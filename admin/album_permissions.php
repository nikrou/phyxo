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
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use App\Repository\UserAccessRepository;

if (!defined('ALBUM_BASE_URL')) {
    die("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                       variable initialization                         |
// +-----------------------------------------------------------------------+

$page['cat'] = $category['id'];

// +-----------------------------------------------------------------------+
// |                           form submission                             |
// +-----------------------------------------------------------------------+

if (!empty($_POST)) {
    \Phyxo\Functions\Utils::check_token();

    if ($category['status'] != $_POST['status']) {
        $categoryMapper->setCatStatus([$page['cat']], $_POST['status']);
        $category['status'] = $_POST['status'];
    }

    if ('private' == $_POST['status']) {
        //
        // manage groups
        //
        $result = (new GroupAccessRepository($conn))->findByCatId($page['cat']);
        $groups_granted = $conn->result2array($result, null, 'group_id');

        if (!isset($_POST['groups'])) {
            $_POST['groups'] = [];
        }

        //
        // remove permissions to groups
        //
        $deny_groups = array_diff($groups_granted, $_POST['groups']);
        if (count($deny_groups) > 0) {
            // if you forbid access to an album, all sub-albums become
            // automatically forbidden
            (new GroupAccessRepository($conn))->deleteByGroupIdsAndCatIds($deny_groups, (new CategoryRepository($conn))->getSubcatIds([$page['cat']]));
        }

        //
        // add permissions to groups
        //
        $grant_groups = $_POST['groups'];
        if (count($grant_groups) > 0) {
            $cat_ids = $categoryMapper->getUppercatIds([$page['cat']]);
            if (isset($_POST['apply_on_sub'])) {
                $cat_ids = array_merge($cat_ids, (new CategoryRepository($conn))->getSubcatIds([$page['cat']]));
            }

            $result = (new CategoryRepository($conn))->findByIds($cat_ids, 'private');
            $private_cats = $conn->result2array($result, null, 'id');

            $inserts = [];
            foreach ($private_cats as $cat_id) {
                foreach ($grant_groups as $group_id) {
                    $inserts[] = [
                        'group_id' => $group_id,
                        'cat_id' => $cat_id
                    ];
                }
            }

            (new GroupAccessRepository($conn))->massInserts(['group_id', 'cat_id'], $inserts, ['ignore' => true]);
        }

        //
        // users
        //
        $result = (new UserAccessRepository($conn))->findByCatId($page['cat']);
        $users_granted = $conn->result2array($result, null, 'user_id');

        if (!isset($_POST['users'])) {
            $_POST['users'] = [];
        }

        //
        // remove permissions to users
        //
        $deny_users = array_diff($users_granted, $_POST['users']);
        if (count($deny_users) > 0) {
            // if you forbid access to an album, all sub-album become automatically forbidden
            (new UserAccessRepository($conn))->deleteByUserIdsAndCatIds($deny_users, (new CategoryRepository($conn))->getSubcatIds([$page['cat']]));
        }

        //
        // add permissions to users
        //
        $grant_users = $_POST['users'];
        if (count($grant_users) > 0) {
            $categoryMapper->addPermissionOnCategory($page['cat'], $grant_users);
        }
    }

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Album updated successfully');
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->assign(
    [
        'CATEGORIES_NAV' => $categoryMapper->getCatDisplayNameFromId($page['cat'], 'admin/index.php?page=album&amp;cat_id='),
        'F_ACTION' => ALBUM_BASE_URL . '&amp;section=permissions',
        'private' => ('private' == $category['status']),
    ]
);

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

// groups denied are the groups not granted. So we need to find all groups
// minus groups granted to find groups denied.

$groups = [];

$result = (new GroupRepository($conn))->findAll('ORDER BY name ASC');
$groups = $conn->result2array($result, 'id', 'name');
$template->assign('groups', $groups);

// groups granted to access the category
$result = (new GroupAccessRepository($conn))->findByCatId($page['cat']);
$group_granted_ids = $conn->result2array($result, null, 'group_id');
$template->assign('groups_selected', $group_granted_ids);

// users...
$result = (new UserRepository($conn))->findAll();
$users = $conn->result2array($result, 'id', 'username');
$template->assign('users', $users);

(new UserAccessRepository($conn))->findByCatId($page['cat']);
$user_granted_direct_ids = $conn->result2array($result, null, 'user_id');
$template->assign('users_selected', $user_granted_direct_ids);

$user_granted_indirect_ids = [];
if (count($group_granted_ids) > 0) {
    $granted_groups = [];

    $result = (new UserGroupRepository($conn))->findByGroupIds($group_granted_ids);
    while ($row = $conn->db_fetch_assoc($result)) {
        if (!isset($granted_groups[$row['group_id']])) {
            $granted_groups[$row['group_id']] = [];
        }
        $granted_groups[$row['group_id']][] = $row['user_id'];
    }

    $user_granted_by_group_ids = [];

    foreach ($granted_groups as $group_users) {
        $user_granted_by_group_ids = array_merge($user_granted_by_group_ids, $group_users);
    }

    $user_granted_by_group_ids = array_unique($user_granted_by_group_ids);

    $user_granted_indirect_ids = array_diff(
        $user_granted_by_group_ids,
        $user_granted_direct_ids
    );

    $template->assign('nb_users_granted_indirect', count($user_granted_indirect_ids));
    foreach ($granted_groups as $group_id => $group_users) {
        $group_usernames = [];
        foreach ($group_users as $user_id) {
            if (in_array($user_id, $user_granted_indirect_ids)) {
                $group_usernames[] = $users[$user_id];
            }
        }

        $template->append(
            'user_granted_indirect_groups',
            [
                'group_name' => $groups[$group_id],
                'group_users' => implode(', ', $group_usernames),
            ]
        );
    }
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+
$template->assign([
    'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    'INHERIT' => $conf['inheritance_by_default'],
    'CACHE_KEYS' => \Phyxo\Functions\Utils::get_admin_client_cache_keys(['groups', 'users']),
]);
