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

//----------------------------------------------------------- include
define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_FREE);

//----------------------------------------------------------- user registration

if (!$conf['allow_user_registration']) {
    page_forbidden('User registration closed');
}

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_register');

if (isset($_POST['submit'])) {
    if (empty($_POST['key']) || !verify_ephemeral_key($_POST['key'])) {
        t_status_header(403);
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Invalid/expired form key');
    }

    if (empty($_POST['password'])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Password is missing. Please enter the password.');
    } elseif (empty($_POST['password_conf'])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Password confirmation is missing. Please confirm the chosen password.');
    } elseif ($_POST['password'] != $_POST['password_conf']) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('The passwords do not match');
    }

    $services['users']->registerUser(
        $_POST['login'],
        $_POST['password'],
        $_POST['mail_address'],
        true,
        $page['errors'],
        isset($_POST['send_password_by_mail'])
    );

    if (count($page['errors']) == 0) {
        // email notification
        if (isset($_POST['send_password_by_mail']) and email_check_format($_POST['mail_address'])) {
            $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Successfully registered, you will soon receive an email with your connection settings. Welcome!');
        }

        // log user and redirect
        $user_id = $services['users']->getUserId($_POST['login']);
        $services['users']->logUser($user_id, false);
        redirect(\Phyxo\Functions\URL::make_index_url());
    }
    $registration_post_key = get_ephemeral_key(2);
} else {
    $registration_post_key = get_ephemeral_key(6);
}

$login = !empty($_POST['login']) ? htmlspecialchars(stripslashes($_POST['login'])) : '';
$email = !empty($_POST['mail_address']) ? htmlspecialchars(stripslashes($_POST['mail_address'])) : '';

//----------------------------------------------------- template initialization
//
// Start output of page
//
$title = \Phyxo\Functions\Language::l10n('Registration');
$page['body_id'] = 'theRegisterPage';

$template->set_filenames(array('register' => 'register.tpl'));
$template->assign(array(
    'U_HOME' => \Phyxo\Functions\URL::make_index_url(),
    'F_KEY' => $registration_post_key,
    'F_ACTION' => 'register.php',
    'F_LOGIN' => $login,
    'F_EMAIL' => $email,
    'obligatory_user_mail_address' => $conf['obligatory_user_mail_address'],
));

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theRegisterPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

include(PHPWG_ROOT_PATH . 'include/page_header.php');
\Phyxo\Functions\Plugin::trigger_notify('loc_end_register');
flush_page_messages();
include(PHPWG_ROOT_PATH . 'include/page_tail.php');
$template->pparse('register');
