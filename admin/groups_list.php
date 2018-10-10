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

use App\Repository\GroupRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\UserGroupRepository;

if (!defined("GROUPS_BASE_URL")) {
    die("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                              add a group                              |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit_add'])) {
    if (empty($_POST['groupname'])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('The name of a group must not contain " or \' or be empty.');
    }
    if (count($page['errors']) == 0) {
        if ((new GroupRepository($conn))->isGroupNameExists($_POST['groupname'])) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('This name is already used by another group.');
        }
    }
    if (count($page['errors']) == 0) {
        (new GroupRepository($conn))->addGroup($_POST['groupname']);

        $page['infos'][] = \Phyxo\Functions\Language::l10n('group "%s" added', $_POST['groupname']);
    }
}

// +-----------------------------------------------------------------------+
// |                             action send                               |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and isset($_POST['selectAction']) and isset($_POST['group_selection'])) {
    // if the user tries to apply an action, it means that there is at least 1 photo in the selection
    $groups = $_POST['group_selection'];
    if (count($groups) == 0) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one group');
    }

    $action = $_POST['selectAction'];

    // +
    // |rename a group
    // +
    if ($action == "rename") {
        // is the group not already existing ?
        $result = (new GroupRepository($conn))->findAll();
        $group_names = $conn->result2array($result, null, 'name');
        foreach ($groups as $group) {
            if (in_array($_POST['rename_' . $group . ''], $group_names)) {
                $page['errors'][] = $_POST['rename_' . $group . ''] . ' | ' . \Phyxo\Functions\Language::l10n('This name is already used by another group.');
            } elseif (!empty($_POST['rename_' . $group])) {
                (new GroupRepository($conn))->updateGroup(['name' => $_POST['rename_' . $group]], $group);
            }
        }
    }

    // +
    // |delete a group
    // +
    if ($action == "delete" and isset($_POST['confirm_deletion']) and $_POST['confirm_deletion']) {
        // destruction of the access linked to the group
        (new GroupAccessRepository($conn))->deleteByGroupIds($groups);

        // destruction of the users links for this group
        (new UserGroupRepository($conn))->deleteGroupByIds($groups);

        $result = (new GroupRepository($conn))->findByIds($params['group_id']);
        $groupnames = $conn->result2array($result, null, 'name');

        // destruction of the group
        (new GroupRepository($conn))->deleteByIds($params['group_id']);

        $page['infos'][] = \Phyxo\Functions\Language::l10n('groups "%s" deleted', implode(', ', $groupnames));
    }

    // +
    // |merge groups into a new one
    // +
    if ($action == "merge" and count($groups) > 1) {
        if ((new GroupRepository($conn))->isGroupNameExists($_POST['merge'])) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('This name is already used by another group.');
        } else {
            $group_id = (new GroupRepository($conn))->addGroup(['name' => $_POST['merge']]);
        }

        $grp_access = [];
        $usr_grp = [];
        $result = (new GroupAccessRepository($conn))->findByGroupIds($groups);
        $groups_infos = $conn->result2array($result);
        foreach ($groups_infos as $group) {
            $new_grp_access = [
                'cat_id' => $group['cat_id'],
                'group_id' => $group_id
            ];
            if (!in_array($new_grp_access, $grp_access)) {
                $grp_access[] = $new_grp_access;
            }
        }

        $result = (new GroupAccessRepository($conn))->findByGroupIds($groups);
        $groups_infos = $conn->result2array($result);
        foreach ($groups_infos as $group) {
            $new_grp_access = [
                'cat_id' => $group['cat_id'],
                'group_id' => $group_id
            ];
            if (!in_array($new_grp_access, $grp_access)) {
                $grp_access[] = $new_grp_access;
            }
        }

        (new UserGroupRepository($conn))->massInserts(['user_id', 'group_id'], $usr_grp);
        (new GroupAccessRepository($conn))->massInserts(['group_id', 'cat_id'], $grp_access);

        $page['infos'][] = \Phyxo\Functions\Language::l10n('group "%s" added', $_POST['merge']);
    }

    // +
    // |duplicate a group
    // +
    if ($action == "duplicate") {
        // @TODO: avoid query in loop
        foreach ($groups as $group) {
            if (empty($_POST['duplicate_' . $group])) {
                break;
            }

            if ((new GroupRepository($conn))->isGroupNameExists($_POST['duplicate_' . $group])) {
                $page['errors'][] = \Phyxo\Functions\Language::l10n('This name is already used by another group.');
                break;
            }

            $group_id = (new GroupRepository($conn))->addGroup(['name' => $_POST['duplicate_' . $group]]);

            $grp_access = [];
            $result = (new GroupAccessRepository($conn))->findByGroupId($group);
            while ($row = $conn->db_fetch_assoc($result)) {
                $grp_access[] = [
                    'cat_id' => $row['cat_id'],
                    'group_id' => $groupid
                ];
            }
            (new GroupAccessRepository($conn))->massInserts(['group_id', 'cat_id'], $grp_access);

            $usr_grp = [];
            $result = (new UserGroupRepository($conn))->findByGroupId($group);
            while ($row = $conn->db_fetch_assoc($result)) {
                $usr_grp[] = [
                    'user_id' => $row['user_id'],
                    'group_id' => $groupid
                ];
            }
            (new UserGroupRepository($conn))->massInserts(['user_id', 'group_id'], $usr_grp);

            $page['infos'][] = \Phyxo\Functions\Language::l10n('group "%s" added', $_POST['duplicate_' . $group]);
        }
    }


    // +
    // | toggle_default
    // +
    if ($action == "toggle_default") {
        // @TODO: strange idea to have multiple default groups
        (new GroupRepository($conn))->toggleIsDefault($groups);

        $page['infos'][] = \Phyxo\Functions\Language::l10n('groups "%s" updated', implode(', ', $groups));
    }
    \Phyxo\Functions\Utils::invalidate_user_cache();
}
// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->assign(
    [
        'F_ADD_ACTION' => GROUPS_BASE_URL . '&amp;section=list',
        //'U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=group_list',
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    ]
);

// +-----------------------------------------------------------------------+
// |                              group list                               |
// +-----------------------------------------------------------------------+

$perm_url = GROUPS_BASE_URL . '&amp;section=perm&amp;group_id=';
$del_url = GROUPS_BASE_URL . '&amp;section=list&amp;delete=';
$toggle_is_default_url = GROUPS_BASE_URL . '&amp;section=list&amp;toggle_is_default=';

$groups = [];
$result = (new GroupRepository($conn))->findUsersInGroups();
while ($row = $conn->db_fetch_assoc($result)) {
    if (isset($groups[$row['id']])) {
        if (!empty($row['username'])) {
            $groups[$row['id']]['MEMBERS'][] = $row['username'];
        }
    } else {
        $group = [
            'MEMBERS' => [],
            'ID' => $row['id'],
            'NAME' => $row['name'],
            'IS_DEFAULT' => ($conn->get_boolean($row['is_default']) ? ' [' . \Phyxo\Functions\Language::l10n('default') . ']' : ''),
            'U_DELETE' => $del_url . $row['id'] . '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token(),
            'U_PERM' => $perm_url . $row['id'],
            'U_ISDEFAULT' => $toggle_is_default_url . $row['id'] . '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token(),
        ];
        if (!empty($row['username'])) {
            $group['MEMBERS'][] = $row['username'];
        }
        $groups[$row['id']] = $group;
    }
}

$template->assign('groups', $groups);
