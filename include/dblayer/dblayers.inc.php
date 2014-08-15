<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

$dblayers = array();
$dblayers['mysql'] = array(
    'engine' => 'MySQL',
    'function_available' => 'mysql_connect'
);

$dblayers['mysqli'] = array(
    'engine' => 'MySQL (improved)',
    'function_available' => 'mysqli_connect'
);

$dblayers['pgsql'] = array(
    'engine' => 'PostgreSQL',
    'function_available' => 'pg_connect'
);

$dblayers['sqlite'] = array(
    'engine' => 'SQLite',
    'class_available' => 'PDO'
);
