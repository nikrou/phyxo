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

class GroupAccessRepository extends BaseRepository
{
    public function deleteByCatIds(array $ids, string $condition)
    {
        $query = 'DELETE FROM ' . self::GROUP_ACCESS_TABLE;
        $query .= ' WHERE cat_id ' . $this->conn->in($ids);

        if (!empty($condtion)) {
            $query .= ' AND ' . $condtion;
        }

        $this->conn->db_query($query);
    }

    public function findByCatId(int $cat_id, string $field)
    {
        $query = 'SELECT ' . $field . ' FROM ' . self::GROUP_ACCESS_TABLE;
        $query .= ' WHERE cat_id = ' . $this->conn->db_real_escape_string($cat_id);

        return $this->conn->db_query($query);
    }

    public function insertGroupAccess(array $fields, array $datas)
    {
        $this->conn->mass_insert(self::GROUP_ACCESS_TABLE, $fields, $datas);
    }
}
