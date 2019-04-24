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

use App\Repository\TagRepository;
use App\Repository\CategoryRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ImageRepository;
use App\Repository\BaseRepository;

/**
 * This included page checks section related parameter and provides
 * following informations:
 *
 * - $page['title']
 *
 * - $page['items']: ordered list of items to display
 *
 */

// "index.php?/category/12-foo/start-24" or
// "index.php/category/12-foo/start-24"
// must return :
//
// array(
//   'section'  => 'categories',
//   'category' => array('id'=>12, ...),
//   'start'    => 24
//   );


$page['items'] = [];
$page['start'] = $page['startcat'] = 0;

// some ISPs set PATH_INFO to empty string or to SCRIPT_FILENAME while in the
// default apache implementation it is not set
if ($conf['question_mark_in_urls'] == false and isset($_SERVER['PATH_INFO']) and !empty($_SERVER['PATH_INFO'])) {
    $rewritten = $_SERVER['PATH_INFO'];
    $rewritten = str_replace('//', '/', $rewritten);
    $path_count = count(explode('/', $rewritten));
    $page['root_path'] = __DIR__ . '/../' . str_repeat('../', $path_count - 1);
} else {
    $rewritten = '';
    foreach (array_keys($_GET) as $keynum => $key) {
        $rewritten = $key;
        break;
    }
    $page['root_path'] = __DIR__ . '/../';
}

if (strncmp($page['root_path'], './', 2) == 0) {
    $page['root_path'] = substr($page['root_path'], 2);
}

// deleting first "/" if displayed
$tokens = explode('/', ltrim($rewritten, '/'));
// $tokens = array(
//   0 => category,
//   1 => 12-foo,
//   2 => start-24
//   );

$next_token = 0;

// +-----------------------------------------------------------------------+
// |                             picture page                              |
// +-----------------------------------------------------------------------+
// the first token must be the identifier for the picture
if (\Phyxo\Functions\Utils::script_basename() == 'picture') {
    $token = $tokens[$next_token];
    $next_token++;
    if (is_numeric($token)) {
        $page['image_id'] = $token;
        if ($page['image_id'] == 0) {
            \Phyxo\Functions\HTTP::bad_request('invalid picture identifier');
        }
    } else {
        preg_match('/^(\d+-)?(.*)?$/', $token, $matches);
        if (isset($matches[1]) and is_numeric($matches[1] = rtrim($matches[1], '-'))) {
            $page['image_id'] = $matches[1];
            if (!empty($matches[2])) {
                $page['image_file'] = $matches[2];
            }
        } else {
            $page['image_id'] = 0; // more work in picture.php
            if (!empty($matches[2])) {
                $page['image_file'] = $matches[2];
            } else {
                \Phyxo\Functions\HTTP::bad_request('picture identifier is missing');
            }
        }
    }
}

$page = array_merge($page, \Phyxo\Functions\URL::parse_section_url($tokens, $next_token));

if (!isset($page['section'])) {
    $page['section'] = 'categories';

    switch (\Phyxo\Functions\Utils::script_basename()) {
        case 'picture':
            break;
        case 'index':
            {
            // No section defined, go to random url
                if (!empty($conf['random_index_redirect']) and empty($tokens[$next_token])) {
                    $random_index_redirect = [];
                    foreach ($conf['random_index_redirect'] as $random_url => $random_url_condition) {
                        if (empty($random_url_condition) or eval($random_url_condition)) {
                            $random_index_redirect[] = $random_url;
                        }
                    }
                    if (!empty($random_index_redirect)) {
                        \Phyxo\Functions\Utils::redirect($random_index_redirect[mt_rand(0, count($random_index_redirect) - 1)]);
                    }
                }
                $page['is_homepage'] = true;
                break;
            }
        default:
            trigger_error('script_basename "' . \Phyxo\Functions\Utils::script_basename() . '" unknown', E_USER_WARNING);
    }
}

$page = array_merge($page, \Phyxo\Functions\URL::parse_well_known_params_url($tokens, $next_token));

//access a picture only by id, file or id-file without given section
if (\Phyxo\Functions\Utils::script_basename() == 'picture' and 'categories' == $page['section']
    and !isset($page['category']) and !isset($page['chronology_field'])) {
    $page['flat'] = true;
}

// $page['nb_image_page'] is the number of picture to display on this page
// By default, it is the same as the $user['nb_image_page']
$page['nb_image_page'] = $user['nb_image_page'];

// if flat mode is active, we must consider the image set as a standard set
// and not as a category set because we can't use the #image_category.rank :
// displayed images are not directly linked to the displayed category
if ('categories' == $page['section'] and !isset($page['flat'])) {
    $conf['order_by'] = $conf['order_by_inside_category'];
}

if (!empty($_SESSION['image_order']) && $_SESSION['image_order'] > 0) {
    $image_order_id = $_SESSION['image_order'];

    $orders = \Phyxo\Functions\Category::get_category_preferred_image_orders($userMapper);

    // the current session stored image_order might be not compatible with
    // current image set, for example if the current image_order is the rank
    // and that we are displaying images related to a tag.
    //
    // In case of incompatibility, the session stored image_order is removed.
    if ($orders[$image_order_id][2]) {
        $conf['order_by'] = str_replace(
            'ORDER BY ',
            'ORDER BY ' . $orders[$image_order_id][1] . ',',
            $conf['order_by']
        );
        $page['super_order_by'] = true;
    } else {
        unset($_SESSION['image_order']);
        $page['super_order_by'] = false;
    }
}

$forbidden = (new BaseRepository($conn))->getSQLConditionFandF(
    $app_user,
    $filter,
    [
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'id'
    ],
    'AND'
);

// +-----------------------------------------------------------------------+
// |                              category                                 |
// +-----------------------------------------------------------------------+
if ('categories' == $page['section']) {
    if (isset($page['category'])) {
        $page = array_merge(
            $page,
            [
                'comment' => \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_description',
                    $page['category']['comment'],
                    'main_page_category_description'
                ),
                'title' => \Phyxo\Functions\Category::get_cat_display_name($page['category']['upper_names'], '', false),
            ]
        );
    } else {
        $page['title'] = ''; // will be set later
    }

    // GET IMAGES LIST
    if ($page['startcat'] == 0 and (!isset($page['chronology_field'])) and // otherwise the calendar will requery all subitems
    ((isset($page['category'])) or (isset($page['flat'])))) {
        if (!empty($page['category']['image_order']) and !isset($page['super_order_by'])) {
            $conf['order_by'] = ' ORDER BY ' . $page['category']['image_order'];
        }

        // flat categories mode
        if (isset($page['flat'])) {
            // get all allowed sub-categories
            if (isset($page['category'])) {
                $result = (new CategoryRepository($conn))->findAllowedSubCategories($app_user, $filter, $page['category']['uppercats']);
                $subcat_ids = $conn->result2array($result, null, 'id');
                $subcat_ids[] = $page['category']['id'];
                $where_sql = 'category_id ' . $conn->in($subcat_ids);
                // remove categories from forbidden because just checked above
                $forbidden = (new BaseRepository($conn))->getSQLConditionFandF($app_user, $filter, ['visible_images' => 'id'], 'AND');
            } else {
                unset($page['is_homepage']);
                $where_sql = '1=1';
            }
        } else { // normal mode
            $where_sql = 'category_id = ' . $page['category']['id'];
        }

        // main query
        $result = (new ImageRepository($conn))->searchDistinctId('image_id', [$where_sql . ' ' . $forbidden], true, $conf['order_by']);
        $page['items'] = $conn->result2array($result, null, 'image_id');
    }
} else { // special sections
    if ($page['section'] == 'tags') {
        // +-----------------------------------------------------------------------+
        // |                            tags section                               |
        // +-----------------------------------------------------------------------+
        $page['tag_ids'] = [];
        foreach ($page['tags'] as $tag) {
            $page['tag_ids'][] = $tag['id'];
        }

        $items = $conn->result2array(
            (new TagRepository($conn))->getImageIdsForTags($app_user, $filter, $page['tag_ids']),
            null,
            'id'
        );

        $page = array_merge(
            $page,
            [
                'title' => \Phyxo\Functions\Utils::get_tags_content_title(),
                'items' => $items,
            ]
        );
    } elseif ($page['section'] == 'search') {
        // +-----------------------------------------------------------------------+
        // |                           search section                              |
        // +-----------------------------------------------------------------------+
        $search_result = \Phyxo\Functions\Search::get_search_results($page['search'], @$page['super_order_by']);
        //save the details of the query search
        if (isset($search_result['qs'])) {
            $page['qsearch_details'] = $search_result['qs'];
        }

        $page = array_merge(
            $page,
            [
                'items' => $search_result['items'],
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(['start' => 0]) . '">' . \Phyxo\Functions\Language::l10n('Search results') . '</a>'
            ]
        );
    } elseif ($page['section'] == 'favorites') {
        // +-----------------------------------------------------------------------+
        // |                           favorite section                            |
        // +-----------------------------------------------------------------------+
        if ($user['forbidden_categories']) {
            (new FavoriteRepository($conn))->deleteUnauthorizedImagesFromFavorites($app_user, $filter);
        }

        $page = array_merge($page, ['title' => \Phyxo\Functions\Language::l10n('Favorites')]);

        if (!empty($_GET['action']) && ($_GET['action'] == 'remove_all_from_favorites')) {
            (new FavoriteRepository($conn))->removeAllFavorites($user['id']);
            \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::make_index_url(['section' => 'favorites']));
        } else {
            $result = (new ImageRepository($conn))->getFavorites($app_user, $filter, $conf['order_by']);
            $page = array_merge($page, ['items' => $conn->result2array($result, null, 'image_id')]);

            if (count($page['items']) > 0) {
                $template->assign(
                    'favorite',
                    [
                        'U_FAVORITE' => \Phyxo\Functions\URL::add_url_params(
                            \Phyxo\Functions\URL::make_index_url(['section' => 'favorites']),
                            ['action' => 'remove_all_from_favorites']
                        ),
                    ]
                );
            }
        }
    } elseif ($page['section'] == 'recent_pics') {
        // +-----------------------------------------------------------------------+
        // |                       recent pictures section                         |
        // +-----------------------------------------------------------------------+
        if (!isset($page['super_order_by'])) {
            $conf['order_by'] = str_replace(
                'ORDER BY ',
                'ORDER BY date_available DESC,',
                $conf['order_by']
            );
        }

        $result = (new ImageRepository($conn))->searchDistinctId(
            'id',
            [\Phyxo\Functions\SQL::get_recent_photos('date_available') . ' ' . $forbidden],
            true,
            $conf['order_by']
        );
        $page = array_merge(
            $page,
            [
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(['start' => 0]) . '">' . \Phyxo\Functions\Language::l10n('Recent photos') . '</a>',
                'items' => $conn->result2array($result, null, 'id')
            ]
        );
    } elseif ($page['section'] == 'recent_cats') {
        // +-----------------------------------------------------------------------+
        // |                 recently updated categories section                   |
        // +-----------------------------------------------------------------------+
        $page = array_merge($page, ['title' => \Phyxo\Functions\Language::l10n('Recent albums')]);
    } elseif ($page['section'] == 'most_visited') {
        // +-----------------------------------------------------------------------+
        // |                        most visited section                           |
        // +-----------------------------------------------------------------------+
        $page['super_order_by'] = true;
        $conf['order_by'] = ' ORDER BY hit DESC, id DESC';

        $result = (new ImageRepository($conn))->searchDistinctId('id', ['hit > 0 ' . $forbidden], true, $conf['order_by'], $conf['top_number']);
        $page = array_merge(
            $page,
            [
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(['start' => 0]) . '">' . $conf['top_number'] . ' ' . \Phyxo\Functions\Language::l10n('Most visited') . '</a>',
                'items' => $conn->result2array($result, null, 'id'),
            ]
        );
    } elseif ($page['section'] == 'best_rated') {
        // +-----------------------------------------------------------------------+
        // |                          best rated section                           |
        // +-----------------------------------------------------------------------+
        $page['super_order_by'] = true;
        $conf['order_by'] = ' ORDER BY rating_score DESC, id DESC';

        $result = (new ImageRepository($conn))->searchDistinctId('id', ['rating_score IS NOT NULL ' . $forbidden], true, $conf['order_by'], $conf['top_number']);
        $page = array_merge(
            $page,
            [
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(['start' => 0]) . '">' . $conf['top_number'] . ' ' . \Phyxo\Functions\Language::l10n('Best rated') . '</a>',
                'items' => $conn->result2array($result, null, 'id'),
            ]
        );
    } elseif ($page['section'] == 'list') {
        // +-----------------------------------------------------------------------+
        // |                             list section                              |
        // +-----------------------------------------------------------------------+
        $result = (new ImageRepository($conn))->findList($page['list'], $forbidden, $conf['order_by']);
        $page = array_merge(
            $page,
            [
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(['start' => 0]) . '">' . \Phyxo\Functions\Language::l10n('Random photos') . '</a>',
                'items' => $conn->result2array($result, null, 'id'),
            ]
        );
    }
}

// +-----------------------------------------------------------------------+
// |                             chronology                                |
// +---------------/** URL keyword for list view */
/** URL keyword for list view */
define('CAL_VIEW_LIST', 'list');
/** URL keyword for calendar view */
define('CAL_VIEW_CALENDAR', 'calendar');

if (isset($page['chronology_field'])) {
    unset($page['is_homepage']);

    //-------------------------------------- initialize the calendar parameters ---
    $fields = [
        // Created
        'created' => [
            'label' => \Phyxo\Functions\Language::l10n('Creation date'),
        ],
        // Posted
        'posted' => [
            'label' => \Phyxo\Functions\Language::l10n('Post date'),
        ],
    ];

    $styles = [
        // Monthly style
        'monthly' => [
            'view_calendar' => true,
            'classname' => 'Phyxo\Calendar\CalendarMonthly',
        ],
        // Weekly style
        'weekly' => [
            'view_calendar' => false,
            'classname' => 'Phyxo\Calendar\CalendarWeekly',
        ],
    ];

    $views = [CAL_VIEW_LIST, CAL_VIEW_CALENDAR];

    // Retrieve style
    if (!isset($styles[$page['chronology_style']])) {
        $page['chronology_style'] = 'monthly';
    }
    $cal_style = $page['chronology_style'];
    $classname = $styles[$cal_style]['classname'];

    // Retrieve view
    if (!isset($page['chronology_view']) or !in_array($page['chronology_view'], $views)) {
        $page['chronology_view'] = CAL_VIEW_LIST;
    }

    if (CAL_VIEW_CALENDAR == $page['chronology_view'] && !$styles[$cal_style]['view_calendar']) {
        $page['chronology_view'] = CAL_VIEW_LIST;
    }

    // perform a sanity check on $requested
    if (!isset($page['chronology_date'])) {
        $page['chronology_date'] = [];
    }
    while (count($page['chronology_date']) > 3) {
        array_pop($page['chronology_date']);
    }

    $any_count = 0;
    for ($i = 0; $i < count($page['chronology_date']); $i++) {
        if ($page['chronology_date'][$i] == 'any') {
            if ($page['chronology_view'] == CAL_VIEW_CALENDAR) { // we dont allow any in calendar view
                while ($i < count($page['chronology_date'])) {
                    array_pop($page['chronology_date']);
                }
                break;
            }
            $any_count++;
        } elseif ($page['chronology_date'][$i] == '') {
            while ($i < count($page['chronology_date'])) {
                array_pop($page['chronology_date']);
            }
        } else {
            $page['chronology_date'][$i] = (int)$page['chronology_date'][$i];
        }
    }
    if ($any_count == 3) {
        array_pop($page['chronology_date']);
    }

    $calendar = new $classname($conn, $page['chronology_field']);
    if ($page['section'] == 'categories') { // we will regenerate the items by including subcats elements
        if (isset($page['category'])) {
            $calendar->findByConditionAndCategory(
                (new BaseRepository($conn))->getSQLConditionFandF($app_user, $filter, ['visible_images' => 'id'], 'AND', false),
                $page['category']['id'],
                explode(',', $user['forbidden_categories'])
            );
        } else {
            $calendar->findByCondition(
                (new BaseRepository($conn))->getSQLConditionFandF(
                    $app_user,
                    $filter,
                    [

                        'forbidden_categories' => 'category_id',
                        'visible_categories' => 'category_id',
                        'visible_images' => 'id'
                    ],
                    '',
                    true
                )
            );
        }
    } else {
        $calendar->findByItems($page['items']);
    }

    $must_show_list = true; // true until calendar generates its own display
    if (\Phyxo\Functions\Utils::script_basename() != 'picture') { // basename without file extention
        if ($calendar->generateCategoryContent()) {
            $page['items'] = [];
            $must_show_list = false;
        }

        $page['comment'] = '';

        foreach ($styles as $style => $style_data) {
            foreach ($views as $view) {
                if ($style_data['view_calendar'] or $view != CAL_VIEW_CALENDAR) {
                    $selected = false;

                    if ($style != $cal_style) {
                        $chronology_date = [];
                        if (isset($page['chronology_date'][0])) {
                            $chronology_date[] = $page['chronology_date'][0];
                        }
                    } else {
                        $chronology_date = $page['chronology_date'];
                    }
                    $url = \Phyxo\Functions\URL::duplicate_index_url(
                        [
                            'chronology_style' => $style,
                            'chronology_view' => $view,
                            'chronology_date' => $chronology_date,
                        ]
                    );

                    if ($style == $cal_style and $view == $page['chronology_view']) {
                        $selected = true;
                    }

                    $template->append(
                        'chronology_views',
                        [
                            'VALUE' => $url,
                            'CONTENT' => \Phyxo\Functions\Language::l10n('chronology_' . $style . '_' . $view),
                            'SELECTED' => $selected,
                        ]
                    );
                }
            }
        }
        $url = \Phyxo\Functions\URL::duplicate_index_url([], ['start', 'chronology_date']);
        $calendar_title = '<a href="' . $url . '">' . $fields[$page['chronology_field']]['label'] . '</a>';
        $calendar_title .= $calendar->getDisplayName();
        $template->assign('chronology', ['TITLE' => $calendar_title]);
    } // end category calling

    if ($must_show_list) {
        if (isset($page['super_order_by'])) {
            $order_by = $conf['order_by'];
        } else {
            if (count($page['chronology_date']) == 0
                or in_array('any', $page['chronology_date'])) {// selected period is very big so we show newest first
                $order = ' DESC, ';
            } else { // selected period is small (month,week) so we show oldest first
                $order = ' ASC, ';
            }

            $order_by = str_replace(
                'ORDER BY ',
                'ORDER BY ' . $calendar->date_field . $order,
                $conf['order_by']
            );
        }

        $page['items'] = $calendar->getItems($order_by);
    }
}

// title update
if (isset($page['title'])) {
    $page['section_title'] = '<a href="' . \Phyxo\Functions\URL::get_root_url() . '">' . \Phyxo\Functions\Language::l10n('Home') . '</a>';
    if (!empty($page['title'])) {
        $page['section_title'] .= $conf['level_separator'] . $page['title'];
    } else {
        $page['title'] = $page['section_title'];
    }
}

// see if we need a redirect because of a permalink
if ('categories' == $page['section'] and isset($page['category'])) {
    $need_redirect = false;
    if (empty($page['category']['permalink'])) {
        if ($conf['category_url_style'] == 'id-name' and @$page['hit_by']['cat_url_name'] !== \Phyxo\Functions\Language::str2url($page['category']['name'])) {
            $need_redirect = true;
        }
    } else {
        if ($page['category']['permalink'] !== @$page['hit_by']['cat_permalink']) {
            $need_redirect = true;
        }
    }

    if ($need_redirect) {
        $redirect_url = \Phyxo\Functions\Utils::script_basename() == 'picture' ? \Phyxo\Functions\URL::duplicate_picture_url() : \Phyxo\Functions\URL::duplicate_index_url();

        \Phyxo\Functions\HTTP::set_status_header(301);
        \Phyxo\Functions\Utils::redirect($redirect_url);
    }
    unset($need_redirect, $page['hit_by']);
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_section_init');
