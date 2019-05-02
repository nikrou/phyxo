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

use Phyxo\Theme\Themes;
use Phyxo\Ws\Error;
use Phyxo\Ws\Server;

class Theme
{
    /**
     * API method
     * Performs an action on a theme
     * @param mixed[] $params
     *    @option string action
     *    @option string theme
     *    @option string pwg_token
     */
    public static function performAction(array $params, Template $template, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        define('IN_ADMIN', true); // @TODO: remove ?

        $themes = new Themes($service->getConnection());
        $errors = $themes->performAction($params['action'], $params['theme']);

        if (!empty($errors)) {
            return new Error(500, $errors);
        } else {
            if (in_array($params['action'], ['activate', 'deactivate'])) {
                $template->delete_compiled_templates();
            }
            return true;
        }
    }
}
