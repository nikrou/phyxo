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

use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;

//--------------------------------------------------------------------- include
define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_GUEST);

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_search');

//------------------------------------------------------------------ form check
$search = [];
if (isset($_POST['submit'])) {
    foreach ($_POST as $post_key => $post_value) {
        if (!is_array($post_value)) {
            $_POST[$post_key] = $conn->db_real_escape_string($post_value);
        }
    }

    if (isset($_POST['search_allwords']) && !preg_match('/^\s*$/', $_POST['search_allwords'])) {
        \Phyxo\Functions\Utils::check_input_parameter('mode', $_POST, false, '/^(OR|AND)$/');

        $fields = array_intersect($_POST['fields'], ['name', 'comment', 'file']);

        $drop_char_match = [
            '-', '^', '$', ';', '#', '&', '(', ')', '<', '>', '`', '\'', '"', '|', ',', '@', '_',
            '?', '%', '~', '.', '[', ']', '{', '}', ':', '\\', '/', '=', '\'', '!', '*'
        ];
        $drop_char_replace = [
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', '', ' ',
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', ' ', ' ', ' ', ' ', ' '
        ];

        // Split words
        $search['fields']['allwords'] = [
            'words' => array_unique(
                preg_split(
                    '/\s+/',
                    str_replace(
                        $drop_char_match,
                        $drop_char_replace,
                        $_POST['search_allwords']
                    )
                )
            ),
            'mode' => $_POST['mode'],
            'fields' => $fields,
        ];
    }

    if (isset($_POST['tags'])) {
        \Phyxo\Functions\Utils::check_input_parameter('tags', $_POST, true, PATTERN_ID);
        \Phyxo\Functions\Utils::check_input_parameter('tag_mode', $_POST, false, '/^(OR|AND)$/');

        $search['fields']['tags'] = [
            'words' => $_POST['tags'],
            'mode' => $_POST['tag_mode'],
        ];
    }

    if (isset($_POST['authors']) && is_array($_POST['authors']) && count($_POST['authors']) > 0) {
        $authors = [];

        foreach ($_POST['authors'] as $author) {
            $authors[] = strip_tags($author);
        }

        $search['fields']['author'] = [
            'words' => $authors,
            'mode' => 'OR',
        ];
    }

    if (isset($_POST['cat'])) {
        \Phyxo\Functions\Utils::check_input_parameter('cat', $_POST, true, PATTERN_ID);

        $search['fields']['cat'] = [
            'words' => $_POST['cat'],
            'sub_inc' => ($_POST['subcats-included'] == 1) ? true : false,
        ];
    }

    // dates
    $type_date = $_POST['date_type'];

    if (!empty($_POST['start_year'])) {
        $search['fields'][$type_date . '-after'] = [
            'date' => sprintf(
                '%d-%02d-%02d',
                $_POST['start_year'],
                $_POST['start_month'] != 0 ? $_POST['start_month'] : '01',
                $_POST['start_day'] != 0 ? $_POST['start_day'] : '01'
            ),
            'inc' => true,
        ];
    }

    if (!empty($_POST['end_year'])) {
        $search['fields'][$type_date . '-before'] = [
            'date' => sprintf(
                '%d-%02d-%02d',
                $_POST['end_year'],
                $_POST['end_month'] != 0 ? $_POST['end_month'] : '12',
                $_POST['end_day'] != 0 ? $_POST['end_day'] : '31'
            ),
            'inc' => true,
        ];
    }

    if (!empty($search)) {
        // default search mode : each clause must be respected
        $search['mode'] = 'AND';

        // register search rules in database, then they will be available on
        // thumbnails page and picture page.
        $query = 'INSERT INTO ' . SEARCH_TABLE . ' (rules, last_seen) VALUES (\'' . serialize($search) . '\', NOW());';
        $conn->db_query($query);
        $search_id = $conn->db_insert_id(SEARCH_TABLE);
    } else {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Empty query. No criteria has been entered.');
    }
}
//----------------------------------------------------------------- redirection
if (isset($_POST['submit']) and count($page['errors']) == 0) {
    \Phyxo\Functions\Utils::redirect(
        \Phyxo\Functions\URL::make_index_url(
            [
                'section' => 'search',
                'search' => $search_id,
            ]
        )
    );
}
//----------------------------------------------------- template initialization

//
// Start output of page
//
$title = \Phyxo\Functions\Language::l10n('Search');

$month_list = $lang['month'];
$month_list[0] = '------------';
ksort($month_list);

$template->assign(
    [
        'F_SEARCH_ACTION' => \Phyxo\Functions\URL::get_root_url() . 'search.php',
        'month_list' => $month_list,
        'START_DAY_SELECTED' => @$_POST['start_day'],
        'START_MONTH_SELECTED' => @$_POST['start_month'],
        'END_DAY_SELECTED' => @$_POST['end_day'],
        'END_MONTH_SELECTED' => @$_POST['end_month'],
    ]
);

$available_tags = $services['tags']->getAvailableTags($user);

if (count($available_tags) > 0) {
    usort($available_tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

    $template->assign('TAGS', $available_tags);
}

// authors
$authors = [];
$author_counts = [];
$result = (new ImageRepository($conn))->findGroupByAuthor();
while ($row = $conn->db_fetch_assoc($result)) {
    if (!isset($author_counts[$row['author']])) {
        $author_counts[$row['author']] = 0;
    }

    $author_counts[$row['author']]++;
}

foreach ($author_counts as $author => $counter) {
    $authors[] = [
        'author' => $author,
        'counter' => $counter,
    ];
}

$template->assign('AUTHORS', $authors);

//------------------------------------------------------------- categories form
$where = [];
if ($filter_condtion = \Phyxo\Functions\SQL::get_sql_condition_FandF(
    [
        'forbidden_categories' => 'id',
        'visible_categories' => 'id'
    ]
)) {
    $where[] = $filter_condtion;
}
$result = (new CategoryRepository($conn))->findWithCondtion($where);
$categories = $conn->result2array($result);
\Phyxo\Functions\Category::display_select_cat_wrapper($categories, [], 'category_options', true);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theSearchPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

//------------------------------------------------------------ html code display
\Phyxo\Functions\Plugin::trigger_notify('loc_end_search');
\Phyxo\Functions\Utils::flush_page_messages();
