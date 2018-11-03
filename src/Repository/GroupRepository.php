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

class GroupRepository extends BaseRepository
{
    public function count() : int
    {
        $query = 'SELECT count(1) FROM ' . self::GROUPS_TABLE;
        $result = $this->conn->db_query($query);
        list($nb_groups) = $this->conn->db_fetch_row($result);

        return $nb_groups;
    }

    public function isGroupNameExists(string $name) : bool
    {
        $query = 'SELECT count(1) as group_exists FROM ' . self::GROUPS_TABLE;
        $query .= ' WHERE name = \'' . $this->conn->db_real_escape_string($name) . '\'';

        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return $row['group_exists'] == 1;
    }

    public function isGroupIdExists(int $id) : bool
    {
        $query = 'SELECT count(1) as group_exists FROM ' . self::GROUPS_TABLE;
        $query .= ' WHERE id = ' . $id;

        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return $row['group_exists'] == 1;
    }

    public function findAll(? string $order_by = null)
    {
        $query = 'SELECT id, name, is_default, lastmodified FROM ' . self::GROUPS_TABLE;

        if (!is_null($order_by)) {
            $query .= ' ' . $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function findById(int $id)
    {
        $query = 'SELECT id, name, is_default, lastmodified FROM ' . self::GROUPS_TABLE;
        $query .= ' WHERE id = ' . $id;

        return $this->conn->db_query($query);
    }

    public function findByIds(array $ids, ? string $order_by = null)
    {
        $query = 'SELECT id, name, is_default, lastmodified FROM ' . self::GROUPS_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);

        if (!is_null($order_by)) {
            $query .= ' ' . $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function findByField(string $field, $value, ? string $order_by = null)
    {
        $query = 'SELECT id, name, is_default, lastmodified FROM ' . self::GROUPS_TABLE;

        if (is_bool($value)) {
            $query .= ' WHERE ' . $field . ' = \'' . $this->conn->boolean_to_db($value) . '\'';
        } elseif (!isset($value) or $value === '') {
            $query .= ' WHERE ' . $field . ' IS null ';
        } else {
            $query .= ' WHERE ' . $field . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
        }

        if (!is_null($order_by)) {
            $query .= ' ' . $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function findUsersInGroups()
    {
        $query = 'SELECT g.id, g.name, g.is_default, username FROM ' . self::GROUPS_TABLE . ' AS g';
        $query .= ' LEFT JOIN ' . self::USER_GROUP_TABLE . ' AS ug ON g.id = ug.group_id';
        $query .= ' LEFT JOIN ' . self::USERS_TABLE . ' AS u ON ug.user_id = u.id';

        return $this->conn->db_query($query);
    }

    public function searchByName(? string $name = null, ? array $group_ids = [], string $order, int $limit, int $offset = 0)
    {
        $query = 'SELECT g.id, g.name, g.is_default, g.lastmodified, COUNT(user_id) AS nb_users FROM ' . self::GROUPS_TABLE . ' AS g';
        $query .= ' LEFT JOIN ' . self::USER_GROUP_TABLE . ' AS ug ON ug.group_id = g.id';

        if (!is_null($name) || count($group_ids) > 0) {
            $query .= ' WHERE';
        }

        if (!is_null($name)) {
            $query .= ' LOWER(name) LIKE \'' . $this->conn->db_real_escape_string($name) . '\'';
        }

        if (count($group_ids) > 0) {
            if (!is_null($name)) {
                $query .= ' AND ';
            }
            $query .= ' id ' . $this->conn->in($group_ids);
        }

        $query .= ' GROUP BY id';
        $query .= ' ORDER BY ' . $order;
        $query .= ' LIMIT ' . $limit;
        $query .= ' OFFSET ' . $offset;

        return $this->conn->db_query($query);
    }

    public function addGroup(array $datas) : int
    {
        return $this->conn->single_insert(self::GROUPS_TABLE, $datas);
    }

    public function updateGroup(array $datas, int $id)
    {
        $this->conn->single_update(self::GROUPS_TABLE, $datas, ['id' => $id]);
    }

    public function toggleIsDefault(array $ids)
    {
        $query = 'UPDATE ' . self::GROUPS_TABLE;
        $query .= ' SET is_default = NOT(is_default)';
        $query .= ' WHERE id ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::GROUPS_TABLE, $fields, $datas);
    }

    public function deleteByIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::GROUPS_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }
}
