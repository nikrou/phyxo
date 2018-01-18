<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2018 Nicolas Roudaire        https://www.phyxo.net/ |
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

// Need upgrade?
global $conf;
include(PHPWG_THEMES_PATH.'elegant/admin/upgrade.inc.php');

load_language('theme.lang', PHPWG_THEMES_PATH.'elegant/');

$config = [
    'p_main_menu' => 'on', //on - off - disabled
    'p_pict_descr' => 'on', //on - off - disabled
    'p_pict_comment' => 'off', //on - off - disabled
];

if (isset($_POST['submit_elegant'])) {
    if (!empty($_POST['p_main_menu'])) {
        $config['p_main_menu'] = $_POST['p_main_menu'];
    }
    if (!empty($_POST['p_pict_descr'])) {
        $config['p_pict_descr'] = $_POST['p_pict_descr'];
    }
    if (!empty($_POST['p_pict_comment'])) {
        $config['p_pict_comment'] = $_POST['p_pict_comment'];
    }

    conf_update_param('elegant', $config, true);

    $page['infos'][] = l10n('Information data registered in database');
}

$template->set_filenames(array('theme_admin_content' => dirname(__FILE__) . '/admin.tpl'));

$template->assign('options', $conf['elegant']);

$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
