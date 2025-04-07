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

use App\Entity\Language;
use App\Entity\Plugin;
use App\Entity\Theme;
use Exception;
use Phyxo\Extension\Extensions;
use Phyxo\Language\Languages;
use Phyxo\Plugin\Plugins;
use Phyxo\Theme\Themes;
use Phyxo\Update\Updates;
use Phyxo\Ws\Error;
use Phyxo\Ws\Server;

class Extension
{
    /**
     * API method
     * Updates an extension.
     *
     * @param mixed[] $params
     *
     *    @option string type
     *    @option string id
     *    @option string revision
     *    @option bool reactivate (optional - undocumented)
     */
    public static function update($params, Server $service)
    {
        if (!$service->getUserMapper()->isWebmaster()) {
            return new Error(401, 'Webmaster status is required.');
        }

        if (!in_array($params['type'], Extensions::TYPES)) {
            return new Error(403, 'invalid extension type');
        }

        $type = $params['type'];
        if ($type === 'plugins') {
            $extension = new Plugins($service->getManagerRegistry()->getRepository(Plugin::class), $service->getUserMapper());
        } elseif ($type === 'languages') {
            $extension = new Languages($service->getManagerRegistry()->getRepository(Language::class), $service->getUserMapper());
        } else { // themes
            $extension = new Themes($service->getManagerRegistry()->getRepository(Theme::class), $service->getUserMapper());
        }

        $extension_id = $params['id'];
        $revision = $params['revision'];

        $extension->setExtensionsURL($service->getExtensionsURL());
        $extension->setRootPath($service->getParams()->get($type . '_dir'));

        try {
            if ($type === 'plugins') {
                if (isset($extension->getDbPlugins()[$extension_id]) && $extension->getDbPlugins()[$extension_id]->getState() === Plugin::ACTIVE) {
                    $extension->performAction('deactivate', $extension_id);

                    return null;
                }

                $extension->performAction('update', $extension_id, $revision);
                $extension_name = $extension->getFsPlugins()[$extension_id]['name'];

                if (isset($params['reactivate'])) {
                    $extension->performAction('activate', $extension_id);
                }
            } elseif ($type === 'languages') {
                $extension->extractLanguageFiles('upgrade', $revision);
                $extension_name = $extension->getFsLanguages()[$extension_id]['name'];
            } else { // themes
                $extension->extractThemeFiles('upgrade', $revision);
                $extension_name = $extension->getFsThemes()[$extension_id]['name'];
            }

            return sprintf('%s has been successfully updated.', $extension_name);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * API method
     * Ignore an update.
     *
     * @param mixed[] $params
     *
     *    @option string type (optional)
     *    @option string id (optional)
     *    @option bool reset
     */
    public static function ignoreupdate($params, Server $service)
    {
        $conf = $service->getConf();

        if (!$service->getUserMapper()->isWebmaster()) {
            return new Error(401, 'Access denied');
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

            $service->getConf()->addOrUpdateParam('updates_ignored', $updates_ignored);

            return true;
        }

        if (empty($params['id']) || empty($params['type']) || !in_array($params['type'], Extensions::TYPES)) {
            return new Error(403, 'Invalid parameters');
        }

        // Add or remove extension from ignore list
        if (!in_array($params['id'], $updates_ignored[$params['type']])) {
            $updates_ignored[$params['type']][] = $params['id'];
        }

        $service->getConf()->addOrUpdateParam('updates_ignored', $updates_ignored);

        return true;
    }

    /**
     * API method
     * Checks for updates (core and extensions).
     *
     * @param mixed[] $params
     */
    public static function checkupdates($params, Server $service)
    {
        $result = [];
        $plugins = new Plugins($service->getManagerRegistry()->getRepository(Plugin::class), $service->getUserMapper());
        $languages = new Languages($service->getManagerRegistry()->getRepository(Language::class), $service->getUserMapper());
        $themes = new Themes($service->getManagerRegistry()->getRepository(Theme::class), $service->getUserMapper());

        $update = new Updates($service->getUserMapper(), $service->getCoreVersion(), $plugins, $themes, $languages);
        $update->setExtensionsURL($service->getExtensionsURL());
        $update->setUpdateUrl($service->getParams()->get('update_url'));
        $update->checkCoreUpgrade();

        $result['phyxo_need_update'] = $update->isCoreNeedUpdate();

        $updates_ignored = empty($service->getConf()['updates_ignored']) ? [] : $service->getConf()['updates_ignored'];

        if (!$update->isExtensionsNeedUpdate()) {
            $service->getConf()->addOrUpdateParam('updates_ignored', $update->checkExtensions($updates_ignored));
            $result['ext_need_update'] = false;
        } else {
            $service->getConf()->addOrUpdateParam('updates_ignored', $update->checkUpdatedExtensions($updates_ignored));
            $result['ext_need_update'] = true;
        }

        return $result;
    }
}
