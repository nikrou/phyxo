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

class UserGroupRepository extends BaseRepository
{
    public function findByUserIds(array $user_ids)
    {
        $query = 'SELECT user_id, name FROM ' . self::USER_GROUP_TABLE;
        $query .= ' LEFT JOIN ' . self::GROUPS_TABLE . ' ON id = group_id';
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);

        return $this->conn->db_query($query);
    }

    public function findByGroupId(int $group_id)
    {
        $query = 'SELECT user_id, group_id FROM ' . self::USER_GROUP_TABLE;
        $query .= ' WHERE group_id = ' . $group_id;

        return $this->conn->db_query($query);
    }

    public function findByGroupIds(array $group_ids)
    {
        $query = 'SELECT user_id, group_id FROM ' . self::USER_GROUP_TABLE;
        $query .= ' WHERE group_id ' . $this->conn->in($group_ids);

        return $this->conn->db_query($query);
    }

    public function findCategoryAuthorizedToTheGroupTheUserBelongs(int $user_id)
    {
        $query = 'SELECT cat_id FROM ' . self::USER_GROUP_TABLE . ' AS ug';
        $query .= ' LEFT JOIN ' . self::GROUP_ACCESS_TABLE . ' AS ga ON ug.group_id = ga.group_id';
        $query .= ' WHERE ug.user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function findIndirectUsersByCatIds(array $cat_ids = [])
    {
        $query = 'SELECT ug.user_id, ga.cat_id FROM ' . self::USER_GROUP_TABLE . ' AS ug';
        $query .= ' LEFT JOIN ' . self::GROUP_ACCESS_TABLE . ' AS ga ON ug.group_id = ga.group_id ';

        if (count($cat_ids) > 0) {
            $query .= 'WHERE cat_id ' . $this->conn->in($cat_ids);
        }

        return $this->conn->db_query($query);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::USER_GROUP_TABLE, $fields, $datas);
    }

    public function delete(int $group_id, array $user_ids)
    {
        $query = 'DELETE FROM ' . self::USER_GROUP_TABLE;
        $query .= ' WHERE group_id = ' . $group_id;
        $query .= ' AND user_id ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }

    public function deleteByGroupIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::USER_GROUP_TABLE;
        $query .= ' WHERE group_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function deleteByUserIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::USER_GROUP_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }
}
