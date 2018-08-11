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

use Phyxo\Theme\Themes;
use Phyxo\Plugin\Plugins;
use Phyxo\Language\Languages;
use Phyxo\Update\Updates;

/**
 * API method
 * Returns the list of all plugins
 * @param mixed[] $params
 */
function ws_plugins_getList($params, $service)
{
    $plugins = new Plugins($GLOBALS['conn']);
    $plugins->sortFsPlugins('name');
    $plugin_list = array();

    foreach ($plugins->getFsPlugins() as $plugin_id => $fs_plugin) {
        if (isset($plugins->getDbPlugins()[$plugin_id])) {
            $state = $plugins->getDbPlugins()[$plugin_id]['state'];
        } else {
            $state = 'uninstalled';
        }

        $plugin_list[] = array(
            'id' => $plugin_id,
            'name' => $fs_plugin['name'],
            'version' => $fs_plugin['version'],
            'state' => $state,
            'description' => $fs_plugin['description'],
        );
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
function ws_plugins_performAction($params, $service)
{
    global $template;

    if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    define('IN_ADMIN', true);

    $plugins = new Plugins($GLOBALS['conn']);
    $errors = $plugins->performAction($params['action'], $params['plugin']);

    if (!empty($errors)) {
        return new Phyxo\Ws\Error(500, $errors);
    } else {
        if (in_array($params['action'], array('activate', 'deactivate'))) {
            $template->delete_compiled_templates();
        }
        return true;
    }
}

/**
 * API method
 * Performs an action on a theme
 * @param mixed[] $params
 *    @option string action
 *    @option string theme
 *    @option string pwg_token
 */
function ws_themes_performAction($params, $service)
{
    global $template;

    if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    define('IN_ADMIN', true);

    $themes = new Themes($GLOBALS['conn']);
    $errors = $themes->performAction($params['action'], $params['theme']);

    if (!empty($errors)) {
        return new Phyxo\Ws\Error(500, $errors);
    } else {
        if (in_array($params['action'], array('activate', 'deactivate'))) {
            $template->delete_compiled_templates();
        }
        return true;
    }
}

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
function ws_extensions_update($params, $service)
{
    global $template, $services;

    if (!$services['users']->isWebmaster()) {
        return new Phyxo\Ws\Error(401, \Phyxo\Functions\Language::l10n('Webmaster status is required.'));
    }

    if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    if (!in_array($params['type'], array('plugins', 'themes', 'languages'))) {
        return new Phyxo\Ws\Error(403, "invalid extension type");
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

            $errors = $extension->performAction('update', $extension_id, array('revision' => $revision));
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
function ws_extensions_ignoreupdate($params, $service)
{
    global $conf, $services;

    define('IN_ADMIN', true);
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

    if (!$services['users']->isWebmaster()) {
        return new Phyxo\Ws\Error(401, 'Access denied');
    }

    if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    $conf['updates_ignored'] = json_decode($conf['updates_ignored'], true);

    // Reset ignored extension
    if ($params['reset']) {
        if (!empty($params['type']) and isset($conf['updates_ignored'][$params['type']])) {
            $conf['updates_ignored'][$params['type']] = array();
        } else {
            $conf['updates_ignored'] = array(
                'plugins' => array(),
                'themes' => array(),
                'languages' => array()
            );
        }

        \Phyxo\Functions\Conf::conf_update_param('updates_ignored', $conf['updates_ignored']);
        unset($_SESSION['extensions_need_update']);
        return true;
    }

    if (empty($params['id']) or empty($params['type']) or !in_array($params['type'], array('plugins', 'themes', 'languages'))) {
        return new Phyxo\Ws\Error(403, 'Invalid parameters');
    }

    // Add or remove extension from ignore list
    if (!in_array($params['id'], $conf['updates_ignored'][$params['type']])) {
        $conf['updates_ignored'][$params['type']][] = $params['id'];
    }

    \Phyxo\Functions\Conf::conf_update_param('updates_ignored', $conf['updates_ignored']);
    unset($_SESSION['extensions_need_update']);
    return true;
}

/**
 * API method
 * Checks for updates (core and extensions)
 * @param mixed[] $params
 */
function ws_extensions_checkupdates($params, $service)
{
    global $conf;

    $update = new Updates($GLOBALS['conn']);
    $result = array();

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
