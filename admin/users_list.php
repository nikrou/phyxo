<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!defined("USERS_BASE_URL")) {
    die("Hacking attempt!");
}

use App\Repository\LanguageRepository;

// +-----------------------------------------------------------------------+
// |                              groups list                              |
// +-----------------------------------------------------------------------+

$groups = [];

$query = 'SELECT id, name FROM ' . GROUPS_TABLE . ' ORDER BY name ASC;';
$result = $conn->db_query($query);

while ($row = $conn->db_fetch_assoc($result)) {
    $groups[$row['id']] = $row['name'];
}

// +-----------------------------------------------------------------------+
// | template                                                              |
// +-----------------------------------------------------------------------+

$query = 'SELECT DISTINCT u.' . $conf['user_fields']['id'] . ' AS id,u.' . $conf['user_fields']['username'] . ' AS username,';
$query .= 'u.' . $conf['user_fields']['email'] . ' AS email,ui.status,ui.enabled_high,';
$query .= 'ui.level FROM ' . USERS_TABLE . ' AS u';
$query .= ' LEFT JOIN ' . USER_INFOS_TABLE . ' AS ui ON u.' . $conf['user_fields']['id'] . ' = ui.user_id';
$query .= ' WHERE u.' . $conf['user_fields']['id'] . ' > 0;';

$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $users[] = $row;
    $user_ids[] = $row['id'];
}

$template->assign(
    [
        'users' => $users,
        'all_users' => join(',', $user_ids),
        'ACTIVATE_COMMENTS' => $conf['activate_comments'],
        'Double_Password' => $conf['double_password_type_in_admin']
    ]
);

$default_user = $services['users']->getDefaultUserInfo(true);

$protected_users = [
    $user['id'],
    $conf['guest_id'],
    $conf['default_user_id'],
    $conf['webmaster_id'],
];

// an admin can't delete other admin/webmaster
if ('admin' == $user['status']) {
    $query = 'SELECT user_id FROM ' . USER_INFOS_TABLE . ' WHERE status ' . $conn->in(['webmaster', 'admin']);
    $protected_users = array_merge($protected_users, $conn->query2array($query, null, 'user_id'));
}

$template->assign(
    [
        'F_ADD_ACTION' => USERS_BASE_URL . '&amp;section=list',
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
        'NB_IMAGE_PAGE' => $default_user['nb_image_page'],
        'RECENT_PERIOD' => $default_user['recent_period'],
        'theme_options' => \Phyxo\Functions\Theme::get_themes(),
        'theme_selected' => $services['users']->getDefaultTheme(),
        'language_options' => $conn->result2array((new LanguageRepository($conn))->findAll(), 'id', 'name'),
        'language_selected' => $services['users']->getDefaultLanguage(),
        'association_options' => $groups,
        'protected_users' => implode(',', array_unique($protected_users)),
        'guest_user' => $conf['guest_id'],
    ]
);

// Status options
foreach ($conn->get_enums(USER_INFOS_TABLE, 'status') as $status) {
    $label_of_status[$status] = \Phyxo\Functions\Language::l10n('user_status_' . $status);
}

$pref_status_options = $label_of_status;

// a simple "admin" can set/remove statuses webmaster/admin
if ('admin' == $user['status']) {
    unset($pref_status_options['webmaster'], $pref_status_options['admin']);
}

$template->assign('label_of_status', $label_of_status);
$template->assign('pref_status_options', $pref_status_options);
$template->assign('pref_status_selected', 'normal');

// user level options
foreach ($conf['available_permission_levels'] as $level) {
    $level_options[$level] = \Phyxo\Functions\Language::l10n(sprintf('Level %d', $level));
}
$template->assign('level_options', $level_options);
$template->assign('level_selected', $default_user['level']);
