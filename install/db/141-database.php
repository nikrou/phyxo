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

$upgrade_description = 'add lastmodified field for categories, images, groups, users, tags';

$tables = [
    App\Repository\BaseRepository::CATEGORIES_TABLE,
    App\Repository\BaseRepository::GROUPS_TABLE,
    App\Repository\BaseRepository::IMAGES_TABLE,
    App\Repository\BaseRepository::TAGS_TABLE,
    App\Repository\BaseRepository::USER_INFOS_TABLE
];

foreach ($tables as $table) {
    if (in_array($conf['dblayer'], ['mysql'])) {
        $query = 'ALTER TABLE ' . $table;
        $query .= ' ADD `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,';
        $query .= ' ADD INDEX `lastmodified` (`lastmodified`)';
        $conn->db_query($query);
    } elseif ($conf['dblayer'] == 'pgsql') {
        $conn->db_query('ALTER TABLE ' . $table . ' ADD "lastmodified" TIMESTAMP NULL DEFAULT now()');
    } elseif ($conf['dblayer'] == 'sqlite') {
        $conn->db_query('ALTER TABLE ' . $table . ' ADD "lastmodified" TIMESTAMP NULL DEFAULT \'1970-01-01 00:00:00\'');
    }
}

echo "\n" . $upgrade_description . "\n";
