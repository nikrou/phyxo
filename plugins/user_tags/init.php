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

define('T4U_PLUGIN_ROOT', dirname(__FILE__));

include_once T4U_PLUGIN_ROOT . "/include/constants.inc.php";
include_once T4U_PLUGIN_ROOT . "/include/autoload.inc.php";

$plugin_config = t4u_Config::getInstance();
$plugin_config->load_config();

if (defined('IN_ADMIN')) { 
  add_event_handler('get_admin_plugin_menu_links', 
                    't4u_Config::plugin_admin_menu'
                    ); 
  add_event_handler('get_popup_help_content', 
                    't4u_Config::get_admin_help',
                    EVENT_HANDLER_PRIORITY_NEUTRAL,
                    2
                    );
} else {
  include_once T4U_PLUGIN_ROOT . '/public.php';
}

set_plugin_data($plugin['id'], $plugin_config);
