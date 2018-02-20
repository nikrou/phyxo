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

if (!defined("GROUPS_BASE_URL")) {
    die ("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                              add a group                              |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit_add'])) {
    if (empty($_POST['groupname'])) {
        $page['errors'][] = l10n('The name of a group must not contain " or \' or be empty.');
    }
    if (count($page['errors']) == 0) {
        // is the group not already existing ?
        $query = 'SELECT COUNT(1) FROM '.GROUPS_TABLE;
        $query .= ' WHERE name = \''.$conn->db_real_escape_string($_POST['groupname']).'\'';
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count != 0) {
            $page['errors'][] = l10n('This name is already used by another group.');
        }
    }
    if (count($page['errors']) == 0) {
        // creating the group
        $query = 'INSERT INTO '.GROUPS_TABLE.' (name) VALUES(\''.$conn->db_real_escape_string($_POST['groupname']).'\');';
        $conn->db_query($query);

        $page['infos'][] = l10n('group "%s" added', $_POST['groupname']);
    }
}

// +-----------------------------------------------------------------------+
// |                             action send                               |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and isset($_POST['selectAction']) and isset($_POST['group_selection'])) {
    // if the user tries to apply an action, it means that there is at least 1
    // photo in the selection
    $groups = $_POST['group_selection'];
    if (count($groups) == 0) {
        $page['errors'][] = l10n('Select at least one group');
    }

    $action = $_POST['selectAction'];

    // +
    // |rename a group
    // +
    if ($action=="rename") {
        // is the group not already existing ?
        $query = 'SELECT name FROM '.GROUPS_TABLE;
        $group_names = $conn->query2array($query, null, 'name');
        foreach($groups as $group) {
            if (in_array($_POST['rename_'.$group.''], $group_names)) {
                $page['errors'][] = $_POST['rename_'.$group.''].' | '.l10n('This name is already used by another group.');
            } elseif ( !empty($_POST['rename_'.$group.''])) {
                $query = 'UPDATE '.GROUPS_TABLE;
                $query .= ' SET name = \''.$conn->db_real_escape_string($_POST['rename_'.$group.'']).'\'';
                $query .= ' WHERE id = '.$group;
                $conn->db_query($query);
            }
        }
    }

    // +
    // |delete a group
    // +
    if ($action=="delete" and isset($_POST['confirm_deletion']) and $_POST['confirm_deletion']) {
        foreach($groups as $group) {
            // destruction of the access linked to the group
            $query = 'DELETE FROM '.GROUP_ACCESS_TABLE.' WHERE group_id = '.$group;
            $conn->db_query($query);

            // destruction of the users links for this group
            $query = 'DELETE FROM '.USER_GROUP_TABLE.' WHERE group_id = '.$group;
            $conn->db_query($query);

            $query = 'SELECT name FROM '.GROUPS_TABLE.' WHERE id = '.$group;
            list($groupname) = $conn->db_fetch_row($conn->db_query($query));

            // destruction of the group
            $query = 'DELETE FROM '.GROUPS_TABLE.' WHERE id = '.$group;
            $conn->db_query($query);

            $page['infos'][] = l10n('group "%s" deleted', $groupname);
        }
    }

    // +
    // |merge groups into a new one
    // +
    if ($action=="merge" and count($groups) > 1) {
        // is the group not already existing ?
        $query = 'SELECT COUNT(1) FROM '.GROUPS_TABLE;
        $query .= ' WHERE name = \''.$conn->db_real_escape_string($_POST['merge']).'\'';
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count != 0) {
            $page['errors'][] = l10n('This name is already used by another group.');
        } else {
            // creating the group
            $query = 'INSERT INTO '.GROUPS_TABLE.' (name) VALUES(\''.$conn->db_real_escape_string($_POST['merge']).'\')';
            $conn->db_query($query);
            $query = 'SELECT id FROM '.GROUPS_TABLE.' WHERE name = \''.$conn->db_real_escape_string($_POST['merge']).'\'';
            list($groupid) = $conn->db_fetch_row($conn->db_query($query));
        }
        $grp_access = array();
        $usr_grp = array();
        foreach($groups as $group) {
            $query = 'SELECT * FROM '.GROUP_ACCESS_TABLE.' WHERE group_id = '.$group;
            $res = $conn->db_query($query);
            while ($row = $conn->db_fetch_assoc($res)) {
                $new_grp_access= array(
                    'cat_id' => $row['cat_id'],
                    'group_id' => $groupid
                );
                if (!in_array($new_grp_access,$grp_access)) {
                    $grp_access[]=$new_grp_access;
                }
            }

            $query = 'SELECT * FROM '.USER_GROUP_TABLE.' WHERE group_id = '.$group;
            $result = $conn->db_query($query);
            while ($row = $conn->db_fetch_assoc($result)) {
                $new_usr_grp= array(
                    'user_id' => $row['user_id'],
                    'group_id' => $groupid
                );
                if (!in_array($new_usr_grp,$usr_grp)) {
                    $usr_grp[]=$new_usr_grp;
                }
            }
        }
        $conn->mass_inserts(USER_GROUP_TABLE, array('user_id','group_id'), $usr_grp);
        $conn->mass_inserts(GROUP_ACCESS_TABLE, array('group_id','cat_id'), $grp_access);

        $page['infos'][] = l10n('group "%s" added', $_POST['merge']);
    }

    // +
    // |duplicate a group
    // +
    if ($action=="duplicate") {
        foreach($groups as $group) {
            if (empty($_POST['duplicate_'.$group.''])) {
                break;
            }
            // is the group not already existing ?
            $query = 'SELECT COUNT(1) FROM '.GROUPS_TABLE;
            $query .= ' WHERE name = \''.$conn->db_real_escape_string($_POST['duplicate_'.$group.'']);
            list($count) = $conn->db_fetch_row($conn->db_query($query));
            if ($count != 0) {
                $page['errors'][] = l10n('This name is already used by another group.');
                break;
            }
            // creating the group
            $query = 'INSERT INTO '.GROUPS_TABLE;
            $query .= ' (name) VALUES (\''.$conn->db_real_escape_string($_POST['duplicate_'.$group.'']).'\')';
            $conn->db_query($query);

            // @TODO: use last insert id
            $query = 'SELECT id FROM '.GROUPS_TABLE;
            $query .= ' WHERE name = \''.$conn->db_real_escape_string($_POST['duplicate_'.$group.'']).'\'';
            list($groupid) = $conn->db_fetch_row($conn->db_query($query));
            $query = 'SELECT * FROM '.GROUP_ACCESS_TABLE.' WHERE group_id = '.$group;
            $grp_access = array();
            $res = $conn->db_query($query);
            while ($row = $conn->db_fetch_assoc($res)) {
                $grp_access[] = array(
                    'cat_id' => $row['cat_id'],
                    'group_id' => $groupid
                );
            }
            $conn->mass_inserts(GROUP_ACCESS_TABLE, array('group_id','cat_id'), $grp_access);

            $query = 'SELECT * FROM '.USER_GROUP_TABLE.' WHERE group_id = '.$group;
            $usr_grp = array();
            $result = $conn->db_query($query);
            while ($row = $conn->db_fetch_assoc($result)) {
                $usr_grp[] = array(
                    'user_id' => $row['user_id'],
                    'group_id' => $groupid
                );
            }
            $conn->mass_inserts(USER_GROUP_TABLE, array('user_id','group_id'), $usr_grp);

            $page['infos'][] = l10n('group "%s" added', $_POST['duplicate_'.$group.'']);
        }
    }


    // +
    // | toggle_default
    // +
    if ($action=="toggle_default") {
        foreach($groups as $group) {
            $query = 'SELECT name, is_default FROM '.GROUPS_TABLE.' WHERE id = '.$group;
            $row = $conn->db_fetch_assoc($conn->db_query($query));
            $groupname = $row['name'];
            $is_default = $conn->get_boolean($row['is_default']);

            // update of the group
            $query = 'UPDATE '.GROUPS_TABLE;
            $query .= ' SET is_default = \''.$conn->boolean_to_db($is_default).'\'';
            $query .= ' WHERE id = '.$group;
            $conn->db_query($query);

            $page['infos'][] = l10n('group "%s" updated', $groupname);
        }
    }
    invalidate_user_cache();
}
// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->assign(
    array(
        'F_ADD_ACTION' => GROUPS_BASE_URL.'&amp;section=list',
        //'U_HELP' => get_root_url().'admin/popuphelp.php?page=group_list',
        'PWG_TOKEN' => get_pwg_token(),
    )
);

// +-----------------------------------------------------------------------+
// |                              group list                               |
// +-----------------------------------------------------------------------+

$query = 'SELECT id, name, is_default FROM '.GROUPS_TABLE.' ORDER BY name ASC';
$result = $conn->db_query($query);

$perm_url = GROUPS_BASE_URL.'&amp;section=perm&amp;group_id=';
$del_url = GROUPS_BASE_URL.'&amp;section=list&amp;delete=';
$toggle_is_default_url = GROUPS_BASE_URL.'&amp;section=list&amp;toggle_is_default=';

while ($row = $conn->db_fetch_assoc($result)) {
    $query = 'SELECT u.'. $conf['user_fields']['username'].' AS username FROM '.USERS_TABLE.' AS u';
    $query .= ' LEFT JOIN '.USER_GROUP_TABLE.' AS ug ON u.'.$conf['user_fields']['id'].' = ug.user_id';
    $query .= ' WHERE ug.group_id = '.$row['id'];
    $members = array();
    $user_result = $conn->db_query($query);
    while ($user = $conn->db_fetch_assoc($user_result)) {
        $members[] = $user['username'];
    }
    $template->append(
        'groups',
        array(
            'NAME' => $row['name'],
            'ID' => $row['id'],
            'IS_DEFAULT' => ($conn->get_boolean($row['is_default']) ? ' ['.l10n('default').']' : ''),
            'NB_MEMBERS' => count($members),
            'L_MEMBERS' => implode(' <span class="userSeparator">&middot;</span> ', $members),
            'MEMBERS' => l10n_dec('%d member', '%d members', count($members)),
            'U_DELETE' => $del_url.$row['id'].'&amp;pwg_token='.get_pwg_token(),
            'U_PERM' => $perm_url.$row['id'],
            'U_ISDEFAULT' => $toggle_is_default_url.$row['id'].'&amp;pwg_token='.get_pwg_token(),
        )
    );
}
