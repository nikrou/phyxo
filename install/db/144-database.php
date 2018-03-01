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

$upgrade_description = 'add activation_key_expire';

if (in_array($conf['dblayer'], array('mysql', 'mysqli'))) {
    $query = 'ALTER TABLE '.USER_INFOS_TABLE.' CHANGE activation_key activation_key VARCHAR(255) DEFAULT NULL,';
    $query .= ' ADD COLUMN activation_key_expire DATETIME DEFAULT NULL AFTER activation_key;';
    $conn->db_query($query);
} elseif ($conf['dblayer']=='pgsql') {
    $query = 'ALTER TABLE '.USER_INFOS_TABLE.' ALTER COLUMN activation_key TYPE VARCHAR(255),';
    $query .= ' ADD COLUMN activation_key_expire TIMESTAMP DEFAULT NULL;';
    $conn->db_query($query);
} elseif ($conf['dblayer']=='sqlite') {
    $temporary_table = 'tmp_user_infos_'.micro_seconds();

    $query = 'BEGIN TRANSACTION;';
    $query .= 'CREATE TEMPORARY TABLE "'.$temporary_table.'" (';
    $query .= '"user_id" INTEGER default 0 NOT NULL,';
    $query .= '"nb_image_page" INTEGER default 15 NOT NULL,';
    $query .= '"status" VARCHAR(50) default \'guest\',';
    $query .= '"language" VARCHAR(50) default \'en_GB\' NOT NULL,';
    $query .= '"expand" BOOLEAN default false,';
    $query .= '"show_nb_comments" BOOLEAN default false,';
    $query .= '"show_nb_hits" BOOLEAN default false,';
    $query .= '"recent_period" INTEGER default 7 NOT NULL,';
    $query .= '"theme" VARCHAR(255) default \'elegant\' NOT NULL,';
    $query .= '"registration_date" TIMESTAMP NOT NULL,';
    $query .= '"enabled_high" BOOLEAN default true,';
    $query .= '"level" INTEGER default 0 NOT NULL,';
    $query .= '"activation_key" VARCHAR(255) default NULL,';
    $query .= '"activation_key_expire" TIMESTAMP default NULL,';
    $query .= '"lastmodified" TIMESTAMP NULL DEFAULT \'1970-01-01 00:00:00\'';
    $query .= 'PRIMARY KEY ("user_id"),';
    $query .= 'CONSTRAINT "user_infos_ui1" UNIQUE ("user_id"));';
    $query .= 'INSERT INTO '.$temporary_table.' SELECT * FROM '.USER_INFOS_TABLE.';';
    $query .= 'DROP TABLE '.USER_INFOS_TABLE.';';
    $query .= 'ALTER TABLE '.$temporary_table.' RENAME TO '.USER_INFOS_TABLE.';';
    $query .= 'ALTER TABLE '.USER_INFOS_TABLE.' ADD COLUMN "activation_key_expire" TIMESTAMP default NULL;';
    $query .= 'COMMIT;';
    $conn->db_query($query);
}

// purge current expiration keys
$conn->db_query('UPDATE '.USER_INFOS_TABLE.' SET activation_key = NULL;');

echo "\n".$upgrade_description."\n";
