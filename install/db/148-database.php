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

$upgrade_description = 'Change sessions table schema';

if (in_array($conf['dblayer'], ['mysql'])) {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $query .= ' DROP COLUMN id,';
    $query .= ' DROP COLUMN data,';
    $query .= ' DROP COLUMN expiration,';
    $query .= ' ADD COLUMN sess_id VARCHAR(128) NOT NULL PRIMARY KEY,';
    $query .= ' ADD COLUMN sess_data BLOB NOT NULL,';
    $query .= ' ADD COLUMN  sess_time INTEGER UNSIGNED NOT NULL,';
    $query .= ' ADD COLUMN  sess_lifetime MEDIUMINT NOT NULL';
    $conn->db_query($query);
} elseif ($conf['dblayer'] == 'pgsql') {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $query .= ' DROP COLUMN id,';
    $query .= ' DROP COLUMN data,';
    $query .= ' DROP COLUMN expiration,';
    $query .= ' ADD COLUMN sess_id VARCHAR(128) NOT NULL PRIMARY KEY,';
    $query .= ' ADD COLUMN sess_data BYTEA NOT NULL,';
    $query .= ' ADD COLUMN sess_time INTEGER NOT NULL,';
    $query .= ' ADD COLUMN sess_lifetime INTEGER NOT NULL;';
    $conn->db_query($query);
} elseif ($conf['dblayer'] == 'sqlite') {
    $query = 'DROP TABLE ' . App\Repository\BaseRepository::SESSIONS_TABLE . ';';
    $query = 'CREATE TABLE ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $query .= ' (sess_id VARCHAR(128) NOT NULL PRIMARY KEY,';
    $query .= ' sess_data TEXT NOT NULL,';
    $query .= ' sess_time INTEGER NOT NULL,';
    $query .= ' sess_lifetime INTEGER NOT NULL)';
  
    $conn->db_query($query);
}

echo "\n" . $upgrade_description . "\n";
