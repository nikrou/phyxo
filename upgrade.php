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

// temporary file to perform upgrade
require_once(__DIR__ . '/vendor/autoload.php');

use Phyxo\DBLayer\DBLayer;

include_once(__DIR__ . '/local/config/database.inc.php');
$conn = \Phyxo\DBLayer\DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);

$query = 'UPDATE ' . $prefixeTable . 'plugins';
$query .= ' SET state=\'inactive\'';
$conn->db_query($query);

$query = 'DELETE FROM ' . $prefixeTable . 'config';
$query .= ' WHERE param=\'derivatives\'';
$conn->db_query($query);

$obsolete_file = __DIR__ . '/install/obsolete.list';
$root = __DIR__;

if (!is_readable($obsolete_file)) {
    return;
}

$old_files = file($obsolete_file, FILE_IGNORE_NEW_LINES);
foreach ($old_files as $old_file) {
    $path = $root . '/' . $old_file;
    if (is_writable($path)) {
        @unlink($path);
    } elseif (is_dir($path)) {
        \Phyxo\Functions\Utils::deltree($path);
    }
}

// warmup cache
file_get_contents($_SERVER['REQUEST_SCHEME'].'//'.$_SERVER['HTTP_HOST'].basename($_SERVER['SCRIPT_NAME']).'?now=' . md5(openssl_random_pseudo_bytes(15)));
header("Location: ./admin/?now=" . md5(openssl_random_pseudo_bytes(15)));
