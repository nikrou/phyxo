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

include_once(__DIR__ . '/../../include/common.inc.php');

//----------------------------------------------------- template initialization
//
// Start output of page
//
$title = \Phyxo\Functions\Language::l10n('About Phyxo');
\Phyxo\Functions\Plugin::trigger_notify('loc_begin_about');

$template->assign('ABOUT_MESSAGE', \Phyxo\Functions\Language::load_language('about.html', '', ['language' => $user['language'], 'return' => true]));

$theme_about = \Phyxo\Functions\Language::load_language('about.html', PHPWG_THEMES_PATH . '/' . $user['theme'] . '/', ['language' => $user['language'], 'return' => true]);
if ($theme_about !== false) {
    $template->assign('THEME_ABOUT', $theme_about);
}

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theAboutPage', $themeconf['hide_menu_on'])) {
    include(__DIR__ . '/menubar.inc.php');
}

\Phyxo\Functions\Utils::flush_page_messages();
