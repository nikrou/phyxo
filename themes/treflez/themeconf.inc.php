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

/*
Theme Name: Treflez
Version: 0.1.0
Description: Responsive theme
Author: Nicolas Roudaire
Author URI: https://www.phyxo.net

The theme is based on the original one for piwigo.
 */

namespace Treflez;

$themeconf = [
    'name' => 'treflez',
    'load_parent_css' => false,
    'icon_dir' => 'img',
    'url' => 'https://www.phyxo.net/'
];

require_once(__DIR__ . '/include/config.php');

$config = new Config($conf);
if (!empty($config->bootstrap_darkroom_navbar_main_style)) {
    $config->navbar_main_style = $config->bootstrap_darkroom_navbar_main_style;
}

if (!empty($config->bootstrap_darkroom_navbar_main_bg)) {
    $config->navbar_main_bg = $config->bootstrap_darkroom_navbar_main_bg;
}

if (!empty($config->bootstrap_darkroom_navbar_contextual_style)) {
    $config->navbar_contextual_style = $config->bootstrap_darkroom_navbar_contextual_style;
}

if (!empty($config->bootstrap_darkroom_navbar_contextual_bg)) {
    $config->navbar_contextual_bg = $config->bootstrap_darkroom_navbar_contextual_bg;
}

return ['theme_config' => $config->getConfig()];
