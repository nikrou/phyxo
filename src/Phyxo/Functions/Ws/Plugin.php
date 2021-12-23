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

use App\Entity\Plugin as EntityPlugin;
use Phyxo\Ws\Error;
use Phyxo\Plugin\Plugins;
use Phyxo\Ws\Server;

class Plugin
{
    /**
     * API method
     * Returns the list of all plugins
     * @param mixed[] $params
     */
    public static function getList($params, Server $service)
    {
        $plugins = new Plugins($service->getManagerRegistry()->getRepository(EntityPlugin::class), $service->getUserMapper());
        $plugins->setRootPath($service->getParams()->get('plugins_dir'));
        $plugins->sortFsPlugins('name');
        $plugin_list = [];

        foreach ($plugins->getFsPlugins() as $plugin_id => $fs_plugin) {
            if (isset($plugins->getDbPlugins()[$plugin_id])) {
                $state = $plugins->getDbPlugins()[$plugin_id]['state'];
            } else {
                $state = 'uninstalled';
            }

            $plugin_list[] = [
                'id' => $plugin_id,
                'name' => $fs_plugin['name'],
                'version' => $fs_plugin['version'],
                'state' => $state,
                'description' => $fs_plugin['description'],
            ];
        }

        return $plugin_list;
    }

    /**
     * API method
     * Performs an action on a plugin
     * @param mixed[] $params
     *    @option string action
     *    @option string plugin
     *    @option string pwg_token
     */
    public static function performAction($params, Server $service)
    {
        $plugins = new Plugins($service->getManagerRegistry()->getRepository(EntityPlugin::class), $service->getUserMapper());
        $plugins->setRootPath($service->getParams()->get('plugins_dir'));
        $error = $plugins->performAction($params['action'], $params['plugin']);

        if (!empty($error)) {
            return new Error(500, $error);
        }
    }
}
