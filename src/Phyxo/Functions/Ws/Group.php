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
use App\Repository\GroupRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\UserGroupRepository;

class Group
{
    /**
     * API method
     * Returns the list of groups
     * @param mixed[] $params
     *    @option int[] group_id (optional)
     *    @option string name (optional)
     */
    public static function getList($params, Server $service)
    {
        global $conn;

        $result = (new GroupRepository($conn))->searchByName(
            $params['name'] ?? null,
            $params['group_id'] ?? [],
            $params['order'],
            $params['per_page'],
            $params['per_page'] * $params['page']
        );
        $groups = $conn->result2array($result);

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
    public static function add($params, Server $service)
    {
        global $conn;

        if ((new GroupRepository($conn))->isGroupNameExists($params['name'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
        }

        // creating the group
        $group_id = (new GroupRepository($conn))->addGroup(
            [
                'name' => $params['name'],
                'is_default' => $conn->boolean_to_string($params['is_default']),
            ]
        );

        return $service->invoke('pwg.groups.getList', ['group_id' => $group_id]);
    }

    /**
     * API method
     * Deletes a group
     * @param mixed[] $params
     *    @option int[] group_id
     *    @option string pwg_token
     */
    public static function delete($params, Server $service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        // destruction of the access linked to the group
        (new GroupAccessRepository($conn))->deleteByGroupIds($params['group_id']);

        // destruction of the users links for this group
        (new UserGroupRepository($conn))->deleteByGroupIds($params['group_id']);

        $result = (new GroupRepository($conn))->findByIds($params['group_id']);
        $groupnames = $conn->result2array($result, null, 'name');

        // destruction of the group
        (new GroupRepository($conn))->deleteByIds($params['group_id']);

        $service->getUserMapper()->invalidateUserCache();

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
    public static function setInfo($params, Server $service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $updates = [];

        if (!(new GroupRepository($conn))->isGroupIdExists($params['group_id'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        if (!empty($params['name'])) {
            if ((new GroupRepository($conn))->isGroupNameExists($params['name'])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
            }

            $updates['name'] = $params['name'];
        }

        if (!empty($params['is_default'])) {
            $updates['is_default'] = $params['is_default'];
        }

        (new GroupRepository($conn))->updateGroup($updates, $params['group_id']);

        return $service->invoke('pwg.groups.getList', ['group_id' => $params['group_id']]);
    }

    /**
     * API method
     * Adds user(s) to a group
     * @param mixed[] $params
     *    @option int group_id
     *    @option int[] user_id
     */
    public static function addUser($params, Server $service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!(new GroupRepository($conn))->isGroupIdExists($params['group_id'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        $inserts = [];
        foreach ($params['user_id'] as $user_id) {
            $inserts[] = [
                'group_id' => $params['group_id'],
                'user_id' => $user_id,
            ];
        }

        (new UserGroupRepository($conn))->massInserts(['group_id', 'user_id'], $inserts);

        $service->getUserMapper()->invalidateUserCache();

        return $service->invoke('pwg.groups.getList', ['group_id' => $params['group_id']]);
    }

    /**
     * API method
     * Removes user(s) from a group
     * @param mixed[] $params
     *    @option int group_id
     *    @option int[] user_id
     */
    public static function deleteUser($params, Server $service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!(new GroupRepository($conn))->isGroupIdExists($params['group_id'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        (new UserGroupRepository($conn))->delete($params['group_id'], $params['user_id']);

        $service->getUserMapper()->invalidateUserCache();

        return $service->invoke('pwg.groups.getList', ['group_id' => $params['group_id']]);
    }
}
