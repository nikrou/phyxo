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

class UserAccessRepository extends BaseRepository
{
    public function findByUserId(int $user_id)
    {
        $query = 'SELECT user_id, cat_id FROM ' . self::USER_ACCESS_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function findByCatId(? int $cat_id = null)
    {
        $query = 'SELECT user_id, cat_id FROM ' . self::USER_ACCESS_TABLE;

        if (!is_null($cat_id)) {
            $query .= ' WHERE cat_id = ' . $cat_id;
        }

        return $this->conn->db_query($query);
    }

    public function findFieldByCatId(int $cat_id, string $field)
    {
        $query = 'SELECT ' . $field . ' FROM ' . self::USER_ACCESS_TABLE;
        $query .= ' WHERE cat_id = ' . $cat_id;

        return $this->conn->db_query($query);
    }

    public function findByCatIds(array $cat_ids)
    {
        $query = 'SELECT user_id, cat_id FROM ' . self::USER_ACCESS_TABLE;
        $query .= ' WHERE cat_id ' . $this->conn->in($cat_ids);

        return $this->conn->db_query($query);
    }

    public function getDistinctUser()
    {
        $query = 'SELECT DISTINCT user_id FROM ' . self::USER_ACCESS_TABLE;

        return $this->conn->db_query($query);
    }

    public function insertUserAccess(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::USER_ACCESS_TABLE, $fields, $datas);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::USER_ACCESS_TABLE, $fields, $datas);
    }

    public function deleteByCatIds(array $ids, string $condition = '')
    {
        $query = 'DELETE FROM ' . self::USER_ACCESS_TABLE;
        $query .= ' WHERE cat_id ' . $this->conn->in($ids);

        if (!empty($condition)) {
            $query .= ' AND ' . $condition;
        }

        $this->conn->db_query($query);
    }

    public function deleteByUserIdsAndCatIds(array $user_ids, array $cat_ids)
    {
        $query = 'DELETE FROM ' . self::USER_ACCESS_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $query .= ' AND cat_id ' . $this->conn->in($cat_ids);
        $this->conn->db_query($query);
    }

    public function deleteByUserId(int $user_id)
    {
        $query = 'DELETE FROM ' . self::USER_ACCESS_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $this->conn->db_query($query);
    }

    public function deleteByUserIds(array $user_ids)
    {
        $query = 'DELETE FROM ' . self::USER_ACCESS_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }
}
