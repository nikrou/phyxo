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
use Phyxo\Plugin\Plugins;
use Phyxo\Language\Languages;
use Phyxo\Update\Updates;

use Phyxo\Ws\Error;

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
    public static function update($params, $service)
    {
        global $services;

        if (!$services['users']->isWebmaster()) {
            return new Error(401, \Phyxo\Functions\Language::l10n('Webmaster status is required.'));
        }

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!in_array($params['type'], ['plugins', 'themes', 'languages'])) {
            return new Error(403, "invalid extension type");
        }

        $type = $params['type'];
        $typeClassName = sprintf('\Phyxo\%s\%s', ucfirst(substr($type, 0, -1)), ucfirst($type));
        $extension_id = $params['id'];
        $revision = $params['revision'];

        $extension = new $typeClassName($GLOBALS['conn']);

        try {
            if ($type == 'plugins') {
                if (isset($extension->getDbPlugins()[$extension_id]) && $extension->getDbPlugins()[$extension_id]['state'] == 'active') {
                    $extension->performAction('deactivate', $extension_id);

                    \Phyxo\Functions\Utils::redirect(PHPWG_ROOT_PATH
                        . 'ws.php'
                        . '?method=pwg.extensions.update'
                        . '&type=plugins'
                        . '&id=' . $extension_id
                        . '&revision=' . $revision
                        . '&reactivate=true'
                        . '&pwg_token=' . \Phyxo\Functions\Utils::get_token()
                        . '&format=json');
                }

                $errors = $extension->performAction('update', $extension_id, ['revision' => $revision]);
                $extension_name = $extension->getFsPlugins()[$extension_id]['name'];

                if (isset($params['reactivate'])) {
                    $extension->performAction('activate', $extension_id);
                }
            } elseif ($type == 'themes') {
                $extension->extractThemeFiles('upgrade', $revision, $extension_id);
                $extension_name = $extension->getFsThemes()[$extension_id]['name'];
            } elseif ($type == 'languages') {
                $extension->extractLanguageFiles('upgrade', $revision, $extension_id);
                $extension_name = $extension->getFsLanguages()[$extension_id]['name'];
            }

            return \Phyxo\Functions\Language::l10n('%s has been successfully updated.', $extension_name);
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
    public static function ignoreupdate($params, $service)
    {
        global $conf, $services;

        define('IN_ADMIN', true);

        if (!$services['users']->isWebmaster()) {
            return new Error(401, 'Access denied');
        }

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $conf['updates_ignored'] = json_decode($conf['updates_ignored'], true);

        // Reset ignored extension
        if ($params['reset']) {
            if (!empty($params['type']) and isset($conf['updates_ignored'][$params['type']])) {
                $conf['updates_ignored'][$params['type']] = [];
            } else {
                $conf['updates_ignored'] = [
                    'plugins' => [],
                    'themes' => [],
                    'languages' => []
                ];
            }

            unset($_SESSION['extensions_need_update']);
            return true;
        }

        if (empty($params['id']) or empty($params['type']) or !in_array($params['type'], ['plugins', 'themes', 'languages'])) {
            return new Error(403, 'Invalid parameters');
        }

        // Add or remove extension from ignore list
        if (!in_array($params['id'], $conf['updates_ignored'][$params['type']])) {
            $conf['updates_ignored'][$params['type']][] = $params['id'];
        }

        unset($_SESSION['extensions_need_update']);
        return true;
    }

    /**
     * API method
     * Checks for updates (core and extensions)
     * @param mixed[] $params
     */
    public static function checkupdates($params, $service)
    {
        global $conf;

        $update = new Updates($GLOBALS['conn']);
        $result = [];

        if (!isset($_SESSION['need_update'])) {
            $update->checkCoreUpgrade();
        }

        $result['phyxo_need_update'] = $_SESSION['need_update'];

        if (!empty($conf['updates_ignored'])) {
            $conf['updates_ignored'] = json_decode($conf['updates_ignored'], true);
        }

        if (!isset($_SESSION['extensions_need_update'])) {
            $update->checkExtensions();
        } else {
            $update->checkUpdatedExtensions();
        }

        if (!isset($_SESSION['extensions_need_update'])) {
            $result['ext_need_update'] = false;
        } else {
            $result['ext_need_update'] = !empty($_SESSION['extensions_need_update']);
        }

        return $result;
    }
}