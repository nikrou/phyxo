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

class UserCacheRepository extends BaseRepository
{
    public function getUserCacheData(int $user_id)
    {
        $query = 'SELECT user_id, need_update, cache_update_time, forbidden_categories, nb_total_images,';
        $query .= ' last_photo_date, nb_available_tags, nb_available_comments, image_access_type, image_access_list FROM ' . self::USER_CACHE_TABLE;
        $query .= ' WHERE user_id  = ' . $this->conn->db_real_escape_string($user_id);

        return $this->conn->db_query($query);
    }

    public function deleteUserCache(? int $user_id = null)
    {
        $query = 'DELETE FROM ' . self::USER_CACHE_TABLE;
        if (!is_null($user_id)) {
            $query .= ' WHERE user_id = ' . $user_id;
        }

        $this->conn->db_query($query);
    }

    public function invalidateUserCache(string $field)
    {
        $this->conn->single_update(self::USER_CACHE_TABLE, [$field => null], []);
    }

    public function updateUserCache(array $datas, array $where = [])
    {
        $this->conn->single_update(self::USER_CACHE_TABLE, $datas, $where);
    }

    public function getDistinctUsers()
    {
        $query = 'SELECT DISTINCT user_id FROM ' . self::USER_CACHE_TABLE;

        return $this->conn->db_query($query);
    }

    public function deleteByUserIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::USER_CACHE_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function insertUserCache(array $params)
    {
        return $this->conn->single_insert(self::USER_CACHE_TABLE, $params, $auto_increment_for_table = false);
    }
}
