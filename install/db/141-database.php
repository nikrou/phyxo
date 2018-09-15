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

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

$upgrade_description = 'add lastmodified field for categories, images, groups, users, tags';

$tables = [
  CATEGORIES_TABLE,
  GROUPS_TABLE,
  IMAGES_TABLE,
  TAGS_TABLE,
  USER_INFOS_TABLE
  ];

foreach ($tables as $table) {
    if (in_array($conf['dblayer'], ['mysql'])) {
        $query = 'ALTER TABLE '. $table;
        $query .= ' ADD `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,';
        $query .= ' ADD INDEX `lastmodified` (`lastmodified`)';
        $conn->db_query($query);
    } elseif ($conf['dblayer']=='pgsql') {
        $conn->db_query('ALTER TABLE '.$table.' ADD "lastmodified" TIMESTAMP NULL DEFAULT now()');
    } elseif ($conf['dblayer']=='sqlite') {
        $conn->db_query('ALTER TABLE '.$table.' ADD "lastmodified" TIMESTAMP NULL DEFAULT \'1970-01-01 00:00:00\'');
    }
}

echo "\n".$upgrade_description."\n";
