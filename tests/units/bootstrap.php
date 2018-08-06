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

require_once __DIR__ . '/../../vendor/autoload.php';

define('PHPWG_ROOT_PATH', './../../');
define('PEM_URL', 'http://localhost/pem');
define('PHPWG_VERSION', 'tests');

define('PHPWG_THEMES_PATH', __DIR__ . '/fixtures/themes/');
define('PHPWG_PLUGINS_PATH', __DIR__ . '/fixtures/plugins');
define('PHPWG_LANGUAGES_PATH', __DIR__ . '/fixtures/language/');

define('PHPWG_TMP_PATH', __DIR__ . '/tmp');
define('PHPWG_ZIP_PATH', __DIR__ . '/fixtures/zip');
define('PHPWG_DEFAULT_LANGUAGE', 'en_GB');

define('MASS_UPDATES_SKIP_EMPTY', false);

$conf['admin_theme'] = 'default';

// copy from include/functions_html.inc.php
function name_compare($a, $b)
{
    return strcmp(strtolower($a['name']), strtolower($b['name']));
}

// copy from include/functions.inc.php
function get_pwg_charset()
{
    $pwg_charset = 'utf-8';
    if (defined('PWG_CHARSET')) {
        $pwg_charset = PWG_CHARSET;
    }

    return $pwg_charset;
}

function convert_charset($str, $source_charset, $dest_charset)
{
    return $str;
}