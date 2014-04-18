<?php
// +-----------------------------------------------------------------------+
// | User Tags  - a plugin for Phyxo                                       |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2010-2014 Nicolas Roudaire        http://www.nikrou.net  |
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

function plugin_install($plugin_id, $plugin_version, &$errors) {
}

function plugin_activate($plugin_id, $plugin_version, &$errors) {
}

function plugin_deactivate($plugin_id) { 
}

function plugin_uninstall($plugin_id) {
    $config_file = PHPWG_ROOT_PATH . $GLOBALS['conf']['data_location'] . 'plugins/';
    $config_file .= basename(dirname(__FILE__)).'.dat';
    if (file_exists($config_file)) {
        unlink($config_file);
    }
}
