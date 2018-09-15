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

// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

$upgrade_description = 'add fields for users tags';

if (in_array($conf['dblayer'], ['mysql'])) {
    $query = 'ALTER TABLE '.IMAGE_TAG_TABLE;
    $query .= ' ADD COLUMN validated enum("true","false") NOT NULL default "false",';
    $query .= ' ADD COLUMN created_by mediumint(8) unsigned DEFAULT NULL,';
    $query .= ' ADD COLUMN status smallint(3) DEFAULT 1';
    $conn->db_query($query);
} elseif ($conf['dblayer']=='pgsql') {
    $query = 'ALTER TABLE '.IMAGE_TAG_TABLE.' ADD COLUMN validated BOOLEAN default true';
    $conn->db_query($query);
    $query = 'ALTER TABLE '.IMAGE_TAG_TABLE.' ADD COLUMN created_by INTEGER REFERENCES "phyxo_users" (id)';
    $conn->db_query($query);
    $query = 'ALTER TABLE '.IMAGE_TAG_TABLE.' ADD COLUMN status INTEGER default 1';
    $conn->db_query($query);
} elseif ($conf['dblayer']=='sqlite') {
    $query = 'ALTER TABLE '.IMAGE_TAG_TABLE.' ADD COLUMN validated" BOOLEAN default false';
    $conn->db_query($query);
    $query = 'ALTER TABLE '.IMAGE_TAG_TABLE.' ADD COLUMN created_by INTEGER REFERENCES "phyxo_users" (id)';
    $conn->db_query($query);
    $query = 'ALTER TABLE '.IMAGE_TAG_TABLE.' ADD COLUMN status INTEGER DEFAULT 1';
    $conn->db_query($query);
}

echo "\n".$upgrade_description."\n";
