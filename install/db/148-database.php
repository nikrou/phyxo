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

$temporary_table = $conn->getTemporaryTable(App\Repository\BaseRepository::SESSIONS_TABLE);

if (in_array($conn->getLayer(), ['mysql'])) {
    $query = 'CREATE TABLE ' . $temporary_table;
    $query .= '(';
    $query .= '`sess_id` VARCHAR(128) NOT NULL PRIMARY KEY,';
    $query .= '`sess_data` BLOB NOT NULL,';
    $query .= '`sess_time` INTEGER UNSIGNED NOT NULL,';
    $query .= '`sess_lifetime` INTEGER NOT NULL';
    $query .= ') ENGINE=MyISAM;';
    $conn->db_query($query);

    $query = 'DROP TABLE ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $temporary_table . ' RENAME TO ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $conn->db_query($query);
} elseif ($conn->getLayer() === 'pgsql') {
    $query = 'CREATE TABLE ' . $temporary_table;
    $query .= '(';
    $query .= '"sess_id" VARCHAR(128) NOT NULL PRIMARY KEY,';
    $query .= '"sess_data" BYTEA NOT NULL,';
    $query .= '"sess_time" INTEGER NOT NULL,';
    $query .= '"sess_lifetime" INTEGER NOT NULL';
    $query .= ');';
    $conn->db_query($query);

    $query = 'DROP TABLE ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $temporary_table . ' RENAME TO ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $conn->db_query($query);
} elseif ($conn->getLayer() === 'sqlite') {
    $query = 'CREATE TABLE ' . $temporary_table;
    $query .= ' (sess_id VARCHAR(128) NOT NULL PRIMARY KEY,';
    $query .= ' sess_data TEXT NOT NULL,';
    $query .= ' sess_time INTEGER NOT NULL,';
    $query .= ' sess_lifetime INTEGER NOT NULL)';
    $conn->db_query($query);

    $query = 'DROP TABLE ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $conn->db_query($query);

    $query = 'ALTER TABLE ' . $temporary_table . ' RENAME TO ' . App\Repository\BaseRepository::SESSIONS_TABLE;
    $conn->db_query($query);
}

echo "\n" . $upgrade_description . "\n";
