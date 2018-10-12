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

use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_FREE);

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_password');

// +-----------------------------------------------------------------------+
// | Functions                                                             |
// +-----------------------------------------------------------------------+

/**
 * checks the validity of input parameters, fills $page['errors'] and
 * $page['infos'] and send an email with confirmation link
 *
 * @return bool (true if email was sent, false otherwise)
 */
function process_password_request()
{
    global $page, $conf, $conn, $services;

    if (empty($_POST['username_or_email'])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Invalid username or email');
        return false;
    }

    $user_id = $services['users']->getUserIdByEmail($_POST['username_or_email']);

    if (!is_numeric($user_id)) {
        $user_id = $services['users']->getUserId($_POST['username_or_email']);
    }

    if (!is_numeric($user_id)) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Invalid username or email');
        return false;
    }

    $userdata = $services['users']->getUserData($user_id, false);

    // password request is not possible for guest/generic users
    $status = $userdata['status'];
    if ($services['users']->isGuest($status) or $services['users']->isGeneric($status)) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Password reset is not allowed for this user');
        return false;
    }

    if (empty($userdata['email'])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n(
            'User "%s" has no email address, password reset is not possible',
            $userdata['username']
        );
        return false;
    }

    if (empty($userdata['activation_key'])) {
        $activation_key = get_user_activation_key();

        (new UserInfosRepository($conn))->updateUserInfos(['activation_key' => $activation_key], $user_id);

        $userdata['activation_key'] = $activation_key;
    }

    \Phyxo\Functions\URL::set_make_full_url();

    $message = \Phyxo\Functions\Language::l10n('Someone requested that the password be reset for the following user account:') . "\r\n\r\n";
    $message .= \Phyxo\Functions\Language::l10n(
        'Username "%s" on gallery %s',
        $userdata['username'],
        \Phyxo\Functions\URL::get_gallery_home_url()
    );
    $message .= "\r\n\r\n";
    $message .= \Phyxo\Functions\Language::l10n('To reset your password, visit the following address:') . "\r\n";
    $message .= \Phyxo\Functions\URL::get_gallery_home_url() . '/password.php?key=' . $userdata['activation_key'] . "\r\n\r\n";
    $message .= \Phyxo\Functions\Language::l10n('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n";

    \Phyxo\Functions\URL::unset_make_full_url();

    $message = \Phyxo\Functions\Plugin::trigger_change('render_lost_password_mail_content', $message);

    $email_params = [
        'subject' => '[' . $conf['gallery_title'] . '] ' . \Phyxo\Functions\Language::l10n('Password Reset'),
        'content' => $message,
        'email_format' => 'text/plain',
    ];

    if (\Phyxo\Functions\Mail::mail($userdata['email'], $email_params)) {
        $page['infos'][] = \Phyxo\Functions\Language::l10n('Check your email for the confirmation link');
        return true;
    } else {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Error sending email');
        return false;
    }
}


/**
 * checks the passwords, checks that user is allowed to reset his password,
 * update password, fills $page['errors'] and $page['infos'].
 *
 * @return bool (true if password was reset, false otherwise)
 */
function reset_password()
{
    global $page, $user, $conf, $conn, $services;

    if ($_POST['use_new_pwd'] != $_POST['passwordConf']) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('The passwords do not match');
        return false;
    }

    if (isset($_GET['key'])) {
        try {
            $user_id = $services['users']->checkPasswordResetKey($_GET['key']);
        } catch (\Exception $e) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Invalid key');
        }
    } else {
        // we check the currently logged in user
        if ($services['users']->isGuest() or $services['users']->isGeneric()) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Password reset is not allowed for this user');
            return false;
        }

        $user_id = $user['id'];
    }

    (new UserRepository($conn))->updateUser(['password' => $services['users']->passwordHash($_POST['use_new_pwd'])], $user_id);
    $page['infos'][] = \Phyxo\Functions\Language::l10n('Your password has been reset');

    if (isset($_GET['key'])) {
        $page['infos'][] = '<a href="' . \Phyxo\Functions\URL::get_root_url() . 'identification.php">' . \Phyxo\Functions\Language::l10n('Login') . '</a>';
    } else {
        $page['infos'][] = '<a href="' . \Phyxo\Functions\URL::get_gallery_home_url() . '">' . \Phyxo\Functions\Language::l10n('Return to home page') . '</a>';
    }

    return true;
}

// +-----------------------------------------------------------------------+
// | Process form                                                          |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit'])) {
    \Phyxo\Functions\Utils::check_token();

    if ('lost' == $_GET['action']) {
        if (process_password_request()) {
            $page['action'] = 'none';
        }
    }

    if ('reset' == $_GET['action']) {
        if (reset_password()) {
            $page['action'] = 'none';
        }
    }
}

// +-----------------------------------------------------------------------+
// | key and action                                                        |
// +-----------------------------------------------------------------------+

// a connected user can't reset the password from a mail
if (isset($_GET['key']) and !$services['users']->isGuest()) {
    unset($_GET['key']);
}

if (isset($_GET['key'])) {
    try {
        $user_id = $services['users']->checkPasswordResetKey($_GET['key']);
        $userdata = $services['users']->getUserData($user_id, false);
        $page['username'] = $userdata['username'];
        $template->assign('key', $_GET['key']);

        if (!isset($page['action'])) {
            $page['action'] = 'reset';
        }

    } catch (\Exception $e) {
        $page['errors'][] = $e->getMessage();
        $page['action'] = 'none';
    }
}

if (!isset($page['action'])) {
    if (!isset($_GET['action'])) {
        $page['action'] = 'lost';
    } elseif (in_array($_GET['action'], ['lost', 'reset', 'none'])) {
        $page['action'] = $_GET['action'];
    }
}

if ('reset' == $page['action'] and !isset($_GET['key']) and ($services['users']->isGuest() or $services['users']->isGeneric())) {
    \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url());
}

if ('lost' == $page['action'] and !$services['users']->isGuest()) {
    \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url());
}

// +-----------------------------------------------------------------------+
// | template initialization                                               |
// +-----------------------------------------------------------------------+

$title = \Phyxo\Functions\Language::l10n('Password Reset');
if ('lost' == $page['action']) {
    $title = \Phyxo\Functions\Language::l10n('Forgot your password?');

    if (isset($_POST['username_or_email'])) {
        // @TODO: remove that stupid code
        $template->assign('username_or_email', htmlspecialchars(stripslashes($_POST['username_or_email'])));
    }
}

$template->assign(
    [
        'title' => $title,
        'form_action' => \Phyxo\Functions\URL::get_root_url() . 'password.php',
        'action' => $page['action'],
        'username' => isset($page['username']) ? $page['username'] : $user['username'],
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    ]
);


// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('thePasswordPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+

\Phyxo\Functions\Plugin::trigger_notify('loc_end_password');
\Phyxo\Functions\Utils::flush_page_messages();
