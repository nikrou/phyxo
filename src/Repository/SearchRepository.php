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

class SearchRepository extends BaseRepository
{
    public function findById(int $id)
    {
        $query = 'SELECT rules FROM ' . self::SEARCH_TABLE;
        $query .= ' WHERE id = ' . $id;

        return $this->conn->db_query($query);
    }

    public function findByRules(string $rules)
    {
        $query = 'SElECT id FROM ' . self::SEARCH_TABLE;
        $query .= ' WHERE rules = \'' . $this->conn->db_real_escape_string($rules) . '\'';

        return $this->conn->db_query($query);
    }

    public function updateLastSeen(int $id)
    {
        $query = 'UPDATE ' . self::SEARCH_TABLE;
        $query .= ' SET last_seen = NOW() WHERE id=' . $id;
        $this->conn->db_query($query);
    }

    public function addSearch(string $rules)
    {
        $query = 'INSERT INTO ' . self::SEARCH_TABLE;
        $query .= ' (rules, last_seen) VALUES (\'' . $rules . '\', NOW())';
        $result = $this->conn->db_query($query);

        return $this->conn->db_insert_id(self::SEARCH_TABLE);
    }

    public function delete()
    {
        $query = 'DELETE FROM ' . self::SEARCH_TABLE;
        $this->conn->db_query($query);
    }
}
