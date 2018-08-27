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

use Phyxo\Ws\Error;

class Session
{
    /**
     * API method
     * Performs a login
     * @param mixed[] $params
     *    @option string username
     *    @option string password
     */
    public static function login($params, &$service)
    {
        global $conn, $services;

        if ($services['users']->tryLogUser($params['username'], $params['password'], false)) {
            return true;
        }

        return new Error(999, 'Invalid username/password');
    }

    /**
     * API method
     * Performs a logout
     * @param mixed[] $params
     */
    public static function logout($params, &$service)
    {
        global $services;

        if (!$services['users']->isGuest()) {
            $services['users']->logoutUser();
        }

        return true;
    }

    /**
     * API method
     * Returns info about the current user
     * @param mixed[] $params
     */
    public static function getStatus($params, &$service)
    {
        global $user, $conf, $conn, $services;

        $res['username'] = $services['users']->isGuest() ? 'guest' : stripslashes($user['username']);
        foreach (['status', 'theme', 'language'] as $k) {
            $res[$k] = $user[$k];
        }
        $res['pwg_token'] = \Phyxo\Functions\Utils::get_token();
        $res['charset'] = \Phyxo\Functions\Utils::get_charset();

        list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
        $res['current_datetime'] = $dbnow;
        $res['version'] = PHPWG_VERSION;

        if ($services['users']->isAdmin()) {
            $res['upload_file_types'] = implode(
                ',',
                array_unique(
                    array_map(
                        'strtolower',
                        $conf['upload_form_all_types'] ? $conf['file_ext'] : $conf['picture_ext']
                    )
                )
            );
        }

        return $res;
    }
}
