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
use App\Repository\BaseRepository;
use Phyxo\Ws\Server;

class Session
{
    /**
     * API method
     * Performs a login
     * @param mixed[] $params
     *    @option string username
     *    @option string password
     */
    public static function login($params, Server $service)
    {
        if ($service->getUserMapper()->tryLogUser($params['username'], $params['password'], false)) {
            return true;
        }

        return new Error(999, 'Invalid username/password');
    }

    /**
     * API method
     * Performs a logout
     * @param mixed[] $params
     */
    public static function logout($params, Server $service)
    {
        if (!$service->getUserMapper()->isGuest()) {
            $service->getUserMapper()->logoutUser();
        }

        return true;
    }

    /**
     * API method
     * Returns info about the current user
     * @param mixed[] $params
     */
    public static function getStatus($params, Server $service)
    {
        global $user, $conf, $conn;

        $res['username'] = $service->getUserMapper()->isGuest() ? 'guest' : stripslashes($user['username']);
        foreach (['status', 'theme', 'language'] as $k) {
            $res[$k] = $user[$k];
        }
        $res['pwg_token'] = \Phyxo\Functions\Utils::get_token();
        $res['charset'] = \Phyxo\Functions\Utils::get_charset();

        $res['current_datetime'] = (new BaseRepository($conn))->getNow();
        $res['version'] = PHPWG_VERSION;

        if ($service->getUserMapper()->isAdmin()) {
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
