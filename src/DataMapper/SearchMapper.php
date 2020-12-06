<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\DataMapper;

use Phyxo\Search\QExpression;
use Phyxo\Search\QSearchScope;
use Phyxo\Search\QResults;
use Phyxo\Search\QDateRangeScope;
use Phyxo\Search\QNumericRangeScope;
use Phyxo\Search\QMultipleToken;
use App\Repository\TagRepository;
use App\Repository\ImageTagRepository;
use Phyxo\EntityManager;
use Phyxo\Conf;
use App\DataMapper\UserMapper;
use App\Entity\User;
use Phyxo\Functions\Utils;

class SearchMapper
{
    private $em, $conf, $userMapper, $albumMapper, $imageMapper, $tagRepository, $imageTagRepository;

    public function __construct(EntityManager $em, Conf $conf, UserMapper $userMapper, AlbumMapper $albumMapper, ImageMapper $imageMapper,
                                TagRepository $tagRepository, ImageTagRepository $imageTagRepository)
    {
        $this->em = $em;
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->albumMapper = $albumMapper;
        $this->imageMapper = $imageMapper;
        $this->tagRepository = $tagRepository;
        $this->imageTagRepository = $imageTagRepository;
    }

    /**
     * Transforms the array returned by getSearchArray() into array of fields
     */
    public function getSearchClauses(array $search): string
    {
        $clauses = [];

        foreach (['file', 'name', 'comment', 'author'] as $textfield) {
            if (isset($search['fields'][$textfield])) {
                foreach ($search['fields'][$textfield]['words'] as $word) {
                    $clauses[] = [
                        'field' => $textfield,
                        'operator' => $textfield === 'author' ? '=' : 'LIKE',
                        'value' => $word
                    ];
                }
            }
        }

        if (isset($search['fields']['allwords'])) {
            $fields = ['file', 'name', 'comment'];

            if (isset($search['fields']['allwords']['fields']) and count($search['fields']['allwords']['fields']) > 0) {
                $fields = array_intersect($fields, $search['fields']['allwords']['fields']);
            }

            // in the OR mode, request bust be :
            // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
            // OR (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
            //
            // in the AND mode :
            // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
            // AND (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
            $word_clauses = [];
            foreach ($search['fields']['allwords']['words'] as $word) {
                $field_clauses = [];
                foreach ($fields as $field) {
                    $field_clauses[] = $field . " LIKE '%" . $this->em->getConnection()->db_real_escape_string($word) . "%'";
                }
                // adds brackets around where clauses
                $word_clauses[] = implode(' OR ', $field_clauses);
            }

            array_walk(
                $word_clauses,
                function (&$s) {
                    $s = '(' . $s . ')';
                }
            );

            // make sure the "mode" is either OR or AND
            if ($search['fields']['allwords']['mode'] != 'AND' && $search['fields']['allwords']['mode'] != 'OR') {
                $search['fields']['allwords']['mode'] = 'AND';
            }

            $clauses[] = ' ' . implode(' ' . $search['fields']['allwords']['mode'] . ' ', $word_clauses);
        }

        foreach (['date_available', 'date_creation'] as $datefield) {
            if (isset($search['fields'][$datefield])) {
                $clauses[] = $datefield . " = '" . $search['fields'][$datefield]['date'] . "'";
            }

            foreach (['after', 'before'] as $suffix) {
                $key = $datefield . '-' . $suffix;

                if (isset($search['fields'][$key])) {
                    $clauses[] = $datefield . ($suffix == 'after' ? ' >' : ' <') . ($search['fields'][$key]['inc'] ? '=' : '') .
                        " '" . $search['fields'][$key]['date'] . "'";
                }
            }
        }

        if (isset($search['fields']['cat'])) {
            $album_ids = [];
            if ($search['fields']['cat']['sub_inc']) {
                // searching all the categories id of sub-categories
                $album_ids = $this->albumMapper->getRepository()->getSubcatIds($search['fields']['cat']['words']);
            } else {
                $album_ids = $search['fields']['cat']['words'];
            }

            $local_clause = 'category_id ' . $this->em->getConnection()->in($album_ids);
            $clauses[] = $local_clause;
        }

        // adds brackets around where clauses
        $clauses = Utils::prepend_append_array_items($clauses, '(', ')');

        $where_separator = implode(' ' . $search['mode'] . ' ', $clauses);

        $search_clause = $where_separator;

        return $search_clause;
    }

    /**
     * Returns the list of items corresponding to the advanced search array.
     *
     * @param array $search
     * @param string $images_where optional additional restriction on images table
     * @return array
     */
    public function getRegularSearchResults(array $search, User $user): array
    {
        $items = [];
        $tag_items = [];
        $search_clauses = [];

        if (isset($search['fields']['tags'])) {
            $tag_items = [];
            foreach ($this->imageMapper->getRepository()->getImageIdsForTags(
                        $user->getForbiddenCategories(),
                        $search['fields']['tags']['words'],
                        $search['fields']['tags']['mode']) as $image) {
                $tag_items[] = $image->getId();
            }
        }

        $search_clause = $this->getSqlSearchClause($search);

        if (count($search_clauses)) {
            $items = [];
            foreach ($this->imageMapper->getRepository()->searchImages($user->getForbiddenCategories(), $search_clauses, $this->conf['order_by']) as $image) {
                $items[] = $image['id'];
            }
        }

        if (!empty($tag_items)) {
            switch ($search['mode']) {
                case 'AND':
                    if (empty($search_clause)) {
                        $items = $tag_items;
                    } else {
                        $items = array_values(array_intersect($items, $tag_items));
                    }
                    break;
                case 'OR':
                    $items = array_unique(array_merge($items, $tag_items));
                    break;
            }
        }

        return $items;
    }

    public function qsearchGetTextTokenSearchSql($token, $fields)
    {
        $clauses = [];
        $variants = array_merge([$token->term], $token->variants);
        $fts = [];
        foreach ($variants as $variant) {
            $use_ft = mb_strlen($variant) > 3;
            if ($token->modifier & QSearchScope::QST_WILDCARD_BEGIN) {
                $use_ft = false;
            }
            if ($token->modifier & (QSearchScope::QST_QUOTED | QSearchScope::QST_WILDCARD_END) == (QSearchScope::QST_QUOTED | QSearchScope::QST_WILDCARD_END)) {
                $use_ft = false;
            }

            if ($use_ft) {
                $max = max(array_map(
                    'mb_strlen',
                    preg_split('/[' . preg_quote('-\'!"#$%&()*+,./:;<=>?@[\]^`{|}~', '/') . ']+/', $variant)
                ));
                if ($max < 4) {
                    $use_ft = false;
                }
            }

            // odd term or too short for full text search; fallback to regex but unfortunately this is diacritic/accent sensitive
            if (!$use_ft) {
                $pre = ($token->modifier & QSearchScope::QST_WILDCARD_BEGIN) ? '' : '[[:<:]]';
                $post = ($token->modifier & QSearchScope::QST_WILDCARD_END) ? '' : '[[:>:]]';
                foreach ($fields as $field) {
                    $clauses[] = $field . ' ' . $this->em->getConnection()::REGEX_OPERATOR . ' \'' . $pre . $this->em->getConnection()->db_real_escape_string(preg_quote($variant)) . $post . '\'';
                }
            } else {
                $ft = $variant;
                if ($token->modifier & QSearchScope::QST_QUOTED) {
                    $ft = '"' . $ft . '"';
                }
                if ($token->modifier & QSearchScope::QST_WILDCARD_END) {
                    $ft .= '*';
                }
                $fts[] = $ft;
            }
        }

        if (count($fts)) {
            $clauses[] = $this->em->getConnection()->db_full_text_search($fields, $fts);
        }

        return $clauses;
    }

    public function qsearchGetImages(QExpression $expr, QResults $qsr)
    {
        $qsr->images_iids = array_fill(0, count($expr->stokens), []);

        for ($i = 0; $i < count($expr->stokens); $i++) {
            $token = $expr->stokens[$i];
            $scope_id = isset($token->scope) ? $token->scope->id : 'photo';
            $clauses = [];

            $like = $this->em->getConnection()->db_real_escape_string($token->term);
            $like = str_replace(['%', '_'], ['\\%', '\\_'], $like); // escape LIKE specials %_
            $file_like = 'file LIKE \'%' . $like . '%\'';

            switch ($scope_id) {
                case 'photo':
                    $clauses[] = $file_like;
                    $clauses = array_merge($clauses, $this->qsearchGetTextTokenSearchSql($token, ['name', 'comment']));
                    break;

                case 'file':
                    $clauses[] = $file_like;
                    break;
                case 'width':
                case 'height':
                    $clauses[] = $token->scope->get_sql($scope_id, $token);
                    break;
                case 'ratio':
                    $clauses[] = $token->scope->get_sql('width/height', $token);
                    break;
                case 'size':
                    $clauses[] = $token->scope->get_sql('width*height', $token);
                    break;
                case 'hits':
                    $clauses[] = $token->scope->get_sql('hit', $token);
                    break;
                case 'score':
                    $clauses[] = $token->scope->get_sql('rating_score', $token);
                    break;
                case 'filesize':
                    $clauses[] = $token->scope->get_sql('1024*filesize', $token);
                    break;
                case 'created':
                    $clauses[] = $token->scope->get_sql('date_creation', $token);
                    break;
                case 'posted':
                    $clauses[] = $token->scope->get_sql('date_available', $token);
                    break;
                case 'id':
                    $clauses[] = $token->scope->get_sql($scope_id, $token);
                    break;

                default:
                    break;
            }
            if (!empty($clauses)) {
                $qsr->images_iids[$i] = [];
                foreach ($this->imageMapper->getRepository()->qsearchImages($clauses) as $image) {
                    $qsr->images_iids[$i][] = $image->getId();
                }
            }
        }
    }

    public function qsearchGetTags(QExpression $expr, QResults $qsr)
    {
        $token_tag_ids = $qsr->tag_iids = array_fill(0, count($expr->stokens), []);
        $all_tags = [];

        for ($i = 0; $i < count($expr->stokens); $i++) {
            $token = $expr->stokens[$i];
            if (isset($token->scope) && 'tag' != $token->scope->id) {
                continue;
            }
            if (empty($token->term)) {
                continue;
            }

            $clauses = $this->qsearchGetTextTokenSearchSql($token, ['name']);
            foreach ($this->tagRepository->findByClause($clauses) as $tag) {
                $token_tag_ids[$i][] = $tag->getId();
                $all_tags[$tag->getId()] = $tag;
            }
        }

        // check adjacent short words
        for ($i = 0; $i < count($expr->stokens) - 1; $i++) {
            if ((strlen($expr->stokens[$i]->term) <= 3 || strlen($expr->stokens[$i + 1]->term) <= 3)
                && (($expr->stoken_modifiers[$i] & (QSearchScope::QST_QUOTED | QSearchScope::QST_WILDCARD)) == 0)
                && (($expr->stoken_modifiers[$i + 1] & (QSearchScope::QST_BREAK | QSearchScope::QST_QUOTED | QSearchScope::QST_WILDCARD)) == 0)) {
                $common = array_intersect($token_tag_ids[$i], $token_tag_ids[$i + 1]);
                if (count($common)) {
                    $token_tag_ids[$i] = $token_tag_ids[$i + 1] = $common;
                }
            }
        }

        // get images
        $positive_ids = $not_ids = [];
        for ($i = 0; $i < count($expr->stokens); $i++) {
            $tag_ids = $token_tag_ids[$i];
            $token = $expr->stokens[$i];

            if (count($tag_ids) > 0) {
                $qsr->tag_iids[$i] = [];
                foreach ($this->imageTagRepository->findImageByTags($tag_ids) as $image_tag) {
                    $qsr->tag_iids[$i][] = $image_tag->getImage()->getId();
                }
                if ($expr->stoken_modifiers[$i] & QSearchScope::QST_NOT) {
                    $not_ids = array_merge($not_ids, $tag_ids);
                } else {
                    if (strlen($token->term) > 2 || count($expr->stokens) == 1
                        || isset($token->scope) || ($token->modifier & (QSearchScope::QST_WILDCARD | QSearchScope::QST_QUOTED))) {
                        // add tag ids to list only if the word is not too short (such as de / la /les ...)
                        $positive_ids = array_merge($positive_ids, $tag_ids);
                    }
                }
            } elseif (isset($token->scope) && 'tag' == $token->scope->id && strlen($token->term) == 0) {
                if ($token->modifier & QSearchScope::QST_WILDCARD) { // eg. 'tag:*' returns all tagged images
                    $qsr->tag_iids[$i] = [];
                    foreach ($this->imageTagRepository->findImageIds() as $image_tag) {
                        $qsr->tag_iids[$i][] = $image_tag['image_id'];
                    }
                } else { // eg. 'tag:' returns all untagged images
                    $qsr->tag_iids[$i] = [];
                    foreach ($this->tagRepository->findImageWithNoTag() as $tag) {
                        $qsr->tag_iids[$i][] = $tag['image_id'];
                    }
                }
            }
        }

        $all_tags = array_intersect_key($all_tags, array_flip(array_diff($positive_ids, $not_ids)));
        usort($all_tags, '\Phyxo\Functions\Utils::tag_alpha_compare');
        $qsr->all_tags = $all_tags;
        $qsr->tag_ids = $token_tag_ids;
    }

    public function qsearchEval(QMultipleToken $expr, QResults $qsr, &$qualifies, &$ignored_terms)
    {
        $qualifies = false; // until we find at least one positive term
        $ignored_terms = [];

        $ids = $not_ids = [];

        for ($i = 0; $i < count($expr->tokens); $i++) {
            $crt = $expr->tokens[$i];
            if ($crt->is_single) {
                $crt_ids = $qsr->iids[$crt->idx] = array_unique(array_merge($qsr->images_iids[$crt->idx], $qsr->tag_iids[$crt->idx]));
                $crt_qualifies = count($crt_ids) > 0 || count($qsr->tag_ids[$crt->idx]) > 0;
                $crt_ignored_terms = $crt_qualifies ? [] : [(string)$crt];
            } else {
                $crt_ids = $this->qsearchEval($crt, $qsr, $crt_qualifies, $crt_ignored_terms);
            }

            $modifier = $crt->modifier;
            if ($modifier & QSearchScope::QST_NOT) {
                $not_ids = array_unique(array_merge($not_ids, $crt_ids));
            } else {
                $ignored_terms = array_merge($ignored_terms, $crt_ignored_terms);
                if ($modifier & QSearchScope::QST_OR) {
                    $ids = array_unique(array_merge($ids, $crt_ids));
                    $qualifies |= $crt_qualifies;
                } elseif ($crt_qualifies) {
                    if ($qualifies) {
                        $ids = array_intersect($ids, $crt_ids);
                    } else {
                        $ids = $crt_ids;
                    }
                    $qualifies = true;
                }
            }
        }

        if (count($not_ids)) {
            $ids = array_diff($ids, $not_ids);
        }

        return $ids;
    }

    /**
     * Returns the search results corresponding to a quick/query search.
     * A quick/query search returns many items (search is not strict), but results
     * are sorted by relevance unless $super_order_by is true. Returns:
     *  array (
     *    'items' => array of matching images
     *    'qs'    => array(
     *      'unmatched_terms' => array of terms from the input string that were not matched
     *      'matching_tags' => array of matching tags
     *      'matching_cats' => array of matching categories
     *      'matching_cats_no_images' =>array(99) - matching categories without images
     *      )
     *    )
     */
    public function getQuickSearchResults(string $q, $options, array $filter = []): array
    {
        return $this->getQuickSearchResultsNoCache($q, $options, $filter);
    }

    /**
     * @see getQuickSearchResults but without result caching
     */
    public function getQuickSearchResultsNoCache(string $q, $options, array $filter = [])
    {
        $q = trim(stripslashes($q));
        $search_results = [
            'items' => [],
            'qs' => ['q' => $q]
        ];

        $scopes = [];
        $scopes[] = new QSearchScope('tag', ['tags']);
        $scopes[] = new QSearchScope('photo', ['photos']);
        $scopes[] = new QSearchScope('file', ['filename']);
        $scopes[] = new QNumericRangeScope('width', []);
        $scopes[] = new QNumericRangeScope('height', []);
        $scopes[] = new QNumericRangeScope('ratio', [], false, 0.001);
        $scopes[] = new QNumericRangeScope('size', []);
        $scopes[] = new QNumericRangeScope('filesize', []);
        $scopes[] = new QNumericRangeScope('hits', ['hit', 'visit', 'visits']);
        $scopes[] = new QNumericRangeScope('score', ['rating'], true);
        $scopes[] = new QNumericRangeScope('id', []);

        $createdDateAliases = ['taken', 'shot'];
        $postedDateAliases = ['added'];
        if ($this->conf['calendar_datefield'] === 'date_creation') {
            $createdDateAliases[] = 'date';
        } else {
            $postedDateAliases[] = 'date';
        }
        $scopes[] = new QDateRangeScope('created', $createdDateAliases, true);
        $scopes[] = new QDateRangeScope('posted', $postedDateAliases);

        $expression = new QExpression($q, $scopes);

        // get inflections for terms
        $inflector = null;
        $lang_code = ucfirst(substr($this->userMapper->getDefaultLanguage(), 0, 2));
        $class_name = '\Phyxo\Search\Inflector' . $lang_code;
        if (class_exists($class_name)) {
            $inflector = new $class_name;
            foreach ($expression->stokens as $token) {
                if (isset($token->scope) && !$token->scope->is_text) {
                    continue;
                }
                if (strlen($token->term) > 2
                    && ($token->modifier & (QSearchScope::QST_QUOTED | QSearchScope::QST_WILDCARD)) == 0
                    && strcspn($token->term, '\'0123456789') == strlen($token->term)) {
                    $token->variants = array_unique(array_diff($inflector->get_variants($token->term), [$token->term]));
                }
            }
        }

        if (count($expression->stokens) == 0) {
            return $search_results;
        }
        $qsr = new QResults();
        $this->qsearchGetTags($expression, $qsr);
        $this->qsearchGetImages($expression, $qsr);

        $ids = $this->qsearchEval($expression, $qsr, $tmp, $search_results['qs']['unmatched_terms']);

        $debug[] = "<!--\nparsed: " . $expression;
        $debug[] = count($expression->stokens) . ' tokens';
        for ($i = 0; $i < count($expression->stokens); $i++) {
            $debug[] = $expression->stokens[$i] . ': ' . count($qsr->tag_ids[$i]) . ' tags, ' . count($qsr->tag_iids[$i]) . ' tiids, ' . count($qsr->images_iids[$i]) . ' iiids, ' . count($qsr->iids[$i]) . ' iids'
                . ' modifier:' . dechex($expression->stoken_modifiers[$i])
                . (!empty($expression->stokens[$i]->variants) ? ' variants: ' . implode(', ', $expression->stokens[$i]->variants) : '');
        }
        $debug[] = 'before perms ' . count($ids);

        $search_results['qs']['matching_tags'] = $qsr->all_tags;

        if (empty($ids)) {
            $debug[] = '-->';

            return $search_results;
        }

        $ids = [];
        foreach ($this->imageMapper->getRepository()->searchDistinctId($this->userMapper->getUser()->getForbiddenCategories(), $this->conf['order_by']) as $image) {
            $ids[] = $image['id'];
        }

        $debug[] = count($ids) . ' final photo count -->';

        $search_results['items'] = $ids;

        return $search_results;
    }

    /**
     * Returns an array of 'items' corresponding to the search id.
     * It can be either a quick search or a regular search.
     */
    public function getSearchResults(array $rules, User $user, array $filter, bool $super_order_by, string $images_where = ''): array
    {
        if (!isset($rules['q'])) {
            return ['items' => $this->getRegularSearchResults($rules, $user)];
        } else {
            return $this->getQuickSearchResults($rules['q'], ['super_order_by' => $super_order_by, 'images_where' => $images_where], $filter);
        }
    }
}
