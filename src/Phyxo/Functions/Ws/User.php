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

use App\Entity\Group;
use App\Entity\Language;
use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use Phyxo\Ws\NamedArray;
use Phyxo\Ws\NamedStruct;
use App\Repository\HistoryRepository;
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use App\Entity\User as EntityUser;
use App\Entity\UserInfos;

class User
{
    /**
     * API method
     * Returns a list of users
     * @param mixed[] $params
     *    @option int[] user_id (optional)
     *    @option string username (optional)
     *    @option string[] status (optional)
     *    @option int min_level (optional)
     *    @option int[] group_id (optional)
     *    @option int per_page
     *    @option int page
     *    @option string order
     *    @option string display
     */
    public static function getList($params, Server $service)
    {
        $where_clauses = ['1=1'];

        if (!empty($params['user_id'])) {
            $where_clauses[] = 'u.id ' . $service->getConnection()->in($params['user_id']);
        }

        if (!empty($params['username'])) {
            $where_clauses[] = 'u.username LIKE \'' . $service->getConnection()->db_real_escape_string($params['username']) . '\'';
        }

        if (!empty($params['status'])) {
            $params['status'] = array_intersect($params['status'], EntityUser::ALL_STATUS);
            if (count($params['status']) > 0) {
                $where_clauses[] = 'ui.status ' . $service->getConnection()->in($params['status']);
            }
        }

        if (!empty($params['min_level'])) {
            if (!in_array($params['min_level'], $service->getConf()['available_permission_levels'])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid level');
            }
            $where_clauses[] = 'ui.level >= ' . $params['min_level'];
        }

        if (!empty($params['group_id'])) {
            $where_clauses[] = 'ug.group_id ' . $service->getConnection()->in($params['group_id']);
        }

        $display = ['u.id' => 'id'];

        if ($params['display'] != 'none') {
            $params['display'] = array_map('trim', explode(',', $params['display']));

            if (in_array('all', $params['display'])) {
                $params['display'] = [
                    'username', 'email', 'status', 'level', 'groups', 'language', 'theme',
                    'nb_image_page', 'recent_period', 'expand', 'show_nb_comments', 'show_nb_hits',
                    'enabled_high', 'registration_date', 'registration_date_string',
                    'registration_date_since', 'last_visit', 'last_visit_string',
                    'last_visit_since'
                ];
            } elseif (in_array('basics', $params['display'])) {
                $params['display'] = array_merge($params['display'], [
                    'username', 'email', 'status', 'level', 'groups',
                ]);
            }
            $params['display'] = array_flip($params['display']);

            // if registration_date_string or registration_date_since is requested, then registration_date is automatically added
            if (isset($params['display']['registration_date_string']) or isset($params['display']['registration_date_since'])) {
                $params['display']['registration_date'] = true;
            }

            // if last_visit_string or last_visit_since is requested, then last_visit is automatically added
            if (isset($params['display']['last_visit_string']) or isset($params['display']['last_visit_since'])) {
                $params['display']['last_visit'] = true;
            }

            if (isset($params['display']['username'])) {
                $display['u.username'] = 'username';
            }
            if (isset($params['display']['email'])) {
                $display['u.mail_address'] = 'email';
            }

            $ui_fields = [
                'status', 'level', 'language', 'theme', 'nb_image_page', 'recent_period', 'expand',
                'show_nb_comments', 'show_nb_hits', 'enabled_high', 'registration_date'
            ];
            foreach ($ui_fields as $field) {
                if (isset($params['display'][$field])) {
                    $display['ui.' . $field] = $field;
                }
            }
        } else {
            $params['display'] = [];
        }

        $users = [];
        // $result = (new UserRepository($service->getConnection()))->getList($display, $where_clauses, $params['order'], $params['per_page'], $params['per_page'] * $params['page']);
        foreach ($service->getManagerRegistry()->getRepository(EntityUser::class)->getList() as $user) {
            $users[$user['id']] = $user;
        }

        if (count($users) > 0) {
            if (!isset($params['display']['groups'])) {
                unset($user['groups']);
            }

            if (isset($params['display']['registration_date_string'])) {
                foreach ($users as $cur_user) {
                    $users[$cur_user['id']]['registration_date_string'] = \Phyxo\Functions\DateTime::format_date($cur_user['userInfos']['registration_date'], ['day', 'month', 'year']);
                }
            }

            if (isset($params['display']['registration_date_since'])) {
                foreach ($users as $cur_user) {
                    $users[$cur_user['id']]['registration_date_since'] = \Phyxo\Functions\DateTime::time_since($cur_user['userInfos']['registration_date'], 'month');
                }
            }

            if (isset($params['display']['last_visit'])) {
                $result = (new HistoryRepository($service->getConnection()))->getMaxIdForUsers(array_keys($users));
                $history_ids = $service->getConnection()->result2array($result, null, 'history_id');

                if (count($history_ids) == 0) {
                    $history_ids[] = -1;
                }

                $result = (new HistoryRepository($service->getConnection()))->findByIds($history_ids);
                while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                    $last_visit = $row['date'] . ' ' . $row['time'];
                    $users[$row['user_id']]['last_visit'] = $last_visit;

                    if (isset($params['display']['last_visit_string'])) {
                        $users[$row['user_id']]['last_visit_string'] = \Phyxo\Functions\DateTime::format_date($last_visit, ['day', 'month', 'year']);
                    }

                    if (isset($params['display']['last_visit_since'])) {
                        $users[$row['user_id']]['last_visit_since'] = \Phyxo\Functions\DateTime::time_since($last_visit, 'day');
                    }
                }
            }
        }

        return [
            'paging' => new NamedStruct(
                [
                    'page' => $params['page'],
                    'per_page' => $params['per_page'],
                    'count' => count($users)
                ]
            ),
            'users' => new NamedArray(array_values($users), 'user')
        ];
    }

    /**
     * API method
     * Adds a user
     * @param mixed[] $params
     *    @option string username
     *    @option string password (optional)
     *    @option string email (optional)
     */
    public static function add($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if ($service->getConf()['double_password_type_in_admin']) {
            if ($params['password'] != $params['password_confirm']) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'The passwords do not match');
            }
        }

        try {
            $user = new EntityUser();
            $user->setUsername($params['username']);
            $user->setMailAddress($params['email']);
            $user->setPassword($service->getPasswordEncoder()->encodePassword($user, $params['password']));
            $user->addRole('ROLE_NORMAL');

            $user_id = $service->getUserManager()->register($user);

            if ($params['send_password_by_mail']) {
                // send password by mail
            }
        } catch (\Exception $e) {
            return new Error(Server::WS_ERR_INVALID_PARAM, $e->getMessage());
        }

        return $service->invoke('pwg.users.getList', ['user_id' => $user_id]);
    }

    /**
     * API method
     * Deletes users
     * @param mixed[] $params
     *    @option int[] user_id
     *    @option string pwg_token
     */
    public static function delete($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $protected_users = [$service->getUserMapper()->getUser()->getId()];
        $protected_users[] = $service->getUserMapper()->getDefaultUser()->getId();

        // an admin can't delete other admin/webmaster
        if ($service->getUserMapper()->isAdmin()) {
            foreach ($service->getManagerRegistry()->getRepository(UserInfos::class)->findBy(['status' => [EntityUser::STATUS_WEBMASTER, EntityUser::STATUS_ADMIN]]) as $userInfos) {
                $protected_users[] = $userInfos->getUser()->getId();
            }
        }

        // protect some users
        $params['user_id'] = array_diff($params['user_id'], $protected_users);

        $counter = 0;
        foreach ($params['user_id'] as $user_id) {
            $service->getUserMapper()->deleteUser($user_id);
            $counter++;
        }

        return $counter === 1 ? 'one user deleted' : sprintf('%d users deleted', $counter);
    }

    /**
     * API method
     * Updates users
     * @param mixed[] $params
     *    @option int[] user_id
     *    @option string username (optional)
     *    @option string password (optional)
     *    @option string email (optional)
     *    @option string status (optional)
     *    @option int level (optional)
     *    @option string language (optional)
     *    @option string theme (optional)
     *    @option int nb_image_page (optional)
     *    @option int recent_period (optional)
     *    @option bool expand (optional)
     *    @option bool show_nb_comments (optional)
     *    @option bool show_nb_hits (optional)
     *    @option bool enabled_high (optional)
     */
    public static function setInfo($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $updates_infos = [];
        $update_status = null;

        if (count($params['user_id']) === 1) {
            $needUpdate = false;
            $user = $service->getManagerRegistry()->getRepository(EntityUser::class)->find($params['user_id'][0]);

            if (is_null($user)) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'This user does not exist.');
            }

            if (!empty($params['username'])) {
                if ($service->getManagerRegistry()->getRepository(EntityUser::class)->isUsernameExistsExceptUser($params['username'], $params['user_id'][0])) {
                    return new Error(Server::WS_ERR_INVALID_PARAM, 'this login is already used');
                }

                if ($params['username'] != strip_tags($params['username'])) {
                    return new Error(Server::WS_ERR_INVALID_PARAM, 'html tags are not allowed in login');
                }
                $user->setUsername($params['username']);
                $needUpdate = true;
            }

            if (!empty($params['email'])) {
                if (filter_var($params['email'], FILTER_VALIDATE_EMAIL) === false) {
                    return new Error(Server::WS_ERR_INVALID_PARAM, 'mail address must be like xxx@yyy.eee (example : jack@altern.org)');
                }

                if ($service->getManagerRegistry()->getRepository(EntityUser::class)->isEmailExistsExceptUser($params['email'], $params['user_id'][0])) {
                    return new Error(Server::WS_ERR_INVALID_PARAM, 'this email address is already in use');
                }

                $user->setMailAddress($params['email']);
                $needUpdate = true;
            }

            if (!empty($params['password'])) {
                $user->setPassword($service->getPasswordEncoder()->encodePassword(new EntityUser(), $params['password']));
                $needUpdate = true;
            }

            if ($needUpdate) {
                $service->getManagerRegistry()->getRepository(EntityUser::class)->updateUser($user);
            }
        }

        if (!empty($params['status'])) {
            if (in_array($params['status'], [EntityUser::STATUS_WEBMASTER, EntityUser::STATUS_ADMIN]) && !$service->getUserMapper()->isWebmaster()) {
                return new Error(403, 'Only webmasters can grant "webmaster/admin" status');
            }

            if (!in_array($params['status'], EntityUser::ALL_STATUS)) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid status');
            }

            $protected_users = [$service->getUserMapper()->getUser()->getId()];
            $protected_users[] = $service->getUserMapper()->getDefaultUser()->getId();

            // an admin can't change status of other admin/webmaster
            if ($service->getUserMapper()->isAdmin() && !$service->getUserMapper()->isWebmaster()) {
                foreach ($service->getManagerRegistry()->getRepository(UserInfos::class)->findOneBy(['status' => [EntityUser::STATUS_WEBMASTER, EntityUser::STATUS_ADMIN]]) as $userInfos) {
                    $protected_users[] = $userInfos->getUser()->getId();
                }
            }

            // status update query is separated from the rest as not applying to the same
            // set of users (current, guest and webmaster can't be changed)
            $params['user_id_for_status'] = array_diff($params['user_id'], $protected_users);

            $update_status = $params['status'];
        }

        if (!empty($params['level']) || (isset($params['level']) && $params['level'] === 0)) {
            if (!in_array($params['level'], $service->getConf()['available_permission_levels'])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid level');
            }
            $updates_infos['level'] = $params['level'];
        }

        if (!empty($params['language'])) {
            if (is_null($service->getManagerRegistry()->getRepository(Language::class)->findOneBy(['id' => $params['language']]))) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid language');
            }
            $updates_infos['language'] = $params['language'];
        }

        if (!empty($params['theme'])) {
            if (is_null($service->getManagerRegistry->getRepository(Theme::class)->findOneBy(['id' => $params['theme']]))) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid theme');
            }
            $updates_infos['theme'] = $params['theme'];
        }

        if (!empty($params['nb_image_page'])) {
            $updates_infos['nb_image_page'] = $params['nb_image_page'];
        }

        if (!empty($params['recent_period']) || (isset($params['recent_period']) && $params['recent_period'] === 0)) {
            $updates_infos['recent_period'] = $params['recent_period'];
        }

        if (!empty($params['expand']) || (isset($params['expand']) && $params['expand'] === false)) {
            $updates_infos['expand'] = $params['expand'];
        }

        if (!empty($params['show_nb_comments']) || (isset($params['show_nb_comments']) && $params['show_nb_comments'] === false)) {
            $updates_infos['show_nb_comments'] = $params['show_nb_comments'];
        }

        if (!empty($params['show_nb_hits']) || (isset($params['show_nb_hits']) && $params['show_nb_hits'] === false)) {
            $updates_infos['show_nb_hits'] = $params['show_nb_hits'];
        }

        if (!empty($params['enabled_high']) || (isset($params['enabled_high']) && $params['enabled_high'] === false)) {
            $updates_infos['enabled_high'] = $params['enabled_high'];
        }

        if (isset($update_status) && count($params['user_id_for_status']) > 0) {
            $service->getManagerRegistry()->getRepository(UserInfos::class)->updateFieldForUsers('status', $update_status, $params['user_id_for_status']);
        }

        if (count($updates_infos) > 0) {
            $service->getManagerRegistry()->getRepository(UserInfos::class)->updateFieldsForUsers($updates_infos, $params['user_id']);
        }

        // manage association to groups
        if (!empty($params['group_id'])) {
            $groups = [];
            foreach ($service->getManagerRegistry()->getRepository(Group::class)->findById($params['group_id']) as $group) {
                $groups[] = $group;
            }

            foreach ($service->getManagerRegistry()->getRepository(EntityUser::class)->findById($params['user_id']) as $user) {
                $user->setGroups($groups);
            }
        }

        $service->getUserMapper()->invalidateUserCache();

        return $service->invoke('pwg.users.getList', [
            'user_id' => $params['user_id'],
            'display' => 'basics,' . implode(',', array_keys($updates_infos)),
        ]);
    }
}
