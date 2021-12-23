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

use App\Entity\Group as EntityGroup;
use App\Entity\User;
use Phyxo\Ws\Server;
use Phyxo\Ws\Error;

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
        $groups = [];
        $all_groups = $service->getManagerRegistry()->getRepository(EntityGroup::class)->findByNameOrGroupIds(
            $params['name'] ?? null,
            $params['group_id'] ?? [],
            $params['order'],
            $params['per_page'],
            $params['per_page'] * $params['page']
        );
        foreach ($all_groups as $group) {
            $groups[] = [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'is_default' => $group->isDefault(),
                'lastmodified' => $group->getLastModified(),
                'nb_users' => count($group->getUsers()),
            ];
        }

        return [
            'paging' => [
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($groups)
            ],
            'groups' => $groups,
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
        if ($service->getManagerRegistry()->getRepository(EntityGroup::class)->isGroupNameExists($params['name'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
        }

        // creating the group
        $group = new EntityGroup();
        $group->setName($params['name']);
        $group->setIsDefault($params['is_default'] === true);
        $group_id = $service->getManagerRegistry()->getRepository(EntityGroup::class)->addOrUpdateGroup($group);

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
        $groupnames = [];
        $service->getManagerRegistry()->getRepository(EntityGroup::class)->deleteByGroupIds($params['group_id']);
        $service->getUserMapper()->invalidateUserCache();

        return $groupnames;
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
        $group = $service->getManagerRegistry()->getRepository(EntityGroup::class)->find($params['group_id']);
        if (is_null($group)) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        if (!empty($params['name'])) {
            $group->setName($params['name']);
        }

        if (isset($params['is_default'])) {
            $group->setIsDefault($params['is_default'] === true);
        }

        try {
            $service->getManagerRegistry()->getRepository(EntityGroup::class)->addOrUpdateGroup($group);
        } catch (\Exception $e) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
        }

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
        $group = $service->getManagerRegistry()->getRepository(EntityGroup::class)->find($params['group_id']);
        if (is_null($group)) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        foreach ($service->getManagerRegistry()->getRepository(User::class)->findBy(['id' => $params['user_id']]) as $user) {
            $group->addUser($user);
        }

        $service->getManagerRegistry()->getRepository(EntityGroup::class)->addOrUpdateGroup($group);

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
        $group = $service->getManagerRegistry()->getRepository(EntityGroup::class)->find($params['group_id']);
        if (is_null($group)) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        foreach ($service->getManagerRegistry()->getRepository(User::class)->findBy(['id' => $params['user_id']]) as $user) {
            $group->removeUser($user);
        }

        $service->getManagerRegistry()->getRepository(EntityGroup::class)->addOrUpdateGroup($group);

        $service->getUserMapper()->invalidateUserCache();

        return $service->invoke('pwg.groups.getList', ['group_id' => $params['group_id']]);
    }
}
