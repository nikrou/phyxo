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

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $prefixeTable, $conf;

$default_config = array(
    'p_main_menu' => 'on', //on - off - disabled
    'p_pict_descr' => 'on', //on - off - disabled
    'p_pict_comment' => 'off', //on - off - disabled
);

if (!isset($conf['elegant'])) {
    conf_update_param('elegant', $default_config, true);
} else {
    $config = json_decode($conf['elegant'], true);
    if (count($config)!=3) {
        $new_config = array_merge($default_config, $config);

        conf_update_param('elegant', $new_config, true);
    }
}
