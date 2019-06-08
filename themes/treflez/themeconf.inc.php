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

require_once(__DIR__ . '/include/themecontroller.php');
require_once(__DIR__ . '/include/config.php');

$themeconf = [
    'name' => 'treflez',
    'load_parent_css' => false,
    'icon_dir' => 'img',
    'url' => 'https://www.phyxo.net/'
];

// always show metadata initially
$_SESSION['show_metadata'] = true;

// register video files
$video_ext = ['mp4', 'm4v'];
if (!empty($conf['file_ext'])) {
    $conf['file_ext'] = array_merge($conf['file_ext'], $video_ext, array_map('strtoupper', $video_ext));
}

$controller = new \Treflez\ThemeController($conf, $template, $image_std_params);
$controller->init();
