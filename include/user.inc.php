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

// by default we start with guest
$user['id'] = $conf['guest_id'];

if (isset($_COOKIE[session_name()])) {
    if (isset($_GET['act']) and $_GET['act'] == 'logout') { // logout
        $services['users']->logoutUser();
        \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url());
    } elseif (!empty($_SESSION['pwg_uid'])) {
        $user['id'] = $_SESSION['pwg_uid'];
    }
}

// Now check the auto-login
if ($user['id'] == $conf['guest_id']) {
    $services['users']->autoLogin();
}

$user = $services['users']->buildUser($user['id'], (defined('IN_ADMIN') and IN_ADMIN) ? false : true); // use cache ?

if ($conf['browser_language'] and ($services['users']->isGuest() or $services['users']->isGeneric())) {
    \Phyxo\Functions\Language::get_browser_language($user['language']);
}
\Phyxo\Functions\Plugin::trigger_notify('user_init', $user);
