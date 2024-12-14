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

use App\Entity\Theme as EntityTheme;
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
     */
    public static function performAction(array $params, Server $service)
    {
        $themes = new Themes($service->getManagerRegistry()->getRepository(EntityTheme::class), $service->getUserMapper());
        $errors = $themes->performAction($params['action'], $params['theme']);

        if ($errors !== '' && $errors !== '0') {
            return new Error(500, $errors);
        }

        return true;
    }
}
