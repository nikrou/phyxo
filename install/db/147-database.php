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

$upgrade_description = 'Change default language en_GB instead of en_UK in user_infos table';

$query = '';

if (in_array($conf['dblayer'], array('mysql', 'mysqli'))) {
    $query = 'ALTER TABLE ' . USER_INFOS_TABLE . ' CHANGE COLUMN language language VARCHAR(50) NOT NULL DEFAULT \'en_GB\'';
} elseif ($conf['dblayer'] === 'pgsql') {
    $query = 'ALTER TABLE ' . USER_INFOS_TABLE . ' ALTER language SET DEFAULT \'en_GB\'';
} elseif ($conf['dblayer'] === 'sqlite') {
    $temporary_table = $conn->getTemporaryTable('tmp_user_infos');

    $query = 'BEGIN TRANSACTION;';
    $query .= 'CREATE TEMPORARY TABLE "' . $temporary_table . '" (';
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
    $query .= 'INSERT INTO ' . $temporary_table . ' SELECT * FROM ' . USER_INFOS_TABLE . ';';
    $query .= 'DROP TABLE ' . USER_INFOS_TABLE . ';';
    $query .= 'ALTER TABLE ' . $temporary_table . ' RENAME TO ' . USER_INFOS_TABLE . ';';
    $query .= 'ALTER TABLE ' . USER_INFOS_TABLE . ' ADD COLUMN "activation_key_expire" TIMESTAMP default NULL;';
    $query .= 'COMMIT;';
    $conn->db_query($query);
}

$conn->db_query($query);

echo "\n" . $upgrade_description . "\n";
