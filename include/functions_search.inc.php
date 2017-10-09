<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire        https://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

use Phyxo\Search\QSearchScope;
use Phyxo\Search\QExpression;
use Phyxo\Search\QDateRangeScope;
use Phyxo\Search\QNumericRangeScope;
use Phyxo\Search\QMultipleToken;
use Phyxo\Search\QResults;

/**
 * Returns search rules stored into a serialized array in "search"
 * table. Each search rules set is numericaly identified.
 *
 * @param int $search_id
 * @return array
 */
function get_search_array($search_id) {
    global $conn;

    if (!is_numeric($search_id)) {
        die('Search id must be an integer');
    }

    $query = 'SELECT rules FROM '.SEARCH_TABLE.' WHERE id = '.$conn->db_real_escape_string($search_id);
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
function get_sql_search_clause($search) {
    global $conn;

    // SQL where clauses are stored in $clauses array during query
    // construction
    $clauses = array();

    foreach (array('file','name','comment','author') as $textfield) {
        if (isset($search['fields'][$textfield])) {
            $local_clauses = array();
            foreach ($search['fields'][$textfield]['words'] as $word) {
                if ($textfield=='author') {
                    $local_clauses[] = $textfield." = '".$conn->db_real_escape_string($word)."'";
                } else {
                    $local_clauses[] = $textfield." LIKE '%".$conn->db_real_escape_string($word)."%'";
                }
            }

            // adds brackets around where clauses
            $local_clauses = prepend_append_array_items($local_clauses, '(', ')');

            $clauses[] = implode(' '.$search['fields'][$textfield]['mode'].' ', $local_clauses);
        }
    }

    if (isset($search['fields']['allwords'])) {
        $fields = array('file', 'name', 'comment');

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
        $word_clauses = array();
        foreach ($search['fields']['allwords']['words'] as $word) {
            $field_clauses = array();
            foreach ($fields as $field) {
                $field_clauses[] = $field." LIKE '%".$conn->db_real_escape_string($word)."%'";
            }
            // adds brackets around where clauses
            $word_clauses[] = implode(' OR ', $field_clauses);
        }

        array_walk(
            $word_clauses,
            function(&$s) { $s = '('.$s.')';}
        );

        // make sure the "mode" is either OR or AND
        if ($search['fields']['allwords']['mode'] != 'AND' and $search['fields']['allwords']['mode'] != 'OR') {
            $search['fields']['allwords']['mode'] = 'AND';
        }

        $clauses[] = ' '.implode(' '.$search['fields']['allwords']['mode'].' ', $word_clauses);
    }

    foreach (array('date_available', 'date_creation') as $datefield) {
        if (isset($search['fields'][$datefield])) {
            $clauses[] = $datefield." = '".$search['fields'][$datefield]['date']."'";
        }

        foreach (array('after','before') as $suffix) {
            $key = $datefield.'-'.$suffix;

            if (isset($search['fields'][$key])) {
                $clauses[] = $datefield.($suffix == 'after' ? ' >' : ' <'). ($search['fields'][$key]['inc'] ? '='  : '').
                    " '".$search['fields'][$key]['date']."'";
            }
        }
    }

    if (isset($search['fields']['cat'])) {
        if ($search['fields']['cat']['sub_inc']) {
            // searching all the categories id of sub-categories
            $cat_ids = get_subcat_ids($search['fields']['cat']['words']);
        } else {
            $cat_ids = $search['fields']['cat']['words'];
        }

        $local_clause = 'category_id '.$conn->in($cat_ids);
        $clauses[] = $local_clause;
    }

    // adds brackets around where clauses
    $clauses = prepend_append_array_items($clauses, '(', ')');

    $where_separator = implode(' '.$search['mode'].' ', $clauses);

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
function get_regular_search_results($search, $images_where='') {
    global $conf, $conn, $services;

    $forbidden = get_sql_condition_FandF(
        array(
            'forbidden_categories' => 'category_id',
            'visible_categories' => 'category_id',
            'visible_images' => 'id'
        ),
        "\n  AND"
    );

    $items = array();
    $tag_items = array();

  if (isset($search['fields']['tags'])) {
      $tag_items = $services['tags']->getImageIdsForTags(
          $search['fields']['tags']['words'],
          $search['fields']['tags']['mode']
      );
  }

  $search_clause = get_sql_search_clause($search);

  if (!empty($search_clause)) {
      $query = 'SELECT DISTINCT(id),'.addOrderByFields($conf['order_by']);
      $query .= ' FROM '.IMAGES_TABLE.' AS i';
      $query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON id = ic.image_id';
      $query .= ' WHERE '.$search_clause;

      if (!empty($images_where)) {
          $query .= ' AND '.$images_where;
      }
      $query .= $forbidden.' '.$conf['order_by'];
      $items = $conn->query2array($query, null, 'id');
  }

  if (!empty($tag_items)) {
      switch ($search['mode'])
          {
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

function qsearch_get_text_token_search_sql($token, $fields) {
    global $conn;

    $clauses = array();
    $variants = array_merge(array($token->term), $token->variants);
    $fts = array();
    foreach ($variants as $variant) {
        $use_ft = mb_strlen($variant)>3;
        if ($token->modifier & QSearchScope::QST_WILDCARD_BEGIN) {
            $use_ft = false;
        }
        if ($token->modifier & (QSearchScope::QST_QUOTED|QSearchScope::QST_WILDCARD_END) == (QSearchScope::QST_QUOTED|QSearchScope::QST_WILDCARD_END)) {
            $use_ft = false;
        }

        if ($use_ft) {
            $max = max(array_map(
                'mb_strlen',
                preg_split('/['.preg_quote('-\'!"#$%&()*+,./:;<=>?@[\]^`{|}~','/').']+/', $variant))
            );
            if ($max<4) {
                $use_ft = false;
            }
        }

        // odd term or too short for full text search; fallback to regex but unfortunately this is diacritic/accent sensitive
        if (!$use_ft) {
            $pre = ($token->modifier & QSearchScope::QST_WILDCARD_BEGIN) ? '' : '[[:<:]]';
            $post = ($token->modifier & QSearchScope::QST_WILDCARD_END) ? '' : '[[:>:]]';
            foreach( $fields as $field) {
                $clauses[] = $field.' '.$conn::REGEX_OPERATOR.' \''.$pre.$conn->db_real_escape_string(preg_quote($variant)).$post.'\'';
            }
        } else {
            $ft = $variant;
            if ($token->modifier & QSearchScope::QST_QUOTED) {
                $ft = '"'.$ft.'"';
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

function qsearch_get_images(QExpression $expr, QResults $qsr) {
    global $conn;

    $qsr->images_iids = array_fill(0, count($expr->stokens), array());

    $query_base = 'SELECT id from '.IMAGES_TABLE.' i WHERE ';
    for ($i=0; $i<count($expr->stokens); $i++) {
        $token = $expr->stokens[$i];
        $scope_id = isset($token->scope) ? $token->scope->id : 'photo';
        $clauses = array();

        $like = $conn->db_real_escape_string($token->term);
        $like = str_replace( array('%','_'), array('\\%','\\_'), $like); // escape LIKE specials %_
        $file_like = 'file LIKE \'%'.$like.'%\'';

        switch ($scope_id)
            {
            case 'photo':
                $clauses[] = $file_like;
                $clauses = array_merge($clauses, qsearch_get_text_token_search_sql($token, array('name','comment')));
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
                $clauses = trigger_change('qsearch_get_images_sql_scopes', $clauses, $token, $expr);
                break;
            }
        if (!empty($clauses)) {
            $query = $query_base.'('.implode(' OR ', $clauses).')';
            $qsr->images_iids[$i] = $conn->query2array($query,null,'id');
        }
    }
}

function qsearch_get_tags(QExpression $expr, QResults $qsr) {
    global $conn;

    $token_tag_ids = $qsr->tag_iids = array_fill(0, count($expr->stokens), array());
    $all_tags = array();

    for ($i=0; $i<count($expr->stokens); $i++) {
        $token = $expr->stokens[$i];
        if (isset($token->scope) && 'tag' != $token->scope->id) {
            continue;
        }
        if (empty($token->term)) {
            continue;
        }

        $clauses = qsearch_get_text_token_search_sql( $token, array('name'));
        $query = 'SELECT * FROM '.TAGS_TABLE.' WHERE ('. implode("\n OR ",$clauses) .')';
        $result = $conn->db_query($query);
        while ($tag = $conn->db_fetch_assoc($result)) {
            $token_tag_ids[$i][] = $tag['id'];
            $all_tags[$tag['id']] = $tag;
        }
    }

    // check adjacent short words
    for ($i=0; $i<count($expr->stokens)-1; $i++) {
        if ((strlen($expr->stokens[$i]->term)<=3 || strlen($expr->stokens[$i+1]->term)<=3)
        && (($expr->stoken_modifiers[$i] & (QSearchScope::QST_QUOTED|QSearchScope::QST_WILDCARD)) == 0)
        && (($expr->stoken_modifiers[$i+1] & (QSearchScope::QST_BREAK|QSearchScope::QST_QUOTED|QSearchScope::QST_WILDCARD)) == 0)) {
            $common = array_intersect( $token_tag_ids[$i], $token_tag_ids[$i+1] );
            if (count($common)) {
                $token_tag_ids[$i] = $token_tag_ids[$i+1] = $common;
            }
        }
    }

    // get images
    $positive_ids = $not_ids = array();
    for ($i=0; $i<count($expr->stokens); $i++) {
        $tag_ids = $token_tag_ids[$i];
        $token = $expr->stokens[$i];

        if (!empty($tag_ids)) {
            $query = 'SELECT image_id FROM '.IMAGE_TAG_TABLE;
            $query .= ' WHERE tag_id '.$conn->in($tag_ids);
            $query .= ' GROUP BY image_id';
            $qsr->tag_iids[$i] = $conn->query2array($query, null, 'image_id');
            if ($expr->stoken_modifiers[$i]&QSearchScope::QST_NOT) {
                $not_ids = array_merge($not_ids, $tag_ids);
            } else {
                if (strlen($token->term)>2 || count($expr->stokens)==1
                || isset($token->scope) || ($token->modifier&(QSearchScope::QST_WILDCARD|QSearchScope::QST_QUOTED))) {
                    // add tag ids to list only if the word is not too short (such as de / la /les ...)
                    $positive_ids = array_merge($positive_ids, $tag_ids);
                }
            }
        } elseif (isset($token->scope) && 'tag' == $token->scope->id && strlen($token->term)==0) {
            if ($token->modifier & QSearchScope::QST_WILDCARD) { // eg. 'tag:*' returns all tagged images
                $qsr->tag_iids[$i] = $conn->query2array('SELECT DISTINCT image_id FROM '.IMAGE_TAG_TABLE, null, 'image_id');
            } else { // eg. 'tag:' returns all untagged images
                $query = 'SELECT id FROM '.IMAGES_TABLE;
                $query .= ' LEFT JOIN '.IMAGE_TAG_TABLE.' ON id=image_id WHERE image_id IS NULL';
                $qsr->tag_iids[$i] = $conn->query2array($query, null, 'id');
            }
        }
    }

    $all_tags = array_intersect_key($all_tags, array_flip( array_diff($positive_ids, $not_ids) ) );
    usort($all_tags, 'tag_alpha_compare');
    foreach ( $all_tags as &$tag) {
        $tag['name'] = trigger_change('render_tag_name', $tag['name'], $tag);
    }
    $qsr->all_tags = $all_tags;
    $qsr->tag_ids = $token_tag_ids;
}

function qsearch_eval(QMultipleToken $expr, QResults $qsr, &$qualifies, &$ignored_terms) {
    $qualifies = false; // until we find at least one positive term
    $ignored_terms = array();

    $ids = $not_ids = array();

    for ($i=0; $i<count($expr->tokens); $i++) {
        $crt = $expr->tokens[$i];
        if ($crt->is_single) {
            $crt_ids = $qsr->iids[$crt->idx] = array_unique( array_merge($qsr->images_iids[$crt->idx], $qsr->tag_iids[$crt->idx]));
            $crt_qualifies = count($crt_ids)>0 || count($qsr->tag_ids[$crt->idx])>0;
            $crt_ignored_terms = $crt_qualifies ? array() : array((string) $crt);
        } else {
            $crt_ids = qsearch_eval($crt, $qsr, $crt_qualifies, $crt_ignored_terms);
        }

        $modifier = $crt->modifier;
        if ($modifier & QSearchScope::QST_NOT) {
            $not_ids = array_unique( array_merge($not_ids, $crt_ids));
        } else {
            $ignored_terms = array_merge($ignored_terms, $crt_ignored_terms);
            if ($modifier & QSearchScope::QST_OR) {
                $ids = array_unique( array_merge($ids, $crt_ids) );
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
function get_quick_search_results($q, $options) {
    global $persistent_cache, $conf, $user;

    $cache_key = $persistent_cache->make_key(
        array(
            strtolower($q),
            $conf['order_by'],
            $user['id'],$user['cache_update_time'],
            isset($options['permissions']) ? (boolean)$options['permissions'] : true,
            isset($options['images_where']) ? $options['images_where'] : '',
        )
    );
    if ($persistent_cache->get($cache_key, $res)) {
        return $res;
    }

    $res = get_quick_search_results_no_cache($q, $options);

    if (count($res['items'])) {// cache the results only if not empty - otherwise it is useless
        $persistent_cache->set($cache_key, $res, 300);
    }

    return $res;
}

/**
 * @see get_quick_search_results but without result caching
 */
function get_quick_search_results_no_cache($q, $options) {
    global $conf, $template, $conn, $services;

    $q = trim(stripslashes($q));
    $search_results = array(
        'items' => array(),
        'qs' => array('q' => $q)
    );

    $q = trigger_change('qsearch_pre', $q);

    $scopes = array();
    $scopes[] = new QSearchScope('tag', array('tags'));
    $scopes[] = new QSearchScope('photo', array('photos'));
    $scopes[] = new QSearchScope('file', array('filename'));
    $scopes[] = new QNumericRangeScope('width', array());
    $scopes[] = new QNumericRangeScope('height', array());
    $scopes[] = new QNumericRangeScope('ratio', array(), false, 0.001);
    $scopes[] = new QNumericRangeScope('size', array());
    $scopes[] = new QNumericRangeScope('filesize', array());
    $scopes[] = new QNumericRangeScope('hits', array('hit', 'visit', 'visits'));
    $scopes[] = new QNumericRangeScope('score', array('rating'), true);
    $scopes[] = new QNumericRangeScope('id', array());

    $createdDateAliases = array('taken', 'shot');
    $postedDateAliases = array('added');
    if ($conf['calendar_datefield'] == 'date_creation') {
        $createdDateAliases[] = 'date';
    } else {
        $postedDateAliases[] = 'date';
    }
    $scopes[] = new QDateRangeScope('created', $createdDateAliases, true);
    $scopes[] = new QDateRangeScope('posted', $postedDateAliases);

    // allow plugins to add their own scopes
    $scopes = trigger_change('qsearch_get_scopes', $scopes);
    $expression = new QExpression($q, $scopes);

    // get inflections for terms
    $inflector = null;
    $lang_code = substr($services['users']->getDefaultLanguage(), 0, 2);
    @include_once(PHPWG_ROOT_PATH.'include/inflectors/'.$lang_code.'.php');
    $class_name = 'Inflector_'.$lang_code;
    if (class_exists($class_name)) {
        $inflector = new $class_name;
        foreach( $expression->stokens as $token) {
            if (isset($token->scope) && !$token->scope->is_text) {
                continue;
            }
            if (strlen($token->term)>2
                && ($token->modifier & (QSearchScope::QST_QUOTED|QSearchScope::QST_WILDCARD))==0
                && strcspn($token->term, '\'0123456789') == strlen($token->term)) {
                $token->variants = array_unique( array_diff( $inflector->get_variants($token->term), array($token->term) ) );
            }
        }
    }


    trigger_notify('qsearch_expression_parsed', $expression);

    if (count($expression->stokens)==0) {
        return $search_results;
    }
    $qsr = new QResults;
    qsearch_get_tags($expression, $qsr);
    qsearch_get_images($expression, $qsr);

    // allow plugins to evaluate their own scopes
    trigger_notify('qsearch_before_eval', $expression, $qsr);

    $ids = qsearch_eval($expression, $qsr, $tmp, $search_results['qs']['unmatched_terms']);

    $debug[] = "<!--\nparsed: ".$expression;
    $debug[] = count($expression->stokens).' tokens';
    for ($i=0; $i<count($expression->stokens); $i++) {
        $debug[] = $expression->stokens[$i].': '.count($qsr->tag_ids[$i]).' tags, '.count($qsr->tag_iids[$i]).' tiids, '.count($qsr->images_iids[$i]).' iiids, '.count($qsr->iids[$i]).' iids'
            .' modifier:'.dechex($expression->stoken_modifiers[$i])
                         .( !empty($expression->stokens[$i]->variants) ? ' variants: '.implode(', ',$expression->stokens[$i]->variants): '');
    }
    $debug[] = 'before perms '.count($ids);

    $search_results['qs']['matching_tags'] = $qsr->all_tags;
    $search_results = trigger_change('qsearch_results', $search_results, $expression, $qsr);

    if (empty($ids)) {
        $debug[] = '-->';
        if ($template->smarty->debugging) {
            $template->append('footer_elements', implode("\n", $debug) );
        }
        return $search_results;
    }

    $permissions = !isset($options['permissions']) ? true : $options['permissions'];

    $where_clauses = array();
    $where_clauses[] = 'i.id '.$conn->in($ids);
    if (!empty($options['images_where'])) {
        $where_clauses[]='('.$options['images_where'].')';
    }
    if ($permissions) {
        $where_clauses[] = get_sql_condition_FandF(
            array(
                'forbidden_categories' => 'category_id',
                'forbidden_images' => 'i.id'
            ),
            null,true
        );
    }

    $query = 'SELECT DISTINCT(id),'.addOrderByFields($conf['order_by']).' FROM '.IMAGES_TABLE.' i';
    if ($permissions) {
        $query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON id = ic.image_id';
    }
    $query .= ' WHERE '.implode("\n AND ", $where_clauses).' '.$conf['order_by'];

    $ids = $conn->query2array($query, null, 'id');

    $debug[] = count($ids).' final photo count -->';
    $template->append('footer_elements', implode("\n", $debug) );

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
function get_search_results($search_id, $super_order_by, $images_where='') {
    $search = get_search_array($search_id);
    if (!isset($search['q'])) {
        $result['items'] = get_regular_search_results($search, $images_where);
        return $result;
    } else {
        return get_quick_search_results($search['q'], array('super_order_by'=>$super_order_by, 'images_where'=>$images_where));
    }
}
