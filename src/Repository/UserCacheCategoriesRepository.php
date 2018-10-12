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

class UserCacheCategoriesRepository extends BaseRepository
{
    public function deleteUserCacheCategories(? int $user_id = null)
    {
        $query = 'DELETE FROM ' . self::USER_CACHE_CATEGORIES_TABLE;
        if (!is_null($user_id)) {
            $query .= ' WHERE user_id = ' . $user_id;
        }

        $this->conn->db_query($query);
    }

    public function getDistinctUsers()
    {
        $query = 'SELECT DISTINCT user_id FROM ' . self::USER_CACHE_CATEGORIES_TABLE;

        return $this->conn->db_query($query);
    }

    public function deleteByUserId(int $id)
    {
        $query = 'DELETE FROM ' . self::USER_CACHE_CATEGORIES_TABLE;
        $query .= ' WHERE user_id = ' . $id;
        $this->conn->db_query($query);
    }

    public function deleteByUserIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::USER_CACHE_CATEGORIES_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function deleteByUserCatIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::USER_CACHE_CATEGORIES_TABLE;
        $query .= ' WHERE cat_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function insertUserCacheCategories(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::USER_CACHE_CATEGORIES_TABLE, $fields, $datas);
    }

    public function updateUserCacheCategory(array $datas, int $where)
    {
        $this->conn->single_update(self::USER_CACHE_CATEGORIES_TABLE, $datas, $where);
    }

    public function massUpdatesUserCacheCategories(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::USER_CACHE_CATEGORIES_TABLE, $fields, $datas);
    }
}
