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

class t4u_Config
{
  private $config = array();
  protected static $instance; 

  public function __construct($plugin_dir, $plugin_name) {
    $this->plugin_dir = $plugin_dir;
    $this->plugin_name = $plugin_name;

    if (!file_exists($this->get_config_file_dir())) {
      mkgetdir($this->get_config_file_dir());
    }

    if (!file_exists($this->get_config_filename())) {
      $this->setDefaults();
      $this->save_config();
    }
  }

  public static function getInstance() { 
    if (!isset(self::$instance)) { 
      self::$instance = new t4u_Config(T4U_PLUGIN_ROOT, T4U_PLUGIN_NAME); 
    } 
    return self::$instance; 
  }


  public function load_config() {
    $x = file_get_contents($this->get_config_filename());
    if ($x!==false) {
      $c = unserialize($x);
      $this->config = $c;
    }
  }

  public function save_config() {
    file_put_contents($this->get_config_filename(), serialize($this->config));
  }

  private function get_config_file_dir() {
    return PHPWG_ROOT_PATH . $GLOBALS['conf']['data_location'].'plugins/';
  }

  private function get_config_filename() {
    return $this->get_config_file_dir().basename($this->plugin_dir).'.dat';
  }

  public function __set($key, $value) {
    $this->config[$key] = $value;
  }

  public function __get($key) {
    return isset($this->config[$key])?$this->config[$key]:null;
  }

  public function setPermission($permission, $value) {
    $this->config['permissions'][$permission] = $value;
  }

  public function getPermission($permission) {
    return isset($this->config['permissions'][$permission])?$this->config['permissions'][$permission]:null;
  }


  public function hasPermission($permission='add') {
    return 
      (($this->getPermission($permission)!='')
       and is_autorize_status(get_access_type_status($this->getPermission($permission))));
  }
  
  public static function plugin_admin_menu($menu) {
    $menu[] = array('NAME' => T4U_PLUGIN_NAME,
                    'URL' => get_admin_plugin_menu_link(T4U_PLUGIN_ROOT .'/admin.php')
                    );
    
    return $menu;
  }

  public static function get_admin_help($help_content, $page) {
    return load_language('help/'.$page.'.html', 
                         T4U_PLUGIN_ROOT .'/', 
                         array('return' => true) 
                         );
  }
  
  public function getActionUrl($action, $method='POST') {
    $ws = get_root_url();
    $ws .= 'ws.php?format=json&method=user_tags.tags.list';

    return $ws;
  }

  private function setDefaults() {
    include_once $this->plugin_dir.'/include/default_values.inc.php';
    
    foreach ($default_values as $key => $value) {
      if (empty($this->config[$key])) {
        $this->config[$key] = $value;
      }
    }
  }
}
