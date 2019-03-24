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

use App\Repository\CaddieRepository;

//--------------------------------------------------------------------- include
define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
include(PHPWG_ROOT_PATH . 'include/section_init.inc.php');

// Check Access and exit when user status is not ok
$services['users']->checkStatus(ACCESS_GUEST);


// access authorization check
if (isset($page['category'])) {
    \Phyxo\Functions\Utils::check_restrictions($page['category']['id']);
}

if ($page['start'] > 0 && $page['start'] >= count($page['items'])) {
    \Phyxo\Functions\HTTP::page_not_found('', \Phyxo\Functions\URL::duplicate_index_url(['start' => 0]));
}

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_index');

//---------------------------------------------- change of image display order
if (isset($_GET['image_order'])) {
    if ((int)$_GET['image_order'] > 0) {
        $_SESSION['image_order'] = (int)$_GET['image_order'];
    } else {
        unset($_SESSION['image_order']);
    }
    \Phyxo\Functions\Utils::redirect(
        \Phyxo\Functions\URL::duplicate_index_url(
            [],        // nothing to redefine
            ['start']  // changing display order goes back to section first page
        )
    );
}

if (isset($_GET['display'])) {
    if (array_key_exists($_GET['display'], \Phyxo\Image\ImageStdParams::get_defined_type_map())) {
        $_SESSION['index_deriv'] = $_GET['display'];
    }
}

//-------------------------------------------------------------- initialization
// navigation bar
$page['navigation_bar'] = [];
if (count($page['items']) > $page['nb_image_page']) {
    $page['navigation_bar'] = \Phyxo\Functions\Utils::create_navigation_bar(
        \Phyxo\Functions\URL::duplicate_index_url([], ['start']),
        count($page['items']),
        $page['start'],
        $page['nb_image_page'],
        true,
        'start'
    );
}

$template->assign('thumb_navbar', $page['navigation_bar']);

// caddie filling :-)
if (isset($_GET['caddie'])) {
    (new CaddieRepository($conn))->fillCaddie($user['id'], $page['items']);
    \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::duplicate_index_url());
}

if (isset($page['is_homepage']) and $page['is_homepage']) {
    $canonical_url = \Phyxo\Functions\URL::get_root_url();
} else {
    $start = $page['nb_image_page'] * round($page['start'] / $page['nb_image_page']);
    if ($start > 0 && $start >= count($page['items'])) {
        $start -= $page['nb_image_page'];
    }
    $canonical_url = \Phyxo\Functions\URL::duplicate_index_url(['start' => $start]);
}
$template->assign('U_CANONICAL', $canonical_url);

//-------------------------------------------------------------- page title
$title = $page['title'];
$template_title = $page['section_title'];
if (count($page['items']) > 0) {
    $template->assign('ITEMS', count($page['items']));
}
$template->assign('TITLE', $template_title);

//-------------------------------------------------------------- menubar
include(__DIR__ . '/menubar.inc.php');

// +-----------------------------------------------------------------------+
// |  index page (categories, thumbnails, search, calendar, random, etc.)  |
// +-----------------------------------------------------------------------+
if (empty($page['is_external']) or !$page['is_external']) {
    //----------------------------------------------------- template initialization
    if (isset($page['flat']) or isset($page['chronology_field'])) {
        $template->assign(
            'U_MODE_NORMAL',
            \Phyxo\Functions\URL::duplicate_index_url([], ['chronology_field', 'start', 'flat'])
        );
    }

    if (!isset($page['flat']) && 'categories' === $page['section']) {
        $template->assign(
            'U_MODE_FLAT',
            \Phyxo\Functions\URL::duplicate_index_url(['flat' => ''], ['start', 'chronology_field'])
        );
    }

    if (!isset($page['chronology_field'])) {
        $chronology_params = [
            'chronology_field' => 'created',
            'chronology_style' => 'monthly',
            'chronology_view' => 'list',
        ];
        if ($conf['index_created_date_icon']) {
            $template->assign(
                'U_MODE_CREATED',
                \Phyxo\Functions\URL::duplicate_index_url($chronology_params, ['start', 'flat'])
            );
        }
        if ($conf['index_posted_date_icon']) {
            $chronology_params['chronology_field'] = 'posted';
            $template->assign(
                'U_MODE_POSTED',
                \Phyxo\Functions\URL::duplicate_index_url($chronology_params, ['start', 'flat'])
            );
        }
    } else {
        if ($page['chronology_field'] == 'created') {
            $chronology_field = 'posted';
        } else {
            $chronology_field = 'created';
        }
        if ($conf['index_' . $chronology_field . '_date_icon']) {
            $url = \Phyxo\Functions\URL::duplicate_index_url(
                ['chronology_field' => $chronology_field],
                ['chronology_date', 'start', 'flat']
            );
            $template->assign(
                'U_MODE_' . strtoupper($chronology_field),
                $url
            );
        }
    }

    if ($page['section'] == 'search') {
        $template->assign(
            'U_SEARCH_RULES',
            \Phyxo\Functions\URL::get_root_url() . 'search_rules.php?search_id=' . $page['search']
        );
    }

    if (isset($page['category']) and $services['users']->isAdmin()) {
        $template->assign(
            'U_EDIT',
            \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=album-' . $page['category']['id']
        );
    }

    if ($services['users']->isAdmin() and !empty($page['items'])) {
        $template->assign(
            'U_CADDIE',
            \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::duplicate_index_url(), ['caddie' => 1])
        );
    }

    if ($page['section'] == 'search' && $page['start'] == 0 && !isset($page['chronology_field']) && isset($page['qsearch_details'])) {
        $cats = array_merge(
            (array)@$page['qsearch_details']['matching_cats_no_images'],
            (array)@$page['qsearch_details']['matching_cats']
        );
        if (count($cats)) {
            usort($cats, '\Phyxo\Functions\Utils::name_compare');
            $hints = [];
            foreach ($cats as $cat) {
                $hints[] = \Phyxo\Functions\Category::get_cat_display_name([$cat], '');
            }
            $template->assign('category_search_results', $hints);
        }

        $tags = (array)@$page['qsearch_details']['matching_tags'];
        foreach ($tags as $tag) {
            $tag['URL'] = \Phyxo\Functions\URL::make_index_url(['tags' => [$tag]]);
            $template->append('tag_search_results', $tag);
        }

        if (empty($page['items'])) {
            $template->append('no_search_results', htmlspecialchars($page['qsearch_details']['q']));
        } elseif (!empty($page['qsearch_details']['unmatched_terms'])) {
            $template->assign('no_search_results', array_map($page['qsearch_details']['unmatched_terms'], 'htmlspecialchars'));
        }
    }

    // image order
    if ($conf['index_sort_order_input'] && count($page['items']) > 0 && $page['section'] != 'most_visited' && $page['section'] != 'best_rated') {
        $preferred_image_orders = \Phyxo\Functions\Category::get_category_preferred_image_orders();
        $order_idx = isset($_SESSION['image_order']) ? $_SESSION['image_order'] : 0;

        // get first order field and direction
        $first_order = substr($conf['order_by'], 9);
        if (($pos = strpos($first_order, ',')) !== false) {
            $first_order = substr($first_order, 0, $pos);
        }
        $first_order = trim($first_order);

        $url = \Phyxo\Functions\URL::add_url_params(
            \Phyxo\Functions\URL::duplicate_index_url(),
            ['image_order' => '']
        );
        $tpl_orders = [];
        $order_selected = false;

        foreach ($preferred_image_orders as $order_id => $order) {
            if ($order[2]) {
                // force select if the field is the first field of order_by
                if (!$order_selected && $order[1] == $first_order) {
                    $order_idx = $order_id;
                    $order_selected = true;
                }

                $tpl_orders[$order_id] = [
                    'DISPLAY' => $order[0],
                    'URL' => $url . $order_id,
                    'SELECTED' => $order_idx == $order_id,
                ];
            }
        }

        $tpl_orders[0]['SELECTED'] = !$order_selected; // unselect "Default" if another one is selected
        $template->assign('image_orders', $tpl_orders);
    }

    // category comment
    if ($page['start'] == 0 and !isset($page['chronology_field']) and !empty($page['comment'])) {
        $template->assign('CONTENT_DESCRIPTION', $page['comment']);
    }

    if (isset($page['category']['count_categories']) and $page['category']['count_categories'] == 0) {
        // count_categories might be computed by menubar - if the case unassign flat link if no sub albums
        $template->clear_assign('U_MODE_FLAT');
    }

    //------------------------------------------------------ main part : thumbnails
    if (0 == $page['start'] && !isset($page['flat']) && !isset($page['chronology_field']) && ('recent_cats' == $page['section'] or 'categories' == $page['section'])
        && (!isset($page['category']['count_categories']) || $page['category']['count_categories'] > 0)) {
        include(__DIR__ . '/category_cats.inc.php');

        $template_filename = 'mainpage_categories';
    }

    if (!empty($page['items'])) {
        include(__DIR__ . '/category_default.inc.php');

        if (!isset($page['chronology_field'])) {
            $template_filename = 'thumbnails';
        }
        $url = \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::duplicate_index_url(), ['display' => '']);

        $selected_type = $template->get_template_vars('derivative_params')->type;
        $type_map = \Phyxo\Image\ImageStdParams::get_defined_type_map();
        unset($type_map[IMG_XXLARGE], $type_map[IMG_XLARGE]);

        foreach ($type_map as $params) {
            $template->append(
                'image_derivatives',
                [
                    'DISPLAY' => \Phyxo\Functions\Language::l10n($params->type),
                    'URL' => $url . $params->type,
                    'SELECTED' => ($params->type === $selected_type),
                ]
            );
        }
    }

    // slideshow
    // execute after init thumbs in order to have all picture informations
    if (!empty($page['cat_slideshow_url'])) {
        if (isset($_GET['slideshow'])) {
            \Phyxo\Functions\Utils::redirect($page['cat_slideshow_url']);
        } elseif ($conf['index_slideshow_icon']) {
            $template->assign('U_SLIDESHOW', $page['cat_slideshow_url']);
        }
    }
}

//------------------------------------------------------------ end
\Phyxo\Functions\Plugin::trigger_notify('loc_end_index');
\Phyxo\Functions\Utils::flush_page_messages();

//------------------------------------------------------------ log informations
\Phyxo\Functions\Utils::log();
