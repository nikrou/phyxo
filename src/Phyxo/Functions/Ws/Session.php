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
        if (!$service->getAppUserService()->isGuest()) {
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
        $res['username'] = $service->getUserMapper()->getUser()->getUsername();
        foreach (['status' => 'getStatus', 'theme' => 'getTheme', 'language' => 'getLanguage'] as $key => $getMethod) {
            $res[$key] = $service->getUserMapper()->getUser()->$getMethod();
        }

        $res['current_datetime'] = new \DateTime();
        $res['version'] = $service->getCoreVersion();

        if ($service->getUserMapper()->isAdmin()) {
            $res['upload_file_types'] = implode(
                ',',
                array_unique(
                    array_map(
                        'strtolower',
                        $service->getConf()['upload_form_all_types'] ? $service->getConf()['file_ext'] : $service->getConf()['picture_ext']
                    )
                )
            );
        }

        return $res;
    }
}
