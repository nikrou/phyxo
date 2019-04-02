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

// Default settings
if (!defined('PHPWG_VERSION')) { // can be defined in tests
    define('PHPWG_VERSION', '1.10.0-dev');
}
if (!defined('PHPWG_DEFAULT_LANGUAGE')) {
    define('PHPWG_DEFAULT_LANGUAGE', 'en_GB');
}
if (!defined('PHPWG_DEFAULT_TEMPLATE')) {
    define('PHPWG_DEFAULT_TEMPLATE', 'treflez');
}

if (!defined('PHPWG_THEMES_PATH')) {
    define('PHPWG_THEMES_PATH', __DIR__ . '/../themes');
}
if (!defined('PHPWG_PLUGINS_PATH')) {
    define('PHPWG_PLUGINS_PATH', __DIR__ . '/../plugins');
}
if (!defined('PHPWG_LANGUAGES_PATH')) {
    define('PHPWG_LANGUAGES_PATH', __DIR__ . '/../language');
}
if (!defined('PWG_COMBINED_DIR')) {
    define('PWG_COMBINED_DIR', $conf['data_location'] . 'combined/');
}
if (!defined('PWG_DERIVATIVE_DIR')) {
    define('PWG_DERIVATIVE_DIR', $conf['data_location'] . 'i/');
}

// Update settings
if (!defined('PHPWG_DOMAIN')) {
    define('PHPWG_DOMAIN', 'phyxo.net');
}
if (!defined('PHPWG_URL')) {
    define('PHPWG_URL', 'https://www.phyxo.net');
}
if (!defined('PHYXO_UPDATE_URL')) {
    define('PHYXO_UPDATE_URL', 'https://download.phyxo.net/versions');
}
if (!defined('PHYXO_UPDATE_VERSION')) {
    define('PHYXO_UPDATE_VERSION', 'stable');
}
if (!defined('PEM_URL')) {
    if (!empty($conf['alternative_pem_url'])) {
        define('PEM_URL', $conf['alternative_pem_url']);
    } else {
        define('PEM_URL', 'https://ext.' . PHPWG_DOMAIN);
    }
}

// Access codes
define('ACCESS_FREE', 0);
define('ACCESS_GUEST', 1);
define('ACCESS_CLASSIC', 2);
define('ACCESS_ADMINISTRATOR', 3);
define('ACCESS_WEBMASTER', 4);
define('ACCESS_CLOSED', 5);

// Sanity checks
define('PATTERN_ID', '/^\d+$/');

if (!defined('MASS_UPDATES_SKIP_EMPTY')) {
    define('MASS_UPDATES_SKIP_EMPTY', 1);
}

define('IMG_SQUARE', 'square');
define('IMG_THUMB', 'thumb');
define('IMG_XXSMALL', '2small');
define('IMG_XSMALL', 'xsmall');
define('IMG_SMALL', 'small');
define('IMG_MEDIUM', 'medium');
define('IMG_LARGE', 'large');
define('IMG_XLARGE', 'xlarge');
define('IMG_XXLARGE', 'xxlarge');
define('IMG_CUSTOM', 'custom');
