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

use App\Repository\CommentRepository;
use App\Repository\CategoryRepository;

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+
define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

if (!$conf['activate_comments']) {
    \Phyxo\Functions\HTTP::page_not_found(null);
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_GUEST);

$url_self = \Phyxo\Functions\URL::get_root_url() . 'comments.php' . \Phyxo\Functions\URL::get_query_string_diff(['delete', 'edit', 'validate', 'pwg_token']);

$sort_order = [
    'DESC' => \Phyxo\Functions\Language::l10n('descending'),
    'ASC' => \Phyxo\Functions\Language::l10n('ascending')
];

// sort_by : database fields proposed for sorting comments list
$sort_by = [
    'date' => \Phyxo\Functions\Language::l10n('comment date'),
    'image_id' => \Phyxo\Functions\Language::l10n('photo')
];

// items_number : list of number of items to display per page
$items_number = [5, 10, 20, 50, 'all'];

// if the default value is not in the expected values, we add it in the $items_number array
if (!in_array($conf['comments_page_nb_comments'], $items_number)) {
    $items_number_new = [];

    $is_inserted = false;

    foreach ($items_number as $number) {
        if ($number > $conf['comments_page_nb_comments'] or ($number == 'all' and !$is_inserted)) {
            $items_number_new[] = $conf['comments_page_nb_comments'];
            $is_inserted = true;
        }

        $items_number_new[] = $number;
    }

    $items_number = $items_number_new;
}

// since when display comments ?
//
$since_options = [
    1 => ['label' => \Phyxo\Functions\Language::l10n('today'), 'clause' => 'date > ' . $conn->db_get_recent_period_expression(1)],
    2 => ['label' => \Phyxo\Functions\Language::l10n('last %d days', 7), 'clause' => 'date > ' . $conn->db_get_recent_period_expression(7)],
    3 => ['label' => \Phyxo\Functions\Language::l10n('last %d days', 30), 'clause' => 'date > ' . $conn->db_get_recent_period_expression(30)],
    4 => ['label' => \Phyxo\Functions\Language::l10n('the beginning'), 'clause' => '1=1'] // stupid but generic
];

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_comments');

if (!empty($_GET['since']) && is_numeric($_GET['since'])) {
    $page['since'] = $_GET['since'];
} else {
    $page['since'] = 4;
}

// on which field sorting
//
$page['sort_by'] = 'date';
// if the form was submitted, it overloads default behaviour
if (isset($_GET['sort_by']) and isset($sort_by[$_GET['sort_by']])) {
    $page['sort_by'] = $_GET['sort_by'];
}

// order to sort
//
$page['sort_order'] = 'DESC';
// if the form was submitted, it overloads default behaviour
if (isset($_GET['sort_order']) and isset($sort_order[$_GET['sort_order']])) {
    $page['sort_order'] = $_GET['sort_order'];
}

// number of items to display
//
$page['items_number'] = $conf['comments_page_nb_comments'];
if (isset($_GET['items_number'])) {
    $page['items_number'] = $_GET['items_number'];
}
if (!is_numeric($page['items_number']) and $page['items_number'] != 'all') {
    $page['items_number'] = 10;
}

$page['where_clauses'] = [];

// which category to filter on ?
if (isset($_GET['cat']) and 0 != $_GET['cat']) {
    \Phyxo\Functions\Utils::check_input_parameter('cat', $_GET, false, PATTERN_ID);

    $category_ids = (new CategoryRepository($conn))->getSubcatIds([$_GET['cat']]);
    if (empty($category_ids)) {
        $category_ids = [-1];
    }

    $page['where_clauses'][] = 'category_id ' . $conn->in($category_ids);
}

// search a particular author
if (!empty($_GET['author'])) {
    $page['where_clauses'][] = '(u.' . $conf['user_fields']['username'] . ' = \'' . $conn->db_real_escape_string($_GET['author'])
        . '\' OR author = \'' . $conn->db_real_escape_string($_GET['author']) . '\')';
}

// search a specific comment (if you're coming directly from an admin
// notification email)
if (!empty($_GET['comment_id'])) {
    \Phyxo\Functions\Utils::check_input_parameter('comment_id', $_GET, false, PATTERN_ID);

    // currently, the $_GET['comment_id'] is only used by admins from email
    // for management purpose (validate/delete)
    if (!$services['users']->isAdmin()) {
        // double urlencode because redirect makes a decode !!
        $login_url = \Phyxo\Functions\URL::get_root_url() . 'identification.php?redirect=' . urlencode(urlencode($_SERVER['REQUEST_URI']));
        \Phyxo\Functions\Utils::redirect($login_url);
    }

    $page['where_clauses'][] = 'com.id = ' . $conn->db_real_escape_string($_GET['comment_id']);
}

// search a substring among comments content
if (!empty($_GET['keyword'])) {
    $page['where_clauses'][] = '(' . implode(
        ' AND ',
        array_map(
            function ($s) {
                return "content LIKE '%$s%'";
            },
            preg_split('/[\s,;]+/', $conn->db_real_escape_string($_GET['keyword']))
        )
    ) . ')';
}

$page['where_clauses'][] = $since_options[$page['since']]['clause'];

// which status to filter on ?
if (!$services['users']->isAdmin()) {
    $page['where_clauses'][] = 'validated = \'' . $conn->boolean_to_db(true) . '\'';
}

$page['where_clauses'][] = \Phyxo\Functions\SQL::get_sql_condition_FandF(
    [
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'ic.image_id'
    ],
    '',
    true
);

// +-----------------------------------------------------------------------+
// |                         comments management                           |
// +-----------------------------------------------------------------------+

$comment_id = null;
$action = null;

$actions = ['delete', 'validate', 'edit'];
foreach ($actions as $loop_action) {
    if (isset($_GET[$loop_action])) {
        $action = $loop_action;
        \Phyxo\Functions\Utils::check_input_parameter($action, $_GET, false, PATTERN_ID);
        $comment_id = $_GET[$action];
        break;
    }
}

if (isset($action)) {
    $comment_author_id = (new CommentRepository($conn))->getCommentAuthorId($comment_id);

    if ($services['users']->canManageComment($action, $comment_author_id)) {
        $perform_redirect = false;

        if ('delete' == $action) {
            \Phyxo\Functions\Utils::check_token();
            $services['comments']->deleteUserComment($comment_id);
            $perform_redirect = true;
        }

        if ('validate' == $action) {
            \Phyxo\Functions\Utils::check_token();
            $services['comments']->validateUserComment($comment_id);
            $perform_redirect = true;
        }

        if ('edit' == $action) {
            if (!empty($_POST['content'])) {
                \Phyxo\Functions\Utils::check_token();
                $comment_action = $services['comments']->updateUserComment(
                    [
                        'comment_id' => $_GET['edit'],
                        'image_id' => $_POST['image_id'],
                        'content' => $_POST['content'],
                        'website_url' => @$_POST['website_url'],
                    ],
                    $_POST['key']
                );

                switch ($comment_action) {
                    case 'moderate':
                        $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('An administrator must authorize your comment before it is visible.');
                    case 'validate':
                        $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Your comment has been registered');
                        $perform_redirect = true;
                        break;
                    case 'reject':
                        $_SESSION['page_errors'][] = \Phyxo\Functions\Language::l10n('Your comment has NOT been registered because it did not pass the validation rules');
                        break;
                    default:
                        trigger_error('Invalid comment action ' . $comment_action, E_USER_WARNING);
                }
            }

            $edit_comment = $_GET['edit'];
        }

        if ($perform_redirect) {
            \Phyxo\Functions\Utils::redirect($url_self);
        }
    }
}

// +-----------------------------------------------------------------------+
// |                       page header and options                         |
// +-----------------------------------------------------------------------+

$title = \Phyxo\Functions\Language::l10n('User comments');
$template->assign(
    [
        'F_ACTION' => \Phyxo\Functions\URL::get_root_url() . 'comments.php',
        'F_KEYWORD' => htmlspecialchars(stripslashes(@$_GET['keyword'])),
        'F_AUTHOR' => htmlspecialchars(stripslashes(@$_GET['author'])),
    ]
);

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

// Search in a particular category
$blockname = 'categories';

$result = (new CategoryRepository($conn))->findWithCondtion(
    [\Phyxo\Functions\SQL::get_sql_condition_FandF(['forbidden_categories' => 'id', 'visible_categories' => 'id'])]
);
$categories = $conn->result2array($result);
\Phyxo\Functions\Category::display_select_cat_wrapper($categories, [@$_GET['cat']], $blockname, true);

// Filter on recent comments...
$tpl_var = [];
foreach ($since_options as $id => $option) {
    $tpl_var[$id] = $option['label'];
}
$template->assign('since_options', $tpl_var);
$template->assign('since_options_selected', $page['since']);

// Sort by
$template->assign('sort_by_options', $sort_by);
$template->assign('sort_by_options_selected', $page['sort_by']);

// Sorting order
$template->assign('sort_order_options', $sort_order);
$template->assign('sort_order_options_selected', $page['sort_order']);


// Number of items
$blockname = 'items_number_option';
$tpl_var = [];
foreach ($items_number as $option) {
    $tpl_var[$option] = is_numeric($option) ? $option : \Phyxo\Functions\Language::l10n($option);
}
$template->assign('item_number_options', $tpl_var);
$template->assign('item_number_options_selected', $page['items_number']);


// +-----------------------------------------------------------------------+
// |                            navigation bar                             |
// +-----------------------------------------------------------------------+

if (isset($_GET['start']) and is_numeric($_GET['start'])) {
    $start = $_GET['start'];
} else {
    $start = 0;
}

// +-----------------------------------------------------------------------+
// |                        last comments display                          |
// +-----------------------------------------------------------------------+

$comments = [];
$element_ids = [];
$category_ids = [];

$query_params = [
    'where_clauses' => $page['where_clauses'],
    'limit' => $page['items_number'],
    'offset' => $start,
    'order_by' => $page['sort_by'] . ' ' . $page['sort_order'],
];
$nb_comments = (new CommentRepository($conn))->getLastComments($query_params, $count_only = true);
$result = (new CommentRepository($conn))->getLastComments($query_params);
while ($row = $conn->db_fetch_assoc($result)) {
    $comments[] = $row;
    $element_ids[] = $row['image_id'];
    $category_ids[] = $row['category_id'];
}

$url = \Phyxo\Functions\URL::get_root_url() . 'comments.php' . \Phyxo\Functions\URL::get_query_string_diff(['start', 'edit', 'delete', 'validate', 'pwg_token']);
$navbar = \Phyxo\Functions\Utils::create_navigation_bar(
    $url,
    $nb_comments,
    $start,
    $page['items_number'],
    ''
);

$template->assign('navbar', $navbar);

if (count($comments) > 0) {
  // retrieving element informations
    $query = 'SELECT * FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id ' . $conn->in($element_ids);
    $elements = $conn->query2array($query, 'id');

    // retrieving category informations
    $result = (new CategoryRepository($conn))->findByIds($category_ids);
    $categories = $conn->result2array($result, 'id');

    foreach ($comments as $comment) {
        if (!empty($elements[$comment['image_id']]['name'])) {
            $name = $elements[$comment['image_id']]['name'];
        } else {
            $name = \Phyxo\Functions\Utils::get_name_from_file($elements[$comment['image_id']]['file']);
        }

        // source of the thumbnail picture
        $src_image = new \Phyxo\Image\SrcImage($elements[$comment['image_id']]);

        // link to the full size picture
        $url = \Phyxo\Functions\URL::make_picture_url(
            [
                'category' => $categories[$comment['category_id']],
                'image_id' => $comment['image_id'],
                'image_file' => $elements[$comment['image_id']]['file'],
            ]
        );

        $email = null;
        if (!empty($comment['user_email'])) {
            $email = $comment['user_email'];
        } elseif (!empty($comment['email'])) {
            $email = $comment['email'];
        }

        $tpl_comment = [
            'ID' => $comment['comment_id'],
            'U_PICTURE' => $url,
            'src_image' => $src_image,
            'ALT' => $name,
            'AUTHOR' => \Phyxo\Functions\Plugin::trigger_change('render_comment_author', $comment['author']),
            'WEBSITE_URL' => $comment['website_url'],
            'DATE' => \Phyxo\Functions\DateTime::format_date($comment['date'], ['day_name', 'day', 'month', 'year', 'time']),
            'CONTENT' => \Phyxo\Functions\Plugin::trigger_change('render_comment_content', $comment['content']),
        ];

        if ($services['users']->isAdmin()) {
            $tpl_comment['EMAIL'] = $email;
        }

        if ($services['users']->canManageComment('delete', $comment['author_id'])) {
            $tpl_comment['U_DELETE'] = \Phyxo\Functions\URL::add_url_params(
                $url_self,
                [
                    'delete' => $comment['comment_id'],
                    'pwg_token' => \Phyxo\Functions\Utils::get_token(),
                ]
            );
        }

        if ($services['users']->canManageComment('edit', $comment['author_id'])) {
            $tpl_comment['U_EDIT'] = \Phyxo\Functions\URL::add_url_params(
                $url_self,
                [
                    'edit' => $comment['comment_id']
                ]
            );

            if (isset($edit_comment) and ($comment['comment_id'] == $edit_comment)) {
                $tpl_comment['IN_EDIT'] = true;
                $key = \Phyxo\Functions\Utils::get_ephemeral_key($conf['key_comment_valid_time'], $comment['image_id']);
                $tpl_comment['KEY'] = $key;
                $tpl_comment['IMAGE_ID'] = $comment['image_id'];
                $tpl_comment['CONTENT'] = $comment['content'];
                $tpl_comment['PWG_TOKEN'] = \Phyxo\Functions\Utils::get_token();
                $tpl_comment['U_CANCEL'] = $url_self;
            }
        }

        if ($services['users']->canManageComment('validate', $comment['author_id'])) {
            if ($conn->is_boolean($comment['validated']) && !$conn->get_boolean($comment['validated'])) {
                $tpl_comment['U_VALIDATE'] = \Phyxo\Functions\URL::add_url_params(
                    $url_self,
                    [
                        'validate' => $comment['comment_id'],
                        'pwg_token' => \Phyxo\Functions\Utils::get_token(),
                    ]
                );
            }
        }
        $template->append('comments', $tpl_comment);
    }
}

$derivative_params = \Phyxo\Functions\Plugin::trigger_change('get_comments_derivative_params', \Phyxo\Image\ImageStdParams::get_by_type(IMG_THUMB));
$template->assign('derivative_params', $derivative_params);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theCommentsPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+
\Phyxo\Functions\Plugin::trigger_notify('loc_end_comments');
\Phyxo\Functions\Utils::flush_page_messages();
