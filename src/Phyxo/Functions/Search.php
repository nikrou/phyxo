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

namespace Phyxo\Functions;

use Phyxo\Search\QExpression;
use Phyxo\Search\QSearchScope;
use Phyxo\Search\QResults;
use Phyxo\Search\QDateRangeScope;
use Phyxo\Search\QNumericRangeScope;
use Phyxo\Search\QMultipleToken;
use App\Repository\TagRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageTagRepository;
use App\Repository\ImageRepository;

class Search
{
    /**
     * Returns search rules stored into a serialized array in "search"
     * table. Each search rules set is numericaly identified.
     *
     * @param int $search_id
     * @return array
     */
    public static function get_search_array(int $search_id)
    {
        global $conn;

        $query = 'SELECT rules FROM ' . SEARCH_TABLE . ' WHERE id = ' . $conn->db_real_escape_string($search_id);
        list($serialized_rules) = $conn->db_fetch_row($conn->db_query($query));

        return unserialize($serialized_rules); // @TODO: remove unserialize
    }

    /**
     * Returns the SQL clause for a search.
     * Transforms the array returned by get_search_array() into SQL sub-query.
     *
     * @param array $search
     * @return string
     */
    public static function get_sql_search_clause($search)
    {
        global $conn;

        // SQL where clauses are stored in $clauses array during query
        // construction
        $clauses = [];

        foreach (['file', 'name', 'comment', 'author'] as $textfield) {
            if (isset($search['fields'][$textfield])) {
                $local_clauses = [];
                foreach ($search['fields'][$textfield]['words'] as $word) {
                    if ($textfield == 'author') {
                        $local_clauses[] = $textfield . " = '" . $conn->db_real_escape_string($word) . "'";
                    } else {
                        $local_clauses[] = $textfield . " LIKE '%" . $conn->db_real_escape_string($word) . "%'";
                    }
                }

                // adds brackets around where clauses
                $local_clauses = \Phyxo\Functions\Utils::prepend_append_array_items($local_clauses, '(', ')');

                $clauses[] = implode(' ' . $search['fields'][$textfield]['mode'] . ' ', $local_clauses);
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
                    $field_clauses[] = $field . " LIKE '%" . $conn->db_real_escape_string($word) . "%'";
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
            if ($search['fields']['allwords']['mode'] != 'AND' and $search['fields']['allwords']['mode'] != 'OR') {
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
            if ($search['fields']['cat']['sub_inc']) {
                // searching all the categories id of sub-categories
                $cat_ids = (new CategoryRepository($conn))->getSubcatIds($search['fields']['cat']['words']);
            } else {
                $cat_ids = $search['fields']['cat']['words'];
            }

            $local_clause = 'category_id ' . $conn->in($cat_ids);
            $clauses[] = $local_clause;
        }

        // adds brackets around where clauses
        $clauses = \Phyxo\Functions\Utils::prepend_append_array_items($clauses, '(', ')');

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
    public static function get_regular_search_results($search, $images_where = '')
    {
        global $conf, $conn, $services;

        $forbidden = \Phyxo\Functions\SQL::get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'id'
            ],
            " AND "
        );

        $items = [];
        $tag_items = [];

        if (isset($search['fields']['tags'])) {
            $tag_items = $conn->result2array(
                (new TagRepository($conn))->getImageIdsForTags(
                    $search['fields']['tags']['words'],
                    $search['fields']['tags']['mode']
                ),
                null,
                'id'
            );
        }

        $search_clause = self::get_sql_search_clause($search);

        if (!empty($search_clause)) {
            $query = 'SELECT DISTINCT(id),' . \Phyxo\Functions\SQL::addOrderByFields($conf['order_by']);
            $query .= ' FROM ' . IMAGES_TABLE . ' AS i';
            $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
            $query .= ' WHERE ' . $search_clause;

            if (!empty($images_where)) {
                $query .= ' AND ' . $images_where;
            }
            $query .= $forbidden . ' ' . $conf['order_by'];
            $items = $conn->query2array($query, null, 'id');
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
                    $before_count = count($items);
                    $items = array_unique(
                        array_merge(
                            $items,
                            $tag_items
                        )
                    );
                    break;
            }
        }

        return $items;
    }

    public static function qsearch_get_text_token_search_sql($token, $fields)
    {
        global $conn;

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
                    $clauses[] = $field . ' ' . $conn::REGEX_OPERATOR . ' \'' . $pre . $conn->db_real_escape_string(preg_quote($variant)) . $post . '\'';
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
            $clauses[] = $conn->db_full_text_search($fields, $fts);
        }

        return $clauses;
    }

    public static function qsearch_get_images(QExpression $expr, QResults $qsr)
    {
        global $conn;

        $qsr->images_iids = array_fill(0, count($expr->stokens), []);

        $query_base = 'SELECT id from ' . IMAGES_TABLE . ' i WHERE ';
        for ($i = 0; $i < count($expr->stokens); $i++) {
            $token = $expr->stokens[$i];
            $scope_id = isset($token->scope) ? $token->scope->id : 'photo';
            $clauses = [];

            $like = $conn->db_real_escape_string($token->term);
            $like = str_replace(['%', '_'], ['\\%', '\\_'], $like); // escape LIKE specials %_
            $file_like = 'file LIKE \'%' . $like . '%\'';

            switch ($scope_id) {
                case 'photo':
                    $clauses[] = $file_like;
                    $clauses = array_merge($clauses, self::qsearch_get_text_token_search_sql($token, ['name', 'comment']));
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
                    // allow plugins to have their own scope with columns added in db by themselves
                    $clauses = \Phyxo\Functions\Plugin::trigger_change('qsearch_get_images_sql_scopes', $clauses, $token, $expr);
                    break;
            }
            if (!empty($clauses)) {
                $query = $query_base . '(' . implode(' OR ', $clauses) . ')';
                $qsr->images_iids[$i] = $conn->query2array($query, null, 'id');
            }
        }
    }

    public static function qsearch_get_tags(QExpression $expr, QResults $qsr)
    {
        global $conn;

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

            $clauses = self::qsearch_get_text_token_search_sql($token, ['name']);
            $result = (new TagRepository($conn))->findByClause(implode(' OR ', $clauses));
            while ($tag = $conn->db_fetch_assoc($result)) {
                $token_tag_ids[$i][] = $tag['id'];
                $all_tags[$tag['id']] = $tag;
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

            if (!empty($tag_ids)) {
                $result = (new ImageTagRepository($conn))->findImageByTags($tag_ids);
                $qsr->tag_iids[$i] = $conn->result2array($result, null, 'image_id');
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
                    $result = (new ImageTagRepository($conn))->findImageIds();
                    $qsr->tag_iids[$i] = $conn->result2array($result, null, 'image_id');
                } else { // eg. 'tag:' returns all untagged images
                    $result = (new ImageRepository($conn))->findImageWithNoTag();
                    $qsr->tag_iids[$i] = $conn->result2array($resquery, null, 'id');
                }
            }
        }

        $all_tags = array_intersect_key($all_tags, array_flip(array_diff($positive_ids, $not_ids)));
        usort($all_tags, '\Phyxo\Functions\Utils::tag_alpha_compare');
        foreach ($all_tags as &$tag) {
            $tag['name'] = \Phyxo\Functions\Plugin::trigger_change('render_tag_name', $tag['name'], $tag);
        }
        $qsr->all_tags = $all_tags;
        $qsr->tag_ids = $token_tag_ids;
    }

    public static function qsearch_eval(QMultipleToken $expr, QResults $qsr, &$qualifies, &$ignored_terms)
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
                $crt_ids = self::qsearch_eval($crt, $qsr, $crt_qualifies, $crt_ignored_terms);
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
     *
     * @param string $q
     * @param bool $super_order_by
     * @param string $images_where optional additional restriction on images table
     * @return array
     */
    public static function get_quick_search_results($q, $options)
    {
        global $persistent_cache, $conf, $user;

        $cache_key = $persistent_cache->make_key(
            [
                strtolower($q),
                $conf['order_by'],
                $user['id'], $user['cache_update_time'],
                isset($options['permissions']) ? (boolean)$options['permissions'] : true,
                isset($options['images_where']) ? $options['images_where'] : '',
            ]
        );
        if ($persistent_cache->get($cache_key, $res)) {
            return $res;
        }

        $res = self::get_quick_search_results_no_cache($q, $options);

        if (count($res['items'])) {// cache the results only if not empty - otherwise it is useless
            $persistent_cache->set($cache_key, $res, 300);
        }

        return $res;
    }

    /**
     * @see get_quick_search_results but without result caching
     */
    public static function get_quick_search_results_no_cache($q, $options)
    {
        global $conf, $template, $conn, $services;

        $q = trim(stripslashes($q));
        $search_results = [
            'items' => [],
            'qs' => ['q' => $q]
        ];

        $q = \Phyxo\Functions\Plugin::trigger_change('qsearch_pre', $q);

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
        if ($conf['calendar_datefield'] == 'date_creation') {
            $createdDateAliases[] = 'date';
        } else {
            $postedDateAliases[] = 'date';
        }
        $scopes[] = new QDateRangeScope('created', $createdDateAliases, true);
        $scopes[] = new QDateRangeScope('posted', $postedDateAliases);

        // allow plugins to add their own scopes
        $scopes = \Phyxo\Functions\Plugin::trigger_change('qsearch_get_scopes', $scopes);
        $expression = new QExpression($q, $scopes);

        // get inflections for terms
        $inflector = null;
        $lang_code = ucfirst(substr($services['users']->getDefaultLanguage(), 0, 2));
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

        \Phyxo\Functions\Plugin::trigger_notify('qsearch_expression_parsed', $expression);

        if (count($expression->stokens) == 0) {
            return $search_results;
        }
        $qsr = new QResults;
        self::qsearch_get_tags($expression, $qsr);
        self::qsearch_get_images($expression, $qsr);

        // allow plugins to evaluate their own scopes
        \Phyxo\Functions\Plugin::trigger_notify('qsearch_before_eval', $expression, $qsr);

        $ids = self::qsearch_eval($expression, $qsr, $tmp, $search_results['qs']['unmatched_terms']);

        $debug[] = "<!--\nparsed: " . $expression;
        $debug[] = count($expression->stokens) . ' tokens';
        for ($i = 0; $i < count($expression->stokens); $i++) {
            $debug[] = $expression->stokens[$i] . ': ' . count($qsr->tag_ids[$i]) . ' tags, ' . count($qsr->tag_iids[$i]) . ' tiids, ' . count($qsr->images_iids[$i]) . ' iiids, ' . count($qsr->iids[$i]) . ' iids'
                . ' modifier:' . dechex($expression->stoken_modifiers[$i])
                . (!empty($expression->stokens[$i]->variants) ? ' variants: ' . implode(', ', $expression->stokens[$i]->variants) : '');
        }
        $debug[] = 'before perms ' . count($ids);

        $search_results['qs']['matching_tags'] = $qsr->all_tags;
        $search_results = \Phyxo\Functions\Plugin::trigger_change('qsearch_results', $search_results, $expression, $qsr);

        if (empty($ids)) {
            $debug[] = '-->';
            if ($template->smarty->debugging) {
                $template->append('footer_elements', implode("\n", $debug));
            }
            return $search_results;
        }

        $permissions = !isset($options['permissions']) ? true : $options['permissions'];

        $where_clauses = [];
        $where_clauses[] = 'i.id ' . $conn->in($ids);
        if (!empty($options['images_where'])) {
            $where_clauses[] = '(' . $options['images_where'] . ')';
        }
        if ($permissions) {
            $where_clauses[] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'forbidden_images' => 'i.id'
                ],
                null,
                true
            );
        }

        $query = 'SELECT DISTINCT(id),' . \Phyxo\Functions\SQL::addOrderByFields($conf['order_by']) . ' FROM ' . IMAGES_TABLE . ' i';
        if ($permissions) {
            $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        }
        $query .= ' WHERE ' . implode("\n AND ", $where_clauses) . ' ' . $conf['order_by'];

        $ids = $conn->query2array($query, null, 'id');

        $debug[] = count($ids) . ' final photo count -->';
        $template->append('footer_elements', implode("\n", $debug));

        $search_results['items'] = $ids;
        return $search_results;
    }

    /**
     * Returns an array of 'items' corresponding to the search id.
     * It can be either a quick search or a regular search.
     *
     * @param int $search_id
     * @param bool $super_order_by
     * @param string $images_where optional aditional restriction on images table
     * @return array
     */
    public static function get_search_results($search_id, $super_order_by, $images_where = '')
    {
        $search = self::get_search_array($search_id);
        if (!isset($search['q'])) {
            $result['items'] = self::get_regular_search_results($search, $images_where);
            return $result;
        } else {
            return self::get_quick_search_results($search['q'], ['super_order_by' => $super_order_by, 'images_where' => $images_where]);
        }
    }
}
