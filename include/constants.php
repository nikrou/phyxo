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
    define('PHPWG_VERSION', '1.10.0');
}
if (!defined('PHPWG_DEFAULT_LANGUAGE')) {
    define('PHPWG_DEFAULT_LANGUAGE', 'en_GB');
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

// Access codes
define('ACCESS_FREE', 0);
define('ACCESS_GUEST', 1);
define('ACCESS_CLASSIC', 2);
define('ACCESS_ADMINISTRATOR', 3);
define('ACCESS_WEBMASTER', 4);
define('ACCESS_CLOSED', 5);

// Sanity checks
define('PATTERN_ID', '/^\d+$/');
