<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

if (!defined("THEMES_BASE_URL")) {
    die ("Hacking attempt!");
}

use Phyxo\Update\Updates;

$autoupdate = new Updates($conn, 'themes');

$show_reset = false;
$conf['updates_ignored'] = json_decode($conf['updates_ignored'], true);

try {
    $autoupdate->getServerExtensions();
    $server_themes = $autoupdate->getType('themes')->getServerThemes();

    if (count($server_themes)>0) {
        foreach($autoupdate->getType('themes')->getFsThemes() as $extension_id => $fs_extension) {
            if (!isset($fs_extension['extension']) or !isset($server_themes[$fs_extension['extension']])) {
                continue;
            }

            $ext_info = $server_themes[$fs_extension['extension']];

            if (!safe_version_compare($fs_extension['version'], $ext_info['revision_name'], '>=')) {
                $template->append(
                    'update_themes',
                    array(
                        'ID' => $ext_info['extension_id'],
                        'REVISION_ID' => $ext_info['revision_id'],
                        'EXT_ID' => $extension_id,
                        'EXT_NAME' => $fs_extension['name'],
                        'EXT_URL' => PEM_URL.'/extension_view.php?eid='.$ext_info['extension_id'],
                        'EXT_DESC' => trim($ext_info['extension_description'], " \n\r"),
                        'REV_DESC' => trim($ext_info['revision_description'], " \n\r"),
                        'CURRENT_VERSION' => $fs_extension['version'],
                        'NEW_VERSION' => $ext_info['revision_name'],
                        'AUTHOR' => $ext_info['author_name'],
                        'DOWNLOADS' => $ext_info['extension_nb_downloads'],
                        'URL_DOWNLOAD' => $ext_info['download_url'] . '&amp;origin=piwigo_download',
                        'IGNORED' => !empty($conf['updates_ignored']['themes']) && in_array($extension_id, $conf['updates_ignored']['themes']),
                    )
                );
            }
        }

        if (!empty($conf['updates_ignored']['themes'])) {
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

$template->assign_var_from_handle('ADMIN_CONTENT', 'themes');
