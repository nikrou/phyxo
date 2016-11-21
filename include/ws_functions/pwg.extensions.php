<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

use Phyxo\Theme\Themes;
use Phyxo\Plugin\Plugins;
use Phyxo\Language\Languages;
use Phyxo\Update\Updates;

/**
 * API method
 * Returns the list of all plugins
 * @param mixed[] $params
 */
function ws_plugins_getList($params, $service) {
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
function ws_plugins_performAction($params, $service) {
    global $template;

    if (get_pwg_token() != $params['pwg_token']) {
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
function ws_themes_performAction($params, $service) {
    global $template;

    if (get_pwg_token() != $params['pwg_token']) {
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
function ws_extensions_update($params, $service) {
    global $template, $services;

    if (!$services['users']->isWebmaster()) {
        return new Phyxo\Ws\Error(401, l10n('Webmaster status is required.'));
    }

    if (get_pwg_token() != $params['pwg_token']) {
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

                redirect(PHPWG_ROOT_PATH
                         . 'ws.php'
                         . '?method=pwg.extensions.update'
                         . '&type=plugins'
                         . '&id=' . $extension_id
                         . '&revision=' . $revision
                         . '&reactivate=true'
                         . '&pwg_token=' . get_pwg_token()
                                         . '&format=json'
                );
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

        $template->delete_compiled_templates();
        return l10n('%s has been successfully updated.', $extension_name);
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
function ws_extensions_ignoreupdate($params, $service) {
    global $conf, $services;

    define('IN_ADMIN', true);
    include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

    if (!$services['users']->isWebmaster()) {
        return new Phyxo\Ws\Error(401, 'Access denied');
    }

    if (get_pwg_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    $conf['updates_ignored'] = unserialize($conf['updates_ignored']); // @TODO: use json_decode instead

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

        conf_update_param('updates_ignored', serialize($conf['updates_ignored'])); // @TODO: use json_encode instead
        unset($_SESSION['extensions_need_update']);
        return true;
    }

    if (empty($params['id']) or empty($params['type']) or !in_array($params['type'], array('plugins', 'themes', 'languages'))) {
        return new Phyxo\Ws\Error(403, 'Invalid parameters');
    }

    // Add or remove extension from ignore list
    if (!in_array($params['id'], $conf['updates_ignored'][ $params['type'] ])) {
        $conf['updates_ignored'][ $params['type'] ][] = $params['id'];
    }

    conf_update_param('updates_ignored', serialize($conf['updates_ignored'])); // @TODO: use json_encode instead
    unset($_SESSION['extensions_need_update']);
    return true;
}

/**
 * API method
 * Checks for updates (core and extensions)
 * @param mixed[] $params
 */
function ws_extensions_checkupdates($params, $service) {
    global $conf;

    include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

    $update = new Updates($GLOBALS['conn']);
    $result = array();

    if (!isset($_SESSION['need_update'])) {
        $update->checkCoreUpgrade();
    }

    $result['phyxo_need_update'] = $_SESSION['need_update'];

    $conf['updates_ignored'] = unserialize($conf['updates_ignored']);

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
