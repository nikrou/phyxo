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
use App\Repository\GroupRepository;
use App\Repository\GroupAccessRepository;

if (!defined("GROUPS_BASE_URL")) {
    die("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                            variables init                             |
// +-----------------------------------------------------------------------+

if (isset($_GET['group_id']) and is_numeric($_GET['group_id'])) {
    $page['group'] = $_GET['group_id'];

    // +-----------------------------------------------------------------------+
    // |                                updates                                |
    // +-----------------------------------------------------------------------+

    if (isset($_POST['falsify'], $_POST['cat_true']) && count($_POST['cat_true']) > 0) {
        // if you forbid access to a category, all sub-categories become
        // automatically forbidden
        $subcats = (new CategoryRepository($conn))->getSubcatIds($_POST['cat_true']);
        (new GroupAccessRepository($conn))->deleteByGroupIdsAndCatIds($page['group'], $subcats);
    } elseif (isset($_POST['trueify'], $_POST['cat_false']) && count($_POST['cat_false']) > 0) {
        $uppercats = $categoryMapper->getUppercatIds($_POST['cat_false']);
        $private_uppercats = [];

        $result = (new CategoryRepository($conn))->findByIds($uppercats, 'private');
        while ($row = $conn->db_fetch_assoc($result)) {
            $private_uppercats[] = $row['id'];
        }

        // retrying to authorize a category which is already authorized may cause
        // an error (in SQL statement), so we need to know which categories are
        // accesible
        $authorized_ids = [];

        $result = (new GroupAccessRepository($conn))->findByGroupId($page['group']);
        while ($row = $conn->db_fetch_assoc($result)) {
            $authorized_ids[] = $row['cat_id'];
        }

        $inserts = [];
        $to_autorize_ids = array_diff($private_uppercats, $authorized_ids);
        foreach ($to_autorize_ids as $to_autorize_id) {
            $inserts[] = [
                'group_id' => $page['group'],
                'cat_id' => $to_autorize_id
            ];
        }

        (new GroupAccessRepository($conn))->massInserts(['group_id', 'cat_id'], $inserts);
        \Phyxo\Functions\Utils::invalidate_user_cache();
    }

    // +-----------------------------------------------------------------------+
    // |                             template init                             |
    // +-----------------------------------------------------------------------+

    $template->set_filenames(['double_select' => 'double_select.tpl']);

    $template->assign(
        [
            'TITLE' =>
            \Phyxo\Functions\Language::l10n(
            'Manage permissions for group "%s"',
            \Phyxo\Functions\Utils::get_groupname($page['group'])
            ),
            'L_CAT_OPTIONS_TRUE' => \Phyxo\Functions\Language::l10n('Authorized'),
            'L_CAT_OPTIONS_FALSE' => \Phyxo\Functions\Language::l10n('Forbidden'),
            'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
            'F_ACTION' => GROUPS_BASE_URL . '&amp;section=perm&amp;group_id=' . $page['group']
        ]
    );

    // only private categories are listed
    $result = (new CategoryRepository($conn))->findWithGroupAccess($page['group']);
    $categories = $conn->result2array($result);
    $template->assign($categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_option_true'));
    $authorized_ids = [];
    foreach ($categories as $category) {
        $authorized_ids[] = $category['id'];
    }

    $result = (new CategoryRepository($conn))->findUnauthorized($authorized_ids);
    $categories = $conn->result2array($result);
    $template->assign($categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_option_false'));

    // +-----------------------------------------------------------------------+
    // |                           html code display                           |
    // +-----------------------------------------------------------------------+

    $template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
} else {
    $perm_url = GROUPS_BASE_URL . '&amp;section=perm&amp;group_id=';

    $result = (new GroupRepository($conn))->findAll('ORDER BY name ASC');
    while ($row = $conn->db_fetch_assoc($result)) {
        $template->append(
            'groups',
            [
                'NAME' => $row['name'],
                'ID' => $row['id'],
                'IS_DEFAULT' => ($conn->get_boolean($row['is_default']) ? ' [' . \Phyxo\Functions\Language::l10n('default') . ']' : ''),
                'U_PERM' => $perm_url . $row['id'],
            ]
        );
    }
    $template->assign(['TITLE' => \Phyxo\Functions\Language::l10n('Groups')]);
}
