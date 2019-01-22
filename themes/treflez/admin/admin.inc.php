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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

use Phyxo\TabSheet\TabSheet;
use Phyxo\Functions\Language;
use Phyxo\Functions\URL;

// Includes
require_once(__DIR__ . '/../include/config.php');

Language::load_language('theme.lang', PHPWG_THEMES_PATH . 'treflez/');

// Constants
define('THEME_ID', basename(dirname(dirname(__FILE__))));
define('ADMIN_PATH', URL::get_root_url() . 'admin.php?page=theme&theme=' . THEME_ID);
define('TAB_SETTINGS', 'settings');
define('TAB_ABOUT', 'about');
define('TAB_URL', URL::get_root_url() . 'admin/index.php?page=theme&amp;theme=treflez');

// Get current tab
$page['tab'] = isset($_GET['tab']) ? $_GET['tab'] : $page['tab'] = TAB_SETTINGS;
if (!in_array($page['tab'], [TAB_SETTINGS, TAB_ABOUT])) {
    $page['tab'] = TAB_SETTINGS;
}


$themeconfig = new \Treflez\Config($conf);
// Save settings
if ($page['tab'] == TAB_SETTINGS) {
    if (isset($_POST['_settings'])) {
        $themeconfig->fromPost($_POST);
        $themeconfig->save();
    }
}
// TabSheet
$tabsheet = new TabSheet();
$tabsheet->add(TAB_SETTINGS, \Phyxo\Functions\Language::l10n('Settings'), TAB_URL . '&amp;tab=' . TAB_SETTINGS);
$tabsheet->add(TAB_ABOUT, \Phyxo\Functions\Language::l10n('About'), TAB_URL . '&amp;tab=' . TAB_ABOUT);
$tabsheet->select($page['tab']);
$template->assign(['tabsheet' => $tabsheet]);

// Add our template to the global template
$template->set_filename('theme_admin_content', dirname(__FILE__) . '/template/' . $page['tab'] . '.tpl');

// Assign the template contents to ADMIN_CONTENT
$template->assign('theme_config', $themeconfig);
$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
