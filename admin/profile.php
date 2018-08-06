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

if (!defined("PHPWG_ROOT_PATH")) {
    die("Hacking attempt!");
}

$edit_user = $services['users']->buildUser($_GET['user_id'], false);

if (!empty($_POST)) {
    check_pwg_token();
}

include_once(PHPWG_ROOT_PATH . 'profile.php');

$errors = array();
save_profile_from_post($edit_user, $errors);

load_profile_in_template(
    \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=profile&amp;user_id=' . $edit_user['id'],
    \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=user_list',
    $edit_user
);
$page['errors'] = array_merge($page['errors'], $errors);
