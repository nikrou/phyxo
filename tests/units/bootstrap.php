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

define('PEM_URL', 'http://localhost/pem');
define('TESTS_CONFIG_PATH', __DIR__ . '/fixtures/config/');

define('PHPWG_TMP_PATH', __DIR__ . '/tmp');
define('PHPWG_ZIP_PATH', __DIR__ . '/fixtures/zip');

$conf['admin_theme'] = 'default';

// Access codes
define('ACCESS_FREE', 0);
define('ACCESS_GUEST', 1);
define('ACCESS_CLASSIC', 2);
define('ACCESS_ADMINISTRATOR', 3);
define('ACCESS_WEBMASTER', 4);
define('ACCESS_CLOSED', 5);

$user = ['language' => 'en_GB'];
