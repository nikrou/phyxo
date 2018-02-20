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

if (!defined('IN_ADMIN')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                            variables init                             |
// +-----------------------------------------------------------------------+

if (isset($_GET['user_id']) and is_numeric($_GET['user_id'])) {
    $page['user'] = $_GET['user_id'];
} else {
    die('user_id URL parameter is missing');
}

// +-----------------------------------------------------------------------+
// |                                updates                                |
// +-----------------------------------------------------------------------+

if (isset($_POST['falsify']) && isset($_POST['cat_true']) && count($_POST['cat_true']) > 0) {
    // if you forbid access to a category, all sub-categories become
    // automatically forbidden
    $subcats = get_subcat_ids($_POST['cat_true']);
    $query = 'DELETE FROM '.USER_ACCESS_TABLE;
    $query .= ' WHERE user_id = '.$page['user'].' AND cat_id '.$conn->in($subcats);
    $conn->db_query($query);
} elseif (isset($_POST['trueify']) && isset($_POST['cat_false']) && count($_POST['cat_false']) > 0) {
    add_permission_on_category($_POST['cat_false'], $page['user']);
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'user_perm';
$template->set_filenames(['double_select' => 'double_select.tpl']);

$template->assign(
    array(
        'TITLE' =>
        l10n(
            'Manage permissions for user "%s"',
            get_username($page['user'])
        ),
        'L_CAT_OPTIONS_TRUE'=>l10n('Authorized'),
        'L_CAT_OPTIONS_FALSE'=>l10n('Forbidden'),

        'F_ACTION' =>
        get_root_url().'admin/index.php?page=user_perm&amp;user_id='.$page['user']
    )
);


// retrieve category ids authorized to the groups the user belongs to
$group_authorized = array();

$query = 'SELECT DISTINCT cat_id, c.uppercats, c.global_rank FROM '.GROUP_ACCESS_TABLE.' AS ga';
$query .= ' LEFT JOIN '.USER_GROUP_TABLE.' AS ug ON ug.group_id = ga.group_id';
$query .= ' LEFT JOIN '.CATEGORIES_TABLE.' AS c ON c.id = ga.cat_id';
$query .= ' WHERE ug.user_id = '.$page['user'];
$result = $conn->db_query($query);



if ($conn->db_num_rows($result) > 0) {
    $cats = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $cats[] = $row;
        $group_authorized[] = $row['cat_id'];
    }
    usort($cats, 'global_rank_compare');

    foreach ($cats as $category) {
        $template->append(
            'categories_because_of_groups',
            get_cat_display_name_cache($category['uppercats'], null)
        );
    }
}

// only private categories are listed
$query_true = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
$query_true .= ' LEFT JOIN '.USER_ACCESS_TABLE.' ON cat_id = id';
$query_true .= ' WHERE status = \'private\' AND user_id = '.$page['user'];
if (count($group_authorized) > 0) {
    $query_true .= ' AND cat_id NOT '.$conn->in($group_authorized);
}
display_select_cat_wrapper($query_true, array(), 'category_option_true');

$result = $conn->db_query($query_true);
$authorized_ids = array();
while ($row = $conn->db_fetch_assoc($result)) {
    $authorized_ids[] = $row['id'];
}

$query_false = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
$query_false .= ' WHERE status = \'private\'';
if (count($authorized_ids) > 0) {
    $query_false .= ' AND id NOT '.$conn->in($authorized_ids);
}
if (count($group_authorized) > 0) {
    $query_false .= ' AND id NOT '.$conn->in($group_authorized);
}
display_select_cat_wrapper($query_false,array(),'category_option_false');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
