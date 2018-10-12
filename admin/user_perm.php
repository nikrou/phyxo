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
use App\Repository\GroupAccessRepository;
use App\Repository\UserAccessRepository;

if (!defined('IN_ADMIN')) {
    die('Hacking attempt!');
}

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

if (isset($_POST['falsify'], $_POST['cat_true']) && count($_POST['cat_true']) > 0) {
    // if you forbid access to a category, all sub-categories become automatically forbidden
    $subcats = (new CategoryRepository($conn))->getSubcatIds($_POST['cat_true']);
    (new UserAccessRepository($conn))->deleteByUserIdsAndCatIds([$page['user']], $subcats);
} elseif (isset($_POST['trueify'], $_POST['cat_false']) && count($_POST['cat_false']) > 0) {
    \Phyxo\Functions\Category::add_permission_on_category($_POST['cat_false'], $page['user']);
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'user_perm';
$template->set_filenames(['double_select' => 'double_select.tpl']);

$template->assign(
    [
        'TITLE' =>
            \Phyxo\Functions\Language::l10n(
            'Manage permissions for user "%s"',
            \Phyxo\Functions\Utils::get_username($page['user'])
        ),
        'L_CAT_OPTIONS_TRUE' => \Phyxo\Functions\Language::l10n('Authorized'),
        'L_CAT_OPTIONS_FALSE' => \Phyxo\Functions\Language::l10n('Forbidden'),

        'F_ACTION' =>
            \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=user_perm&amp;user_id=' . $page['user']
    ]
);


// retrieve category ids authorized to the groups the user belongs to
$group_authorized = [];

$result = (new GroupAccessRepository($conn))->findCategoriesAuthorizedToUser($page['user']);
if ($conn->db_num_rows($result) > 0) {
    $cats = [];
    while ($row = $conn->db_fetch_assoc($result)) {
        $cats[] = $row;
        $group_authorized[] = $row['cat_id'];
    }
    usort($cats, '\Phyxo\Functions\Utils::global_rank_compare');

    foreach ($cats as $category) {
        $template->append(
            'categories_because_of_groups',
            \Phyxo\Functions\Category::get_cat_display_name_cache($category['uppercats'], null)
        );
    }
}

// only private categories are listed
$result = (new CategoryRepository($conn))->findWithUserAccess($page['user'], $group_authorized);
$categories = $conn->result2array($result);
\Phyxo\Functions\Category::display_select_cat_wrapper($categories, [], 'category_option_true');
$authorized_ids = [];
foreach ($categories as $category) {
    $authorized_ids[] = $category['id'];
}

$result = (new CategoryRepository($conn))->findUnauthorized(array_merge($authorized_ids, $group_authorized));
$categories = $conn->result2array($result);
\Phyxo\Functions\Category::display_select_cat_wrapper($categories, [], 'category_option_false');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
