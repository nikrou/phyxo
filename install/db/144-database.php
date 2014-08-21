<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

$upgrade_description = 'add activation_key_expire';

if (in_array($conf['dblayer'], array('mysql', 'mysqli'))) {
    $query = 'ALTER TABLE '.USER_INFOS_TABLE.' CHANGE activation_key activation_key VARCHAR(255) DEFAULT NULL,';
    $query .= ' ADD COLUMN activation_key_expire DATETIME DEFAULT NULL AFTER activation_key;';
    pwg_query($query);
} elseif ($conf['dblayer']=='pgsql') {
    $query = 'ALTER TABLE '.USER_INFOS_TABLE.' ALTER COLUMN activation_key TYPE VARCHAR(255),';
    $query .= ' ADD COLUMN activation_key_expire TIMESTAMP DEFAULT NULL;';
    pwg_query($query);
} elseif ($conf['dblayer']=='sqlite') {
    $temporary_table = 'tmp_user_infos_'.micro_seconds();

    $query = 'BEGIN TRANSACTION;';
    $query .= 'CREATE TEMPORARY TABLE "'.$temporary_table.'" (';
    $query .= '"user_id" INTEGER default 0 NOT NULL,';
    $query .= '"nb_image_page" INTEGER default 15 NOT NULL,';
    $query .= '"status" VARCHAR(50) default \'guest\',';
    $query .= '"language" VARCHAR(50) default \'en_UK\' NOT NULL,';
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
    pwg_query($query);
}

// purge current expiration keys
pwg_query('UPDATE '.USER_INFOS_TABLE.' SET activation_key = NULL;');

echo "\n".$upgrade_description."\n";
