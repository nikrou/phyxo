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

if (!defined("PLUGINS_BASE_URL")) {
    die ("Hacking attempt!");
}

use Phyxo\Plugin\Plugins;

$template->set_filenames(array('plugins' => 'plugins_installed.tpl'));

// should we display details on plugins?
if (isset($_GET['show_details'])) {
    if (1 == $_GET['show_details']) {
        $show_details = true;
    } else {
        $show_details = false;
    }

    $_SESSION['plugins_show_details'] = $show_details;
} elseif (!empty($_SESSION['plugins_show_details'])) {
    $show_details = $_SESSION['plugins_show_details'];
} else {
    $show_details = false;
}

$pwg_token = get_pwg_token();
$action_url = PLUGINS_BASE_URL.'&amp;section=installed&amp;plugin='.'%s'.'&amp;pwg_token='.$pwg_token;

$plugins = new Plugins($conn);

//--------------------------------------------------perform requested actions
if (isset($_GET['action']) and isset($_GET['plugin'])) {
    if (!$services['users']->isWebmaster()) {
        $page['errors'][] = l10n('Webmaster status is required.');
    } else {
        check_pwg_token();

        $page['errors'] = $plugins->performAction($_GET['action'], $_GET['plugin']);

        if (empty($page['errors'])) {
            if ($_GET['action'] == 'activate' or $_GET['action'] == 'deactivate') {
                $template->delete_compiled_templates();
                $persistent_cache->purge(true);
            }
            redirect(PLUGINS_BASE_URL.'&section=installed');
        }
    }
}

//--------------------------------------------------------Incompatible Plugins
if (isset($_GET['incompatible_plugins'])) {
    $incompatible_plugins = array();
    foreach ($plugins->getIncompatiblePlugins() as $plugin => $version) {
        if ($plugin == '~~expire~~') {
            continue;
        }
        $incompatible_plugins[] = $plugin;
    }
    echo json_encode($incompatible_plugins);
    exit;
}

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+

$plugins->sortFsPlugins('name');
$tpl_plugins = array();
$active_plugins = 0;

foreach($plugins->getFsPlugins() as $plugin_id => $fs_plugin) {
    if (isset($_SESSION['incompatible_plugins'][$plugin_id])
        && $fs_plugin['version'] != $_SESSION['incompatible_plugins'][$plugin_id]) {
        // Incompatible plugins must be reinitilized
        unset($_SESSION['incompatible_plugins']);
    }

    $tpl_plugin = array(
        'ID' => $plugin_id,
        'NAME' => $fs_plugin['name'],
        'VISIT_URL' => $fs_plugin['uri'],
        'VERSION' => $fs_plugin['version'],
        'DESC' => $fs_plugin['description'],
        'AUTHOR' => $fs_plugin['author'],
        'AUTHOR_URL' => @$fs_plugin['author uri'],
        'U_ACTION' => sprintf($action_url, $plugin_id),
    );

    if (isset($plugins->getDbPlugins()[$plugin_id])) {
        $tpl_plugin['STATE'] = $plugins->getDbPlugins()[$plugin_id]['state'];
    } else {
        $tpl_plugin['STATE'] = 'inactive';
    }

    if ($tpl_plugin['STATE'] == 'active') {
        $active_plugins++;
    }

    $tpl_plugins[] = $tpl_plugin;
}

$template->append('plugin_states', 'active');
$template->append('plugin_states', 'inactive');

$missing_plugin_ids = array_diff(
    array_keys($plugins->getDbPlugins()),
    array_keys($plugins->getFsPlugins())
);

if (count($missing_plugin_ids) > 0) {
    foreach ($missing_plugin_ids as $plugin_id) {
        $tpl_plugins[] = array(
            'NAME' => $plugin_id,
            'VERSION' => $plugins->getDbPlugins()[$plugin_id]['version'],
            'DESC' => l10n('ERROR: THIS PLUGIN IS MISSING BUT IT IS INSTALLED! UNINSTALL IT NOW.'),
            'U_ACTION' => sprintf($action_url, $plugin_id),
            'STATE' => 'missing',
        );
    }
    $template->append('plugin_states', 'missing');
}

// sort plugins by state then by name
function cmp($a, $b) {
    $s = array('missing' => 1, 'active' => 2, 'inactive' => 3);

    if($a['STATE'] == $b['STATE']) {
        return strcasecmp($a['NAME'], $b['NAME']);
    } else {
        return $s[$a['STATE']] >= $s[$b['STATE']];
    }
}
usort($tpl_plugins, 'cmp');

$template->assign(
    array(
        'plugins' => $tpl_plugins,
        'active_plugins' => $active_plugins,
        'PWG_TOKEN' => $pwg_token,
        'base_url' => PLUGINS_BASE_URL.'&amp;section=installed',
        'show_details' => $show_details,
    )
);

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugins');
