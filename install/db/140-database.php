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

$upgrade_description = '#tags.name is not binary';

// add fields
if (in_array($conf['dblayer'], ['mysql'])) {
    $query = 'ALTER TABLE '.TAGS_TABLE.' CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL DEFAULT \'\'';
    $conn->db_query($query);
}

echo "\n".$upgrade_description."\n";
