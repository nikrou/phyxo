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
use App\Repository\ThemeRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;

// +-----------------------------------------------------------------------+
// |                              groups list                              |
// +-----------------------------------------------------------------------+

$groups = [];

$result = (new GroupRepository($conn))->findAll('ORDER BY name ASC');
while ($row = $conn->db_fetch_assoc($result)) {
    $groups[$row['id']] = $row['name'];
}

// +-----------------------------------------------------------------------+
// | template                                                              |
// +-----------------------------------------------------------------------+

$result = (new UserRepository($conn))->getUserInfosList();
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
    $result = (new UserInfosRepository($conn))->findByStatuses(['webmaster', 'admin']);
    $protected_users = array_merge($protected_users, $conn->result2array($result, null, 'user_id'));
}

$template->assign(
    [
        'F_ADD_ACTION' => USERS_BASE_URL . '&amp;section=list',
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
        'NB_IMAGE_PAGE' => $default_user['nb_image_page'],
        'RECENT_PERIOD' => $default_user['recent_period'],
        'theme_options' => $conn->result2array((new ThemeRepository($conn))->findAll(), 'id', 'name'),
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
