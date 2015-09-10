<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

if (!isset($conf['elegant'])) {
    $config = array(
        'p_main_menu' => 'on',//on - off - disabled
        'p_pict_descr' => 'on',//on - off - disabled
        'p_pict_comment' => 'off',//on - off - disabled
    );

    conf_update_param('elegant', json_encode($config));
    load_conf_from_db();
} elseif (count($conff = json_decode($conf['elegant'], true))!=3) {
    $config = array(
        'p_main_menu' => (isset($conff['p_main_menu'])) ? $conff['p_main_menu'] :'on',
        'p_pict_descr' => (isset($conff['p_pict_descr'])) ? $conff['p_pict_descr'] :'on',
        'p_pict_comment' => (isset($conff['p_pict_comment'])) ? $conff['p_pict_comment'] :'off',
    );
    conf_update_param('elegant', json_encode($config));
    load_conf_from_db();
}
