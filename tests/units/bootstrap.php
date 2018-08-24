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
define('TESTS_CONFIG_PATH', __DIR__ . '/fixtures/config/');

define('PHPWG_TMP_PATH', __DIR__ . '/tmp');
define('PHPWG_ZIP_PATH', __DIR__ . '/fixtures/zip');
define('PHPWG_DEFAULT_LANGUAGE', 'en_GB');

define('MASS_UPDATES_SKIP_EMPTY', false);
define('CONFIG_TABLE', 'config');

$conf['admin_theme'] = 'default';
