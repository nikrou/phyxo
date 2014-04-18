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

global $__t4u_autoload;
$__t4u_autoload['t4u_Ws'] = T4U_PLUGIN_ROOT . '/include/t4u_ws.class.php';
$__t4u_autoload['t4u_Config'] = T4U_PLUGIN_ROOT . '/include/t4u_config.class.php';
$__t4u_autoload['t4u_Content'] = T4U_PLUGIN_ROOT . '/include/t4u_content.class.php';


if (function_exists('spl_autoload_register')) {
  spl_autoload_register('t4u_autoload');
} else {
  function __autoload($name) {
    t4u_autoload($name);
  }
}

function t4u_autoload($name) {
  global $__t4u_autoload;

  if (!empty($__t4u_autoload[$name])) {
    require_once $__t4u_autoload[$name];
  }
}
