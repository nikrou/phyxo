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
use Phyxo\Ws\NamedArray;
use Phyxo\Ws\NamedStruct;
use App\Repository\LanguageRepository;
use App\Repository\HistoryRepository;

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
    public static function getList($params, &$service)
    {
        global $conf, $conn;

        $where_clauses = ['1=1'];

        if (!empty($params['user_id'])) {
            $where_clauses[] = 'u.' . $conf['user_fields']['id'] . ' ' . $conn->in($params['user_id']);
        }

        if (!empty($params['username'])) {
            $where_clauses[] = 'u.' . $conf['user_fields']['username'] . ' LIKE \'' . $conn->db_real_escape_string($params['username']) . '\'';
        }

        if (!empty($params['status'])) {
            $params['status'] = array_intersect($params['status'], $conn->get_enums(USER_INFOS_TABLE, 'status'));
            if (count($params['status']) > 0) {
                $where_clauses[] = 'ui.status ' . $conn->in($params['status']);
            }
        }

        if (!empty($params['min_level'])) {
            if (!in_array($params['min_level'], $conf['available_permission_levels'])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid level');
            }
            $where_clauses[] = 'ui.level >= ' . $params['min_level'];
        }

        if (!empty($params['group_id'])) {
            $where_clauses[] = 'ug.group_id ' . $conn->in($params['group_id']);
        }

        $display = ['u.' . $conf['user_fields']['id'] => 'id'];

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
                $display['u.' . $conf['user_fields']['username']] = 'username';
            }
            if (isset($params['display']['email'])) {
                $display['u.' . $conf['user_fields']['email']] = 'email';
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

        $query = 'SELECT DISTINCT ';

        $first = true;
        foreach ($display as $field => $name) {
            if (!$first) {
                $query .= ', ';
            } else {
                $first = false;
            }
            $query .= $field . ' AS ' . $name;
        }

        $query .= ' FROM ' . USERS_TABLE . ' AS u';
        $query .= ' LEFT JOIN ' . USER_INFOS_TABLE . ' AS ui ON u.' . $conf['user_fields']['id'] . ' = ui.user_id';
        $query .= ' LEFT JOIN ' . USER_GROUP_TABLE . ' AS ug ON u.' . $conf['user_fields']['id'] . ' = ug.user_id';
        $query .= ' WHERE ' . implode(' AND ', $where_clauses);
        $query .= ' ORDER BY ' . $conn->db_real_escape_string($params['order']);
        $query .= ' LIMIT ' . (int)$params['per_page'] . ' OFFSET ' . (int)($params['per_page'] * $params['page']) . ';';

        $users = [];
        $result = $conn->db_query($query);
        while ($row = $conn->db_fetch_assoc($result)) {
            $row['id'] = intval($row['id']);
            $users[$row['id']] = $row;
        }

        if (count($users) > 0) {
            if (isset($params['display']['groups'])) {
                $query = 'SELECT user_id, group_id FROM ' . USER_GROUP_TABLE;
                $query .= ' WHERE user_id ' . $conn->in(array_keys($users));
                $result = $conn->db_query($query);

                while ($row = $conn->db_fetch_assoc($result)) {
                    $users[$row['user_id']]['groups'][] = intval($row['group_id']);
                }
            }

            if (isset($params['display']['registration_date_string'])) {
                foreach ($users as $cur_user) {
                    $users[$cur_user['id']]['registration_date_string'] = \Phyxo\Functions\DateTime::format_date($cur_user['registration_date'], ['day', 'month', 'year']);
                }
            }

            if (isset($params['display']['registration_date_since'])) {
                foreach ($users as $cur_user) {
                    $users[$cur_user['id']]['registration_date_since'] = \Phyxo\Functions\DateTime::time_since($cur_user['registration_date'], 'month');
                }
            }

            if (isset($params['display']['last_visit'])) {
                $result = (new HistoryRepository($conn))->getMaxIdForUsers(array_keys($users));
                $history_ids = $conn->result2array($result, null, 'history_id');

                if (count($history_ids) == 0) {
                    $history_ids[] = -1;
                }

                $result = (new HistoryRepository($conn))->findByIds($history_ids);
                while ($row = $conn->db_fetch_assoc($result)) {
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

        $users = \Phyxo\Functions\Plugin::trigger_change('ws_users_getList', $users);

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
    public static function add($params, &$service)
    {
        global $conf, $services;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if ($conf['double_password_type_in_admin']) {
            if ($params['password'] != $params['password_confirm']) {
                return new Error(Server::WS_ERR_INVALID_PARAM, \Phyxo\Functions\Language::l10n('The passwords do not match'));
            }
        }

        $user_id = $services['users']->registerUser(
            $params['username'],
            $params['password'],
            $params['email'],
            false, // notify admin
            $errors,
            $params['send_password_by_mail']
        );

        if (!$user_id) {
            return new Error(Server::WS_ERR_INVALID_PARAM, $errors[0]);
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
    public static function delete($params, &$service)
    {
        global $conf, $user, $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $protected_users = [
            $user['id'],
            $conf['guest_id'],
            $conf['default_user_id'],
            $conf['webmaster_id'],
        ];

        // an admin can't delete other admin/webmaster
        if ('admin' == $user['status']) {
            $query = 'SELECT user_id ' . USER_INFOS_TABLE;
            $query .= ' WHERE status ' . $conn->in(['webmaster', 'admin']);
            $protected_users = array_merge($protected_users, $conn->query2array($query, null, 'user_id'));
        }

        // protect some users
        $params['user_id'] = array_diff($params['user_id'], $protected_users);

        $counter = 0;

        foreach ($params['user_id'] as $user_id) {
            \Phyxo\Functions\Utils::delete_user($user_id);
            $counter++;
        }

        return \Phyxo\Functions\Language::l10n_dec(
            '%d user deleted',
            '%d users deleted',
            $counter
        );
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
    public static function setInfo($params, &$service)
    {
        global $conf, $user, $conn, $services;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $updates = $updates_infos = [];
        $update_status = null;

        if (count($params['user_id']) == 1) {
            if (\Phyxo\Functions\Utils::get_username($params['user_id'][0]) === false) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'This user does not exist.');
            }

            if (!empty($params['username'])) {
                $user_id = $services['users']->getUserId($params['username']);
                if ($user_id and $user_id != $params['user_id'][0]) {
                    return new Error(Server::WS_ERR_INVALID_PARAM, \Phyxo\Functions\Language::l10n('this login is already used'));
                }
                if ($params['username'] != strip_tags($params['username'])) {
                    return new Error(Server::WS_ERR_INVALID_PARAM, \Phyxo\Functions\Language::l10n('html tags are not allowed in login'));
                }
                $updates[$conf['user_fields']['username']] = $params['username'];
            }

            if (!empty($params['email'])) {
                if (($error = $services['users']->validateMailAddress($params['user_id'][0], $params['email'])) != '') {
                    return new Error(Server::WS_ERR_INVALID_PARAM, $error);
                }
                $updates[$conf['user_fields']['email']] = $params['email'];
            }

            if (!empty($params['password'])) {
                $updates[$conf['user_fields']['password']] = $services['users']->passwordHash($params['password']);
            }
        }

        if (!empty($params['status'])) {
            if (in_array($params['status'], ['webmaster', 'admin']) and !$services['users']->isWebmaster()) {
                return new Error(403, 'Only webmasters can grant "webmaster/admin" status');
            }

            if (!in_array($params['status'], ['guest', 'generic', 'normal', 'admin', 'webmaster'])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid status');
            }

            $protected_users = [
                $user['id'],
                $conf['guest_id'],
                $conf['webmaster_id'],
            ];

            // an admin can't change status of other admin/webmaster
            if ('admin' == $user['status']) {
                $query = 'SELECT user_id ' . USER_INFOS_TABLE;
                $query .= ' WHERE status ' . $conn->in(['webmaster', 'admin']);
                $protected_users = array_merge($protected_users, $conn->query2array($query, null, 'user_id'));
            }

            // status update query is separated from the rest as not applying to the same
            // set of users (current, guest and webmaster can't be changed)
            $params['user_id_for_status'] = array_diff($params['user_id'], $protected_users);

            $update_status = $params['status'];
        }

        if (!empty($params['level']) or @$params['level'] === 0) {
            if (!in_array($params['level'], $conf['available_permission_levels'])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid level');
            }
            $updates_infos['level'] = $params['level'];
        }

        if (!empty($params['language'])) {
            $languages = $conn->result2array((new LanguageRepository($conn))->findAll(), 'id', 'name');

            if (!isset($languages[$params['language']])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid language');
            }
            $updates_infos['language'] = $params['language'];
        }

        if (!empty($params['theme'])) {
            $themes = $conn->result2array((new ThemeRepository($conn))->findAll(), 'id', 'name');
            if (!isset($themes[$params['theme']])) {
                return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid theme');
            }
            $updates_infos['theme'] = $params['theme'];
        }

        if (!empty($params['nb_image_page'])) {
            $updates_infos['nb_image_page'] = $params['nb_image_page'];
        }

        if (!empty($params['recent_period']) || $params['recent_period'] === 0) {
            $updates_infos['recent_period'] = $params['recent_period'];
        }

        if (!empty($params['expand']) || $params['expand'] === false) {
            $updates_infos['expand'] = $conn->boolean_to_string($params['expand']);
        }

        if (!empty($params['show_nb_comments']) || $params['show_nb_comments'] === false) {
            $updates_infos['show_nb_comments'] = $conn->boolean_to_string($params['show_nb_comments']);
        }

        if (!empty($params['show_nb_hits']) || $params['show_nb_hits'] === false) {
            $updates_infos['show_nb_hits'] = $conn->boolean_to_string($params['show_nb_hits']);
        }

        if (!empty($params['enabled_high']) || $params['enabled_high'] === false) {
            $updates_infos['enabled_high'] = $conn->boolean_to_string($params['enabled_high']);
        }

        // perform updates
        $conn->single_update(
            USERS_TABLE,
            $updates,
            [$conf['user_fields']['id'] => $params['user_id'][0]]
        );

        if (isset($update_status) and count($params['user_id_for_status']) > 0) {
            $query = 'UPDATE ' . USER_INFOS_TABLE;
            $query .= ' SET status = \'' . $conn->db_real_escape_string($update_status) . '\'';
            $query .= ' WHERE user_id ' . $conn->in($params['user_id_for_status']);
            $conn->db_query($query);
        }

        if (count($updates_infos) > 0) {
            $query = 'UPDATE ' . USER_INFOS_TABLE;
            $query .= ' SET ';

            $first = true;
            foreach ($updates_infos as $field => $value) {
                if (!$first) {
                    $query .= ', ';
                } else {
                    $first = false;
                }
                $query .= $field . '=\'' . $value . '\'';
            }

            $query .= ' WHERE user_id ' . $conn->in($params['user_id']);
            $conn->db_query($query);
        }

        // manage association to groups
        if (!empty($params['group_id'])) {
            $query = 'DELETE FROM ' . USER_GROUP_TABLE;
            $query .= ' WHERE user_id ' . $conn->in($params['user_id']);
            $conn->db_query($query);

            // we remove all provided groups that do not really exist
            $query = 'SELECT id FROM ' . GROUPS_TABLE;
            $query .= ' WHERE id ' . $conn->in($params['group_id']);
            $group_ids = $conn->query2array($query, null, 'id');

            // if only -1 (a group id that can't exist) is in the list, then no group is associated

            if (count($group_ids) > 0) {
                $inserts = [];

                foreach ($group_ids as $group_id) {
                    foreach ($params['user_id'] as $user_id) {
                        $inserts[] = ['user_id' => $user_id, 'group_id' => $group_id];
                    }
                }

                $conn->mass_inserts(USER_GROUP_TABLE, array_keys($inserts[0]), $inserts);
            }
        }

        \Phyxo\Functions\Utils::invalidate_user_cache();

        return $service->invoke('pwg.users.getList', [
            'user_id' => $params['user_id'],
            'display' => 'basics,' . implode(',', array_keys($updates_infos)),
        ]);
    }
}
