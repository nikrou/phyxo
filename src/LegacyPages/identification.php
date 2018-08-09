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

//--------------------------------------------------------------------- include
define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_FREE);

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_identification');

//-------------------------------------------------------------- identification
$redirect_to = '';
if (!empty($_GET['redirect'])) {
    $redirect_to = urldecode($_GET['redirect']);
    $page['errors'][] = \Phyxo\Functions\Language::l10n('You are not authorized to access the requested page');
}

if (isset($_POST['login'])) {
    if (!isset($_COOKIE[session_name()])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Cookies are blocked or not supported by your browser. You must enable cookies to connect.');
    } else {
        if ($conf['insensitive_case_logon'] == true) {
            $_POST['username'] = $services['users']->searchCaseUsername($_POST['username']);
        }

        $redirect_to = isset($_POST['redirect']) ? urldecode($_POST['redirect']) : '';
        $remember_me = isset($_POST['remember_me']) and $_POST['remember_me'] == 1;

        if ($services['users']->tryLogUser($_POST['username'], $_POST['password'], $remember_me)) {
            redirect(empty($redirect_to) ? \Phyxo\Functions\URL::get_gallery_home_url() : $redirect_to);
        } else {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Invalid password!');
        }
    }
}

//----------------------------------------------------- template initialization
//
// Start output of page
//
$title = \Phyxo\Functions\Language::l10n('Identification');
$page['body_id'] = 'theIdentificationPage';

$template->set_filenames(array('identification' => 'identification.tpl'));

$template->assign(
    array(
        'U_REDIRECT' => $redirect_to,

        'F_LOGIN_ACTION' => \Phyxo\Functions\URL::get_root_url() . 'identification.php',
        'authorize_remembering' => $conf['authorize_remembering'],
    )
);

if (!$conf['gallery_locked'] && $conf['allow_user_registration']) {
    $template->assign('U_REGISTER', \Phyxo\Functions\URL::get_root_url() . 'register.php');
}

if (!$conf['gallery_locked']) {
    $template->assign('U_LOST_PASSWORD', \Phyxo\Functions\URL::get_root_url() . 'password.php');
}

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!$conf['gallery_locked']
    && (!isset($themeconf['hide_menu_on']) || !in_array('theIdentificationPage', $themeconf['hide_menu_on']))) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

//----------------------------------------------------------- html code display
include(PHPWG_ROOT_PATH . 'include/page_header.php');
\Phyxo\Functions\Plugin::trigger_notify('loc_end_identification');
flush_page_messages();
include(PHPWG_ROOT_PATH . 'include/page_tail.php');
$template->pparse('identification');
