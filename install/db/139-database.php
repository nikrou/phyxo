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

$upgrade_description = 'add "latitude" and "longitude" fields';

// add fields
$query = '
ALTER TABLE ' . App\Repository\BaseRepository::IMAGES_TABLE . '
  ADD `latitude` DOUBLE(8, 6) DEFAULT NULL,
  ADD `longitude` DOUBLE(9, 6) DEFAULT NULL
;';
$conn->db_query($query);

// add index
$query = '
ALTER TABLE ' . App\Repository\BaseRepository::IMAGES_TABLE . '
  ADD INDEX `images_i6` (`latitude`)
;';
$conn->db_query($query);

// search for old "lat" field
$query = 'SHOW COLUMNS FROM ' . App\Repository\BaseRepository::IMAGES_TABLE . ' LIKE \'lat\'';

if ($conn->db_num_rows($conn->db_query($query))) {
    // duplicate non-null values
  $query = 'UPDATE ' . App\Repository\BaseRepository::IMAGES_TABLE;
  $query .= ' SET latitude = lat,longitude = lon WHERE lat IS NOT NULL AND lon IS NOT NULL';
  $conn->db_query($query);
}

echo "\n" . $upgrade_description . "\n";
