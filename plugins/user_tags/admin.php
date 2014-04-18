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

load_language('plugin.lang', T4U_PLUGIN_LANG);

$me = t4u_Config::getInstance();
$save_config = false;

$status_options[null] = '----------';
foreach (get_enums(USER_INFOS_TABLE, 'status') as $status) {
  $status_options[$status] = l10n('user_status_'.$status);
}

if (!empty($_POST['submit'])) {
  if (isset($_POST['permission_add']) && isset($status_options[$_POST['permission_add']]) 
      && $_POST['permission_add']!=$me->getPermission('add')) {
    $me->setPermission('add', $_POST['permission_add']);
    $page['infos'][] = l10n('Add permission updated');
    $save_config = true;
  }

  if (!empty($_POST['existing_tags_only']) 
      && $_POST['existing_tags_only']!=$me->getPermission('existing_tags_only')) {
    $me->setPermission('existing_tags_only', 1);
    $save_config = true;
  } elseif (!isset($_POST['existing_tags_only']) && $me->getPermission('existing_tags_only')!=0) {
    $me->setPermission('existing_tags_only', 0);
    $save_config = true;    
  }
  
  if (isset($_POST['permission_delete']) && isset($status_options[$_POST['permission_delete']]) 
      && $_POST['permission_delete']!=$me->getPermission('delete')) {
    $me->setPermission('delete', $_POST['permission_delete']);
    $page['infos'] = l10n('Delete permission updated');
    $save_config = true;
  }

  if ($save_config) {
    $me->save_config();
  }
}

$template->set_filenames(array('plugin_admin_content' => T4U_TEMPLATE . '/admin.tpl'));
$template->assign('T4U_CSS', T4U_CSS);

$template->assign('T4U_PERMISSION_ADD', $me->getPermission('add'));
$template->assign('T4U_PERMISSION_DELETE', $me->getPermission('delete'));
$template->assign('T4U_EXISTING_TAG_ONLY', $me->getPermission('existing_tags_only'));
$template->assign('STATUS_OPTIONS', $status_options);
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');

$template->assign('U_HELP', get_root_url().'admin/popuphelp.php?page=readme');
