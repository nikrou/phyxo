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

class UserFeedRepository extends BaseRepository
{
    public function findById(string $feed_id)
    {
        $query = 'SELECT user_id, last_check FROM ' . self::USER_FEED_TABLE;
        $query .= ' WHERE id = \'' . $this->conn->db_real_escape_string($feed_id) . '\'';

        return $this->conn->db_query($query);
    }

    public function updateUserFeed(array $datas, string $feed_id)
    {
        $this->conn->single_update(self::USER_FEED_TABLE, $datas, ['id' => $feed_id]);
    }

    public function addUserFeed(array $datas)
    {
        $this->conn->single_insert(self::USER_FEED_TABLE, $datas, false);
    }

    public function deleteUserFeedNotChecked()
    {
        $query = 'DELETE FROM ' . self::USER_FEED_TABLE;
        $query .= ' WHERE last_check IS NULL';
        $this->conn->db_query($query);
    }

    public function deleteUserOnes(int $user_id)
    {
        $query = 'DELETE FROM ' . self::USER_FEED_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $this->conn->db_query($query);
    }

    public function getDistinctUser()
    {
        $query = 'SELECT DISTINCT user_id FROM ' . self::USER_FEED_TABLE;

        return $this->conn->db_query($query);
    }

    public function deleteByUserIds(array $user_ids)
    {
        $query = 'DELETE FROM ' . self::USER_FEED_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }
}
