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
$services['users']->checkStatus(ACCESS_GUEST);

//----------------------------------------------------- template initialization
//
// Start output of page
//
$title = \Phyxo\Functions\Language::l10n('About Phyxo');
$page['body_id'] = 'theAboutPage';

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_about');

$template->set_filename('about', 'about.tpl');

$template->assign('ABOUT_MESSAGE', \Phyxo\Functions\Language::load_language('about.html', '', ['return' => true]));

$theme_about = \Phyxo\Functions\Language::load_language('about.html', PHPWG_THEMES_PATH . $user['theme'] . '/', ['return' => true]);
if ($theme_about !== false) {
    $template->assign('THEME_ABOUT', $theme_about);
}

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theAboutPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

\Phyxo\Functions\Utils::flush_page_messages();
