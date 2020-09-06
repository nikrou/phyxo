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

use App\Entity\Config;
use App\Repository\ConfigRepository;
use Phyxo\Extension\Extensions;
use Phyxo\Theme\Themes;
use Phyxo\Plugin\Plugins;
use Phyxo\Language\Languages;
use Phyxo\Update\Updates;

use Phyxo\Ws\Error;
use Phyxo\Ws\Server;

class Extension
{
    /**
     * API method
     * Updates an extension
     * @param mixed[] $params
     *    @option string type
     *    @option string id
     *    @option string revision
     *    @option string pwg_token
     *    @option bool reactivate (optional - undocumented)
     */
    public static function update($params, Server $service)
    {
        if (!$service->getUserMapper()->isWebmaster()) {
            return new Error(401, 'Webmaster status is required.');
        }

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!in_array($params['type'], Extensions::TYPES)) {
            return new Error(403, "invalid extension type");
        }

        $type = $params['type'];
        $typeClassName = sprintf('\Phyxo\%s\%s', ucfirst(substr($type, 0, -1)), ucfirst($type));
        $extension_id = $params['id'];
        $revision = $params['revision'];

        $extension = new $typeClassName($service->getConnection(), $service->getUserMapper());
        $extension->setExtensionsURL($service->getExtensionsURL());
        $extension->setRootPath($service->getParams()->get($type . '_dir'));

        try {
            if ($type === 'plugins') {
                if (isset($extension->getDbPlugins()[$extension_id]) && $extension->getDbPlugins()[$extension_id]['state'] === 'active') {
                    $extension->performAction('deactivate', $extension_id);

                    return;
                }

                $errors = $extension->performAction('update', $extension_id, $revision);
                $extension_name = $extension->getFsPlugins()[$extension_id]['name'];

                if (isset($params['reactivate'])) {
                    $extension->performAction('activate', $extension_id);
                }
            } elseif ($type === 'themes') {
                $extension->extractThemeFiles('upgrade', $revision, $extension_id);
                $extension_name = $extension->getFsThemes()[$extension_id]['name'];
            } elseif ($type === 'languages') {
                $extension->extractLanguageFiles('upgrade', $revision);
                $extension_name = $extension->getFsLanguages()[$extension_id]['name'];
            }

            return sprintf('%s has been successfully updated.', $extension_name);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * API method
     * Ignore an update
     * @param mixed[] $params
     *    @option string type (optional)
     *    @option string id (optional)
     *    @option bool reset
     *    @option string pwg_token
     */
    public static function ignoreupdate($params, Server $service)
    {
        $conf = $service->getConf();

        if (!$service->getUserMapper()->isWebmaster()) {
            return new Error(401, 'Access denied');
        }

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!empty($conf['updates_ignored'])) {
            $updates_ignored = $conf['updates_ignored'];
        } else {
            $updates_ignored = ['plugins' => [], 'themes' => [], 'languages' => []];
        }

        // Reset ignored extension
        if ($params['reset']) {
            if (!empty($params['type']) && !empty($updates_ignored[$params['type']])) {
                $updates_ignored[$params['type']] = [];
            } else {
                $updates_ignored = ['plugins' => [], 'themes' => [], 'languages' => []];
            }

            $service->getManagerRegistry()->getRepository(Config::class)->addOrUpdateParam('updates_ignored', $updates_ignored);
            return true;
        }

        if (empty($params['id']) || empty($params['type']) || !in_array($params['type'], Extensions::TYPES)) {
            return new Error(403, 'Invalid parameters');
        }

        // Add or remove extension from ignore list
        if (!in_array($params['id'], $updates_ignored[$params['type']])) {
            $updates_ignored[$params['type']][] = $params['id'];
        }

        $service->getManagerRegistry()->getRepository(Config::class)->addOrUpdateParam('updates_ignored', $updates_ignored);

        return true;
    }

    /**
     * API method
     * Checks for updates (core and extensions)
     * @param mixed[] $params
     */
    public static function checkupdates($params, Server $service)
    {
        $result = [];
        $update = new Updates($service->getConnection(), $service->getUserMapper(), $service->getCoreVersion());
        $update->setExtensionsURL($service->getExtensionsURL());
        $update->setUpdateUrl($service->getParams()->get('update_url'));
        $update->checkCoreUpgrade();

        $result['phyxo_need_update'] = $_SESSION['need_update'];

        if (!empty($service->getConf()['updates_ignored'])) {
            $updates_ignored = $service->getConf()['updates_ignored'];
        } else {
            $updates_ignored = [];
        }

        if (!isset($_SESSION['extensions_need_update'])) {
            $service->getConf()->addOrUpdateParam('updates_ignored', $update->checkExtensions($updates_ignored));
        } else {
            $service->getConf()->addOrUpdateParam('updates_ignored', $update->checkUpdatedExtensions($updates_ignored));
        }

        if (!isset($_SESSION['extensions_need_update'])) {
            $result['ext_need_update'] = false;
        } else {
            $result['ext_need_update'] = !empty($_SESSION['extensions_need_update']);
        }

        return $result;
    }
}
