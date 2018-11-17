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

$upgrade_description = 'enlarge your user_id (16 millions possible users)';


if (in_array($conf['dblayer'], ['mysql'])) {
    // we use PREFIX_TABLE, in case Phyxo uses an external user table
    $conn->db_query('ALTER TABLE '.PREFIX_TABLE.'users CHANGE id id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT;');
    $conn->db_query('ALTER TABLE '.IMAGES_TABLE.' CHANGE added_by added_by MEDIUMINT UNSIGNED NOT NULL DEFAULT \'0\';');
    $conn->db_query('ALTER TABLE '.COMMENTS_TABLE.' CHANGE author_id author_id MEDIUMINT UNSIGNED DEFAULT NULL;');

    $tables = [
        USER_ACCESS_TABLE,
        USER_CACHE_TABLE,
        USER_FEED_TABLE,
        USER_GROUP_TABLE,
        USER_INFOS_TABLE,
        USER_CACHE_CATEGORIES_TABLE,
        USER_MAIL_NOTIFICATION_TABLE,
        RATE_TABLE,
        CADDIE_TABLE,
        FAVORITES_TABLE,
        HISTORY_TABLE,
    ];

    foreach ($tables as $table) {
        $conn->db_query('ALTER TABLE '.$table.' CHANGE user_id user_id MEDIUMINT UNSIGNED NOT NULL DEFAULT \'0\';');
    }
}

echo "\n".$upgrade_description."\n";
