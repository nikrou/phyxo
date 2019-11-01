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

// Includes
require_once(__DIR__ . '/../include/config.php');

Language::load_language('theme.lang', __DIR__ . '/../', ['language' => $user['language']]);

$themeconfig = new \Treflez\Config($conf);
// Save settings
if (isset($_POST['_settings'])) {  // @TODO : need to find a better way to use POST paramters
    $themeconfig->fromPost($_POST);
    $themeconfig->save();
}

// Assign the template contents to ADMIN_CONTENT
$tpl_params['theme_config'] = $themeconfig;

// Add our template to the global template
$template_filename = dirname(__FILE__) . '/template/settings.tpl';
