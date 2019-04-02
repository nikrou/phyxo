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

$upgrade_description = 'enlarge your user_id (16 millions possible users)';


if (in_array($conf['dblayer'], ['mysql'])) {
    $conn->db_query('ALTER TABLE ' . App\Repository\BaseRepository::USERS_TABLE . ' CHANGE id id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT;');
    $conn->db_query('ALTER TABLE ' . App\Repository\BaseRepository::IMAGES_TABLE . ' CHANGE added_by added_by MEDIUMINT UNSIGNED NOT NULL DEFAULT \'0\';');
    $conn->db_query('ALTER TABLE ' . App\Repository\BaseRepository::COMMENTS_TABLE . ' CHANGE author_id author_id MEDIUMINT UNSIGNED DEFAULT NULL;');

    $tables = [
        App\Repository\BaseRepository::USER_ACCESS_TABLE,
        App\Repository\BaseRepository::USER_CACHE_TABLE,
        App\Repository\BaseRepository::USER_FEED_TABLE,
        App\Repository\BaseRepository::USER_GROUP_TABLE,
        App\Repository\BaseRepository::USER_INFOS_TABLE,
        App\Repository\BaseRepository::USER_CACHE_CATEGORIES_TABLE,
        App\Repository\BaseRepository::USER_MAIL_NOTIFICATION_TABLE,
        App\Repository\BaseRepository::RATE_TABLE,
        App\Repository\BaseRepository::CADDIE_TABLE,
        App\Repository\BaseRepository::FAVORITES_TABLE,
        App\Repository\BaseRepository::HISTORY_TABLE,
    ];

    foreach ($tables as $table) {
        $conn->db_query('ALTER TABLE ' . $table . ' CHANGE user_id user_id MEDIUMINT UNSIGNED NOT NULL DEFAULT \'0\';');
    }
}

echo "\n" . $upgrade_description . "\n";
