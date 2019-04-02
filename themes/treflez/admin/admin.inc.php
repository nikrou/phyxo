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

use Phyxo\Functions\Language;
use Phyxo\Functions\URL;

// Includes
require_once(__DIR__ . '/../include/config.php');

Language::load_language('theme.lang', PHPWG_THEMES_PATH . '/treflez/', ['language' => $user['language']]);

// Constants
define('THEME_ID', basename(dirname(dirname(__FILE__))));
define('ADMIN_PATH', URL::get_root_url() . 'admin.php?page=theme&theme=' . THEME_ID);

$themeconfig = new \Treflez\Config($conf);
// Save settings
if (isset($_POST['_settings'])) {
    $themeconfig->fromPost($_POST);
    $themeconfig->save();
}

// Add our template to the global template
$template->set_filename('theme_admin_content', dirname(__FILE__) . '/template/settings.tpl');

// Assign the template contents to ADMIN_CONTENT
$template->assign('theme_config', $themeconfig);
$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
