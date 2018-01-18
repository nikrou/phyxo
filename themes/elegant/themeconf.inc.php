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

/*
Theme Name: Elegant
Version: 2.9.2
Description: Dark background, grayscale.
Author: Nicolas Roudaire
Author URI: https://www.phyxo.net

The theme is based on the original one for piwigo.
*/

$themeconf = array(
    'name'  => 'elegant',
    'parent' => 'legacy',
    'local_head'  => 'local_head.tpl'
);

// Need upgrade?
global $conf;
include(PHPWG_THEMES_PATH.'elegant/admin/upgrade.inc.php');

add_event_handler('init', 'set_config_values_elegant');
function set_config_values_elegant() {
    global $conf, $template;
    $config = json_decode($conf['elegant'], true);
    $template->assign('elegant', $config);
}
