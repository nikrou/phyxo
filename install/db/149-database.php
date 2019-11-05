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

$upgrade_description = 'Change users table schema only for postgres';

if ($conn->getLayer() === 'pgsql') {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::USERS_TABLE . ' ALTER mail_address SET DEFAULT null';

    $conn->db_query($query);
}

echo "\n" . $upgrade_description . "\n";
