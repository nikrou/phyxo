<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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

$config_send= array();

if(isset($_POST['submit_elegant'])) {
    $config_send['p_main_menu'] = (isset($_POST['p_main_menu']) and !empty($_POST['p_main_menu'])) ? $_POST['p_main_menu'] : 'on';
    $config_send['p_pict_descr'] = (isset($_POST['p_pict_descr']) and !empty($_POST['p_pict_descr'])) ? $_POST['p_pict_descr'] : 'on';
    $config_send['p_pict_comment'] = (isset($_POST['p_pict_comment']) and !empty($_POST['p_pict_comment'])) ? $_POST['p_pict_comment'] : 'off';

    conf_update_param('elegant', json_encode($conf['elegant']));

    $page['infos'][] = l10n('Information data registered in database');
}

$template->set_filenames(array('theme_admin_content' => dirname(__FILE__) . '/admin.tpl'));
$template->assign('options', json_decode($conf['elegant'], true));
$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
