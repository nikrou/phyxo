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

/**
 * API method
 * Returns the list of groups
 * @param mixed[] $params
 *    @option int[] group_id (optional)
 *    @option string name (optional)
 */
function ws_groups_getList($params, &$service) {
    global $conn;

    $where_clauses = array('1=1');

    if (!empty($params['name'])) {
        $where_clauses[] = 'LOWER(name) LIKE \''. pwg_db_real_escape_string($params['name']) .'\'';
    }

    if (!empty($params['group_id'])) {
        $where_clauses[] = 'id '.$conn->in($params['group_id']);
    }

    $query = 'SELECT g.*, COUNT(user_id) AS nb_users FROM '. GROUPS_TABLE .' AS g';
    $query .= ' LEFT JOIN '. USER_GROUP_TABLE .' AS ug ON ug.group_id = g.id';
    $query .= ' WHERE '. implode(' AND ', $where_clauses);
    $query .= ' GROUP BY id';
    $query .= ' ORDER BY '. $params['order'];
    $query .= ' LIMIT '. (int) $params['per_page'] .' OFFSET '. (int) ($params['per_page']*$params['page']) .';';

    $groups = $conn->query2array($query);

    return array(
        'paging' => new PwgNamedStruct(array(
            'page' => $params['page'],
            'per_page' => $params['per_page'],
            'count' => count($groups)
        )),
        'groups' => new PwgNamedArray($groups, 'group')
    );
}

/**
 * API method
 * Adds a group
 * @param mixed[] $params
 *    @option string name
 *    @option bool is_default
 */
function ws_groups_add($params, &$service) {
    global $conn;

    // is the name not already used ?
    $query = 'SELECT COUNT(1) FROM '.GROUPS_TABLE;
    $query .= ' WHERE name = \''.$conn->db_real_escape_string($params['name']).'\'';
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count != 0) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
    }

    // creating the group
    $conn->single_insert(
        GROUPS_TABLE,
        array(
            'name' => $params['name'],
            'is_default' => boolean_to_string($params['is_default']),
        )
    );

    return $service->invoke('pwg.groups.getList', array('group_id' => $conn->db_insert_id()));
}

/**
 * API method
 * Deletes a group
 * @param mixed[] $params
 *    @option int[] group_id
 *    @option string pwg_token
 */
function ws_groups_delete($params, &$service) {
    global $conn;

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    // destruction of the access linked to the group
    $query = 'DELETE FROM '. GROUP_ACCESS_TABLE .' WHERE group_id '.$conn->in($params['group_id']);
    $conn->db_query($query);

    // destruction of the users links for this group
    $query = 'DELETE FROM '. USER_GROUP_TABLE .' WHERE group_id '.$conn->in($params['group_id']);
    $conn->db_query($query);

    $query = 'SELECT name FROM '. GROUPS_TABLE .' WHERE id '.$conn->in($params['group_id']);
    $groupnames = array_from_query($query, 'name');

    // destruction of the group
    $query = 'DELETE FROM '. GROUPS_TABLE .' WHERE id '.$conn->in($params['group_id']);
    $conn->db_query($query);

    include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
    invalidate_user_cache();

    return new PwgNamedArray($groupnames, 'group_deleted');
}

/**
 * API method
 * Updates a group
 * @param mixed[] $params
 *    @option int group_id
 *    @option string name (optional)
 *    @option bool is_default (optional)
 */
function ws_groups_setInfo($params, &$service) {
    global $conn;

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    $updates = array();

    // does the group exist ?
    $query = 'SELECT COUNT(1) '. GROUPS_TABLE .' WHERE id = '. $conn->db_real_escape_string($params['group_id']);
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count == 0) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This group does not exist.');
    }

    if (!empty($params['name'])) {
        // is the name not already used ?
        $query = 'SELECT COUNT(1) FROM '. GROUPS_TABLE;
        $query .= ' WHERE name = \''. $conn->db_real_escape_string($params['name']).'\'';
        list($count) = pwg_db_fetch_row($conn->db_query($query));
        if ($count != 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
        }

        $updates['name'] = $params['name'];
    }

    if (!empty($params['is_default']) or @$params['is_default']===false) {
        $updates['is_default'] = boolean_to_string($params['is_default']);
    }

    $conn->single_update(
        GROUPS_TABLE,
        $updates,
        array('id' => $params['group_id'])
    );

    return $service->invoke('pwg.groups.getList', array('group_id' => $params['group_id']));
}

/**
 * API method
 * Adds user(s) to a group
 * @param mixed[] $params
 *    @option int group_id
 *    @option int[] user_id
 */
function ws_groups_addUser($params, &$service) {
    global $conn;

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    // does the group exist ?
    $query = 'SELECT COUNT(1) FROM '. GROUPS_TABLE;
    $query .= ' WHERE id = '.$conn->db_real_escape_string($params['group_id']);
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count == 0) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This group does not exist.');
    }

    $inserts = array();
    foreach ($params['user_id'] as $user_id) {
        $inserts[] = array(
            'group_id' => $params['group_id'],
            'user_id' => $user_id,
        );
    }

    $conn->mass_inserts(
        USER_GROUP_TABLE,
        array('group_id', 'user_id'),
        $inserts
    );

    include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
    invalidate_user_cache();

    return $service->invoke('pwg.groups.getList', array('group_id' => $params['group_id']));
}

/**
 * API method
 * Removes user(s) from a group
 * @param mixed[] $params
 *    @option int group_id
 *    @option int[] user_id
 */
function ws_groups_deleteUser($params, &$service) {
    global $conn;

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    // does the group exist ?
    $query = 'SELECT COUNT(1) FROM '.GROUPS_TABLE;
    $query .= ' WHERE id = '.$conn->db_real_escape_string($params['group_id']);
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count == 0) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This group does not exist.');
    }

    $query = 'DELETE FROM '. USER_GROUP_TABLE;
    $query .= ' WHERE group_id = '.$conn->db_real_escape_string($params['group_id']);
    $query .= ' AND user_id '.$conn->in($params['user_id']);
    $conn->db_query($query);

    include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
    invalidate_user_cache();

    return $service->invoke('pwg.groups.getList', array('group_id' => $params['group_id']));
}
