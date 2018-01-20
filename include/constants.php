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
    define('PHPWG_VERSION', '1.7.0-dev');
}
if (!defined('PHPWG_DEFAULT_LANGUAGE')) {
    define('PHPWG_DEFAULT_LANGUAGE', 'en_UK');
}
if (!defined('PHPWG_DEFAULT_TEMPLATE')) {
    define('PHPWG_DEFAULT_TEMPLATE', 'elegant');
}

if (!defined('PHPWG_THEMES_PATH')) {
    define('PHPWG_THEMES_PATH', $conf['themes_dir'].'/');
}
if (!defined('PHPWG_PLUGINS_PATH')) {
    define('PHPWG_PLUGINS_PATH', PHPWG_ROOT_PATH.'plugins');
}
if (!defined('PHPWG_LANGUAGES_PATH')) {
    define('PHPWG_LANGUAGES_PATH', PHPWG_ROOT_PATH.'language/');
}
if (!defined('PWG_COMBINED_DIR')) {
    define('PWG_COMBINED_DIR', $conf['data_location'].'combined/');
}
if (!defined('PWG_DERIVATIVE_DIR')) {
    define('PWG_DERIVATIVE_DIR', $conf['data_location'].'i/');
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
        define('PEM_URL', 'http://ext.'.PHPWG_DOMAIN);
    }
}


// Required versions
define('REQUIRED_PHP_VERSION', '5.4.0');

// Access codes
define('ACCESS_FREE', 0);
define('ACCESS_GUEST', 1);
define('ACCESS_CLASSIC', 2);
define('ACCESS_ADMINISTRATOR', 3);
define('ACCESS_WEBMASTER', 4);
define('ACCESS_CLOSED', 5);

// Sanity checks
define('PATTERN_ID', '/^\d+$/');

// Table names
if (!defined('CATEGORIES_TABLE')) {
    define('CATEGORIES_TABLE', $prefixeTable.'categories');
}
if (!defined('COMMENTS_TABLE')) {
    define('COMMENTS_TABLE', $prefixeTable.'comments');
}
if (!defined('CONFIG_TABLE')) {
    define('CONFIG_TABLE', $prefixeTable.'config');
}
if (!defined('FAVORITES_TABLE')) {
    define('FAVORITES_TABLE', $prefixeTable.'favorites');
}
if (!defined('GROUP_ACCESS_TABLE')) {
    define('GROUP_ACCESS_TABLE', $prefixeTable.'group_access');
}
if (!defined('GROUPS_TABLE')) {
    define('GROUPS_TABLE', $prefixeTable.'groups');
}
if (!defined('HISTORY_TABLE')) {
    define('HISTORY_TABLE', $prefixeTable.'history');
}
if (!defined('HISTORY_SUMMARY_TABLE')) {
    define('HISTORY_SUMMARY_TABLE', $prefixeTable.'history_summary');
}
if (!defined('IMAGE_CATEGORY_TABLE')) {
    define('IMAGE_CATEGORY_TABLE', $prefixeTable.'image_category');
}
if (!defined('IMAGES_TABLE')) {
    define('IMAGES_TABLE', $prefixeTable.'images');
}
if (!defined('SESSIONS_TABLE')) {
    define('SESSIONS_TABLE', $prefixeTable.'sessions');
}
if (!defined('SITES_TABLE')) {
    define('SITES_TABLE', $prefixeTable.'sites');
}
if (!defined('USER_ACCESS_TABLE')) {
    define('USER_ACCESS_TABLE', $prefixeTable.'user_access');
}
if (!defined('USER_GROUP_TABLE')) {
    define('USER_GROUP_TABLE', $prefixeTable.'user_group');
}
if (!defined('USERS_TABLE')) {
    define('USERS_TABLE', isset($conf['users_table']) ? $conf['users_table'] : $prefixeTable.'users' );
}
if (!defined('USER_INFOS_TABLE')) {
    define('USER_INFOS_TABLE', $prefixeTable.'user_infos');
}
if (!defined('USER_FEED_TABLE')) {
    define('USER_FEED_TABLE', $prefixeTable.'user_feed');
}
if (!defined('RATE_TABLE')) {
    define('RATE_TABLE', $prefixeTable.'rate');
}
if (!defined('USER_CACHE_TABLE')) {
    define('USER_CACHE_TABLE', $prefixeTable.'user_cache');
}
if (!defined('USER_CACHE_CATEGORIES_TABLE')) {
    define('USER_CACHE_CATEGORIES_TABLE', $prefixeTable.'user_cache_categories');
}
if (!defined('CADDIE_TABLE')) {
    define('CADDIE_TABLE', $prefixeTable.'caddie');
}
if (!defined('UPGRADE_TABLE')) {
    define('UPGRADE_TABLE', $prefixeTable.'upgrade');
}
if (!defined('SEARCH_TABLE')) {
    define('SEARCH_TABLE', $prefixeTable.'search');
}
if (!defined('USER_MAIL_NOTIFICATION_TABLE')) {
    define('USER_MAIL_NOTIFICATION_TABLE', $prefixeTable.'user_mail_notification');
}
if (!defined('TAGS_TABLE')) {
    define('TAGS_TABLE', $prefixeTable.'tags');
}
if (!defined('IMAGE_TAG_TABLE')) {
    define('IMAGE_TAG_TABLE', $prefixeTable.'image_tag');
}
if (!defined('PLUGINS_TABLE')) {
    define('PLUGINS_TABLE', $prefixeTable.'plugins');
}
if (!defined('OLD_PERMALINKS_TABLE')) {
    define('OLD_PERMALINKS_TABLE', $prefixeTable.'old_permalinks');
}
if (!defined('THEMES_TABLE')) {
    define('THEMES_TABLE', $prefixeTable.'themes');
}
if (!defined('LANGUAGES_TABLE')) {
    define('LANGUAGES_TABLE', $prefixeTable.'languages');
}
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
