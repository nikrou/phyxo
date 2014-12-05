<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined("PHPWG_ROOT_PATH")) {
    die("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

$my_base_url = get_root_url().'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('groups');
$tabsheet->select('group_list');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

if (!empty($_POST) or isset($_GET['delete']) or isset($_GET['toggle_is_default'])) {
    check_pwg_token();
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

$template->set_filenames(array('group_list' => 'group_list.tpl'));

$template->assign(
    array(
        'F_ADD_ACTION' => get_root_url().'admin.php?page=group_list',
        'U_HELP' => get_root_url().'admin/popuphelp.php?page=group_list',
        'PWG_TOKEN' => get_pwg_token(),
    )
);

// +-----------------------------------------------------------------------+
// |                              group list                               |
// +-----------------------------------------------------------------------+

$query = 'SELECT id, name, is_default FROM '.GROUPS_TABLE.' ORDER BY name ASC';
$result = $conn->db_query($query);

$admin_url = get_root_url().'admin.php?page=';
$perm_url = $admin_url.'group_perm&amp;group_id=';
$del_url = $admin_url.'group_list&amp;delete=';
$toggle_is_default_url = $admin_url.'group_list&amp;toggle_is_default=';

while ($row = $conn->db_fetch_assoc($result)) {
    $query = 'SELECT u.'. $conf['user_fields']['username'].' AS username FROM '.USERS_TABLE.' AS u';
    $query .= ' LEFT JOIN '.USER_GROUP_TABLE.' AS ug ON u.'.$conf['user_fields']['id'].' = ug.user_id';
    $query .= ' WHERE ug.group_id = '.$row['id'];
    $members = array();
    $result = $conn->db_query($query);
    while ($user = $conn->db_fetch_assoc($result)) {
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

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'group_list');
