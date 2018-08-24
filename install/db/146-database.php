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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

$upgrade_description = 'Use json functions instead of serialize ones';

$conf->loadFromDB();

$params = array(
    'picture_informations',
    'updates_ignored',
);

foreach ($params as $param) {
    $conf[$param] = unserialize($conf[$param]);
}

echo "\n" . $upgrade_description . "\n";
