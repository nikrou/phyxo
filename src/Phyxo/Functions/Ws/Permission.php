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

use App\Entity\Album;
use App\Entity\Group;
use App\Entity\User;
use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use Phyxo\Ws\NamedArray;

class Permission
{
    /**
     * API method
     * Returns permissions
     * @param mixed[] $params
     *    @option int[] cat_id (optional)
     *    @option int[] group_id (optional)
     *    @option int[] user_id (optional)
     */
    public static function getList($params, Server $service)
    {
        $my_params = array_intersect(array_keys($params), ['cat_id', 'group_id', 'user_id']);
        if (count($my_params) > 1) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'Too many parameters, provide cat_id OR user_id OR group_id');
        }

        $albums = [];
        if (!empty($params['cat_id'])) {
            if (is_array($params['cat_id'])) {
                foreach ($service->getAlbumMapper()->getRepository()->findBy(['id' => $params['cat_id']]) as $album) {
                    $albums[] = $album;
                }
            } else {
                $albums[] = $service->getAlbumMapper()->getRepository()->find($params['cat_id']);
            }
        } else {
            foreach ($service->getAlbumMapper()->getRepository()->findAll() as $album) {
                $albums[] = $album;
            }
        }

        $permissions = [];

        foreach ($albums as $album) {
            if (!isset($permissions[$album->getId()])) {
                $permissions[$album->getId()]['id'] = $album->getId();
            }

            // direct users
            foreach ($album->getUserAccess() as $user) {
                $permissions[$album->getId()]['users'][] = $user->getId();
            }

            // indirect users
            $album_ids = [];
            if (!empty($params['cat_id'])) {
                if (is_array($params['cat_id'])) {
                    $album_ids = $params['cat_id'];
                } else {
                    $album_ids = [$params['cat_id']];
                }
            }
            foreach ($service->getUserMapper()->getRepository()->findWithAlbumsAccess($album_ids) as $user) {
                $permissions[$album->getId()]['users_indirect'][] = $user->getId();
            }

            // groups
            foreach ($album->getGroupAccess() as $group) {
                $permissions[$album->getId()]['groups'][] = $group->getId();
            }
        }

        // filter by group and user
        foreach ($permissions as $album_id => &$album) {
            if (!empty($params['group_id'])) {
                if (empty($album['groups']) || count(array_intersect($album['groups'], $params['group_id'])) === 0) {
                    unset($permissions[$album_id]);
                    continue;
                }
            }
            if (!empty($params['user_id'])) {
                if ((empty($albul['users_indirect']) || count(array_intersect($album['users_indirect'], $params['user_id'])) === 0)
                    && (empty($album['users']) || count(array_intersect($album['users'], $params['user_id'])) === 0)) {
                    unset($permissions[$album_id]);
                    continue;
                }
            }

            $album['groups'] = !empty($album['groups']) ? array_values(array_unique($album['groups'])) : [];
            $album['users'] = !empty($album['users']) ? array_values(array_unique($album['users'])) : [];
            $album['users_indirect'] = !empty($album['users_indirect']) ? array_values(array_unique($album['users_indirect'])) : [];
        }

        return [
            'categories' => new NamedArray(
                array_values($permissions),
                'category',
                ['id']
            )
        ];
    }

    /**
     * API method
     * Add permissions
     * @param mixed[] $params
     *    @option int[] cat_id
     *    @option int[] group_id (optional)
     *    @option int[] user_id (optional)
     *    @option bool recursive
     */
    public static function add($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!empty($params['group_id']) || !empty($params['user_id'])) {
            $album_ids = $service->getAlbumMapper()->getUppercatIds($params['cat_id']);
            if ($params['recursive']) {
                $album_ids = array_merge($album_ids, $service->getAlbumMapper()->getRepository()->getSubcatIds($params['cat_id']));
            }

            $groups = [];
            foreach ($service->getManagerRegistry()->getRepository(Group::class)->findAll() as $group) {
                $groups[] = $group;
            }

            $users = [];
            foreach ($service->getManagerRegistry()->getRepository(User::class)->findAll() as $user) {
                $users[] = $user;
            }

            foreach ($service->getAlbumMapper()->findByIdsAndStatus($album_ids, Album::STATUS_PRIVATE) as $album) {
                $need_update = false;
                if (count($groups) > 0) {
                    $need_update = true;
                    foreach ($groups as $group) {
                        $album->addGroupAccess($group);
                    }
                }

                if (count($users) > 0) {
                    $need_update = true;
                    foreach ($users as $user) {
                        $album->addUserAcccess($user);
                    }
                }

                if ($need_update) {
                    $service->getAlbumMapper()->getRepository()->addOrUpdateAlbum($album);
                }
            }
        }

        return $service->invoke('pwg.permissions.getList', ['cat_id' => $params['cat_id']]);
    }

    /**
     * API method
     * Removes permissions
     * @param mixed[] $params
     *    @option int[] cat_id
     *    @option int[] group_id (optional)
     *    @option int[] user_id (optional)
     */
    public static function remove($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $album = $service->getAlbumMapper()->getRepository()->find($params['cat_id']);

        if (!empty($params['group_id'])) {
            if (is_array($params['group_id'])) {
                foreach ($service->getManagerRegistry()->getRepository(Group::class)->findBy(['id' => $params['group_id']]) as $group) {
                    $album->removeGroupAccess($group);
                }
            } else {
                $group = $service->getManagerRegistry()->getRepository(Group::class)->find($params['group_id']);
                $album->removeGroupAccess($group);
            }
        } else {
            $album->removeAllGroupAccess();
        }

        if (!empty($params['user_id'])) {
            if (is_array($params['user_id'])) {
                foreach ($service->getUserMapper()->getRepository()->findBy(['id' => $params['user_id']]) as $user) {
                    $album->removeUserAccess($user);
                }
            } else {
                $user = $service->getUserMapper()->getRepository()->find($params['user_id']);
                $album->removeUserAccess($user);
            }
        } else {
            $album->removeAllGroupAccess();
        }

        $service->getAlbumMapper()->getRepository()->addOrUpdateAlbum($album);

        return $service->invoke('pwg.permissions.getList', ['cat_id' => $params['cat_id']]);
    }
}
