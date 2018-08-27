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

namespace Phyxo\Functions\Ws;

use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use Phyxo\Ws\NamedStruct;
use Phyxo\Ws\NamedArray;

class Group
{
    /**
     * API method
     * Returns the list of groups
     * @param mixed[] $params
     *    @option int[] group_id (optional)
     *    @option string name (optional)
     */
    public static function getList($params, &$service)
    {
        global $conn;

        $where_clauses = ['1=1'];

        if (!empty($params['name'])) {
            $where_clauses[] = 'LOWER(name) LIKE \'' . $conn->db_real_escape_string($params['name']) . '\'';
        }

        if (!empty($params['group_id'])) {
            $where_clauses[] = 'id ' . $conn->in($params['group_id']);
        }

        $query = 'SELECT g.*, COUNT(user_id) AS nb_users FROM ' . GROUPS_TABLE . ' AS g';
        $query .= ' LEFT JOIN ' . USER_GROUP_TABLE . ' AS ug ON ug.group_id = g.id';
        $query .= ' WHERE ' . implode(' AND ', $where_clauses);
        $query .= ' GROUP BY id';
        $query .= ' ORDER BY ' . $params['order'];
        $query .= ' LIMIT ' . (int)$params['per_page'] . ' OFFSET ' . (int)($params['per_page'] * $params['page']) . ';';

        $groups = $conn->query2array($query);

        return [
            'paging' => new NamedStruct([
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($groups)
            ]),
            'groups' => new NamedArray($groups, 'group')
        ];
    }

    /**
     * API method
     * Adds a group
     * @param mixed[] $params
     *    @option string name
     *    @option bool is_default
     */
    public static function add($params, &$service)
    {
        global $conn;

        // is the name not already used ?
        $query = 'SELECT COUNT(1) FROM ' . GROUPS_TABLE;
        $query .= ' WHERE name = \'' . $conn->db_real_escape_string($params['name']) . '\'';
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count != 0) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
        }

        // creating the group
        $conn->single_insert(
            GROUPS_TABLE,
            [
                'name' => $params['name'],
                'is_default' => $conn->boolean_to_string($params['is_default']),
            ]
        );

        return $service->invoke('pwg.groups.getList', ['group_id' => $conn->db_insert_id()]);
    }

    /**
     * API method
     * Deletes a group
     * @param mixed[] $params
     *    @option int[] group_id
     *    @option string pwg_token
     */
    public static function delete($params, &$service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        // destruction of the access linked to the group
        $query = 'DELETE FROM ' . GROUP_ACCESS_TABLE . ' WHERE group_id ' . $conn->in($params['group_id']);
        $conn->db_query($query);

        // destruction of the users links for this group
        $query = 'DELETE FROM ' . USER_GROUP_TABLE . ' WHERE group_id ' . $conn->in($params['group_id']);
        $conn->db_query($query);

        $query = 'SELECT name FROM ' . GROUPS_TABLE . ' WHERE id ' . $conn->in($params['group_id']);
        $groupnames = $conn->query2array($query, null, 'name');

        // destruction of the group
        $query = 'DELETE FROM ' . GROUPS_TABLE . ' WHERE id ' . $conn->in($params['group_id']);
        $conn->db_query($query);

        \Phyxo\Functions\Utils::invalidate_user_cache();

        return new NamedArray($groupnames, 'group_deleted');
    }

    /**
     * API method
     * Updates a group
     * @param mixed[] $params
     *    @option int group_id
     *    @option string name (optional)
     *    @option bool is_default (optional)
     */
    public static function setInfo($params, &$service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $updates = [];

        // does the group exist ?
        $query = 'SELECT COUNT(1) ' . GROUPS_TABLE . ' WHERE id = ' . $conn->db_real_escape_string($params['group_id']);
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count == 0) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        if (!empty($params['name'])) {
            // is the name not already used ?
            $query = 'SELECT COUNT(1) FROM ' . GROUPS_TABLE;
            $query .= ' WHERE name = \'' . $conn->db_real_escape_string($params['name']) . '\'';
            list($count) = $conn->db_fetch_row($conn->db_query($query));
            if ($count != 0) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
            }

            $updates['name'] = $params['name'];
        }

        if (!empty($params['is_default']) or @$params['is_default'] === false) {
            $updates['is_default'] = $conn->boolean_to_string($params['is_default']);
        }

        $conn->single_update(
            GROUPS_TABLE,
            $updates,
            ['id' => $params['group_id']]
        );

        return $service->invoke('pwg.groups.getList', ['group_id' => $params['group_id']]);
    }

    /**
     * API method
     * Adds user(s) to a group
     * @param mixed[] $params
     *    @option int group_id
     *    @option int[] user_id
     */
    public static function addUser($params, &$service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        // does the group exist ?
        $query = 'SELECT COUNT(1) FROM ' . GROUPS_TABLE;
        $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['group_id']);
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count == 0) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        $inserts = [];
        foreach ($params['user_id'] as $user_id) {
            $inserts[] = [
                'group_id' => $params['group_id'],
                'user_id' => $user_id,
            ];
        }

        $conn->mass_inserts(
            USER_GROUP_TABLE,
            ['group_id', 'user_id'],
            $inserts
        );

        \Phyxo\Functions\Utils::invalidate_user_cache();

        return $service->invoke('pwg.groups.getList', ['group_id' => $params['group_id']]);
    }

    /**
     * API method
     * Removes user(s) from a group
     * @param mixed[] $params
     *    @option int group_id
     *    @option int[] user_id
     */
    public static function deleteUser($params, &$service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        // does the group exist ?
        $query = 'SELECT COUNT(1) FROM ' . GROUPS_TABLE;
        $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['group_id']);
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count == 0) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        $query = 'DELETE FROM ' . USER_GROUP_TABLE;
        $query .= ' WHERE group_id = ' . $conn->db_real_escape_string($params['group_id']);
        $query .= ' AND user_id ' . $conn->in($params['user_id']);
        $conn->db_query($query);

        \Phyxo\Functions\Utils::invalidate_user_cache();

        return $service->invoke('pwg.groups.getList', ['group_id' => $params['group_id']]);
    }
}
