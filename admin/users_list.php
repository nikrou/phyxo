<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
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

if (!defined("USERS_BASE_URL")) {
    die ("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                              groups list                              |
// +-----------------------------------------------------------------------+

$groups = array();

$query = 'SELECT id, name FROM '.GROUPS_TABLE.' ORDER BY name ASC;';
$result = $conn->db_query($query);

while ($row = $conn->db_fetch_assoc($result)) {
    $groups[$row['id']] = $row['name'];
}

// +-----------------------------------------------------------------------+
// | template                                                              |
// +-----------------------------------------------------------------------+

$query = 'SELECT DISTINCT u.'.$conf['user_fields']['id'].' AS id,u.'.$conf['user_fields']['username'].' AS username,';
$query .= 'u.'.$conf['user_fields']['email'].' AS email,ui.status,ui.enabled_high,';
$query .= 'ui.level FROM '.USERS_TABLE.' AS u';
$query .= ' LEFT JOIN '.USER_INFOS_TABLE.' AS ui ON u.'.$conf['user_fields']['id'].' = ui.user_id';
$query .= ' WHERE u.'.$conf['user_fields']['id'].' > 0;';

$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $users[] = $row;
    $user_ids[] = $row['id'];
}

$template->assign(
    array(
        'users' => $users,
        'all_users' => join(',', $user_ids),
        'ACTIVATE_COMMENTS' => $conf['activate_comments'],
        'Double_Password' => $conf['double_password_type_in_admin']
    )
);

$default_user = $services['users']->getDefaultUserInfo(true);

$protected_users = array(
    $user['id'],
    $conf['guest_id'],
    $conf['default_user_id'],
    $conf['webmaster_id'],
);

// an admin can't delete other admin/webmaster
if ('admin' == $user['status']) {
    $query = 'SELECT user_id FROM '.USER_INFOS_TABLE.' WHERE status '.$conn->in(array('webmaster', 'admin'));
    $protected_users = array_merge($protected_users, $conn->query2array($query, null, 'user_id'));
}

$template->assign(
    array(
        'F_ADD_ACTION' => USERS_BASE_URL.'&amp;section=list',
        'PWG_TOKEN' => get_pwg_token(),
        'NB_IMAGE_PAGE' => $default_user['nb_image_page'],
        'RECENT_PERIOD' => $default_user['recent_period'],
        'theme_options' => get_pwg_themes(),
        'theme_selected' => $services['users']->getDefaultTheme(),
        'language_options' => get_languages(),
        'language_selected' => $services['users']->getDefaultLanguage(),
        'association_options' => $groups,
        'protected_users' => implode(',', array_unique($protected_users)),
        'guest_user' => $conf['guest_id'],
    )
);

// Status options
foreach ($conn->get_enums(USER_INFOS_TABLE, 'status') as $status) {
    $label_of_status[$status] = l10n('user_status_'.$status);
}

$pref_status_options = $label_of_status;

// a simple "admin" can set/remove statuses webmaster/admin
if ('admin' == $user['status']) {
    unset($pref_status_options['webmaster']);
    unset($pref_status_options['admin']);
}

$template->assign('label_of_status', $label_of_status);
$template->assign('pref_status_options', $pref_status_options);
$template->assign('pref_status_selected', 'normal');

// user level options
foreach ($conf['available_permission_levels'] as $level) {
    $level_options[$level] = l10n(sprintf('Level %d', $level));
}
$template->assign('level_options', $level_options);
$template->assign('level_selected', $default_user['level']);

$template->assign_var_from_handle('ADMIN_CONTENT', 'users');
