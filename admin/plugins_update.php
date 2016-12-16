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

use Phyxo\Update\Updates;

$show_reset = false;
$conf['updates_ignored'] = json_decode($conf['updates_ignored'], true);

$autoupdate = new Updates($conn, $page['page']);
try {
    $autoupdate->getServerExtensions();
    $server_plugins = $autoupdate->getType('plugins')->getServerPlugins();

    if (count($server_plugins)>0) {
        foreach($autoupdate->getType('plugins')->getFsPlugins() as $extension_id => $fs_extension) {
            if (!isset($fs_extension['extension']) || !isset($server_plugins[$fs_extension['extension']])) {
                continue;
            }

            $extension_info = $server_plugins[$fs_extension['extension']];

            if (!safe_version_compare($fs_extension['version'], $extension_info['revision_name'], '>=')) {
                $template->append(
                    'update_plugins',
                    array(
                        'ID' => $extension_info['extension_id'],
                        'REVISION_ID' => $extension_info['revision_id'],
                        'EXT_ID' => $extension_id,
                        'EXT_NAME' => $fs_extension['name'],
                        'EXT_URL' => PEM_URL.'/extension_view.php?eid='.$extension_info['extension_id'],
                        'EXT_DESC' => trim($extension_info['extension_description'], " \n\r"),
                        'REV_DESC' => trim($extension_info['revision_description'], " \n\r"),
                        'CURRENT_VERSION' => $fs_extension['version'],
                        'NEW_VERSION' => $extension_info['revision_name'],
                        'AUTHOR' => $extension_info['author_name'],
                        'DOWNLOADS' => $extension_info['extension_nb_downloads'],
                        'URL_DOWNLOAD' => $extension_info['download_url'] . '&amp;origin=piwigo_download',
                        'IGNORED' => !empty($conf['updates_ignored']['plugins']) && in_array($extension_id, $conf['updates_ignored']['plugins']),
                    )
                );
            }
        }

        if (!empty($conf['updates_ignored']['plugins'])) {
            $show_reset = true;
        }
    }

    $template->assign('SHOW_RESET', $show_reset);
    $template->assign('PWG_TOKEN', get_pwg_token());
    $template->assign('EXT_TYPE', $page['page'] == 'updates' ? 'extensions' : $page['page']);
} catch (\Exception $e) {
    $page['errors'][] = l10n('Can\'t connect to server.');
    $template->append(
        array('error' => $page['error'])
    );
}

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugins');
