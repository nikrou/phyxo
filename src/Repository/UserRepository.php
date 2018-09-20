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

namespace App\Repository;

class UserRepository extends BaseRepository
{
    public function getAll()
    {
        $query = 'SELECT * FROM ' . self::USERS_TABLE;
        $result = $this->conn->db_query($query);

        return $this->conn->db_fetch_assoc($result);
    }

    public function isUserExists(string $username) : bool
    {
        $query = 'SELECT COUNT(1) AS user_exists FROM ' . self::USERS_TABLE;
        $query .= ' WHERE username = \'' . $this->conn->db_real_escape_string($username) . '\'';
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc();

        return $row['user_exists'] === 1;
    }
}
