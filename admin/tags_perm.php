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

if (!defined('PHPWG_ROOT_PATH')) {
    die ('Hacking attempt!');
}

$status_options[null] = '----------';
foreach (get_enums(USER_INFOS_TABLE, 'status') as $status) {
    $status_options[$status] = l10n('user_status_'.$status);
}

$Permissions = array();
$Permissions['add'] = $conf['tags_permission_add'];
$Permissions['delete'] = $conf['tags_permission_delete'];
$Permissions['existing_tags_only'] = $conf['tags_existing_tags_only'];

if (!empty($_POST['submit'])) {
    if (isset($_POST['permission_add']) && isset($status_options[$_POST['permission_add']])) {
        $Permissions['add'] = $_POST['permission_add'];
        conf_update_param('tags_permission_add', $Permissions['add']);
    }

    $Permissions['existing_tags_only'] = empty($_POST['existing_tags_only'])?0:1;
    conf_update_param('tags_existing_tags_only', $Permissions['existing_tags_only']);

    if (isset($_POST['permission_delete']) && isset($status_options[$_POST['permission_delete']])) {
        $Permissions['delete'] = $_POST['permission_delete'];
        conf_update_param('tags_permission_delete', $_POST['permission_delete']);
    }
}

$template->assign('PERMISSIONS', $Permissions);
$template->assign('STATUS_OPTIONS', $status_options);
