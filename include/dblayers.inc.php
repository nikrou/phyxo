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

$dblayers = [];
$dblayers['mysql'] = [
    'engine' => 'MySQL',
    'function_available' => 'mysqli_connect'
];

$dblayers['pgsql'] = [
    'engine' => 'PostgreSQL',
    'function_available' => 'pg_connect'
];

$dblayers['sqlite'] = [
    'engine' => 'SQLite',
    'class_available' => 'PDO'
];
