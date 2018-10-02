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

class ConfigRepository extends BaseRepository
{
    public function findAll(string $condition = '')
    {
        $query = 'SELECT param, value FROM ' . self::CONFIG_TABLE;

        if (!empty($condition)) {
            $query .= ' WHERE ' . $condition;
        }

        return $this->conn->db_query($query);
    }

    public function addParam(string $param, $value, string $comment)
    {
        if (is_array($value) || is_object($value)) {
            $dbValue = json_encode($value);
        } elseif (is_bool($value)) {
            $dbValue = $this->conn->boolean_to_string($value);
        } elseif (empty($value)) {
            $dbValue = null;
        } else {
            $dbValue = $this->conn->db_real_escape_string($value);
        }

        $this->conn->single_insert(self::CONFIG_TABLE, ['param' => $param, 'value' => $dbValue, 'comment' => $comment], false);
    }

    public function addOrUpdateParam(string $param, $value)
    {
        if (is_array($value) || is_object($value)) {
            $dbValue = json_encode($value);
        } elseif (is_bool($value)) {
            $dbValue = $this->conn->boolean_to_string($value);
        } elseif (empty($value)) {
            $dbValue = null;
        } else {
            $dbValue = $this->conn->db_real_escape_string($value);
        }

        $query = 'SELECT count(1) FROM ' . self::CONFIG_TABLE;
        $query .= ' WHERE param = \'' . $this->conn->db_real_escape_string($param) . '\'';

        list($counter) = $this->conn->db_fetch_row($this->conn->db_query($query));
        if ($counter === 0) {
            $query = 'INSERT INTO ' . self::CONFIG_TABLE . ' (param, value)';
            $query .= ' VALUES(\'' . $this->conn->db_real_escape_string($param) . '\', \'' . $this->conn->db_real_escape_string($dbValue) . '\')';
            $this->conn->db_query($query);
        } else {
            $query = 'UPDATE ' . self::CONFIG_TABLE;
            $query .= ' SET value = \'' . $this->conn->db_real_escape_string($dbValue) . '\'';
            $query .= ' WHERE param = \'' . $this->conn->db_real_escape_string($param) . '\'';
            $this->conn->db_query($query);
        }
    }

    public function massUpdates($fields, array $datas)
    {
        $this->conn->mass_updates(self::CONFIG_TABLE, $fields, $datas);
    }

    public function delete($params)
    {
        $query = 'DELETE FROM ' . self::CONFIG_TABLE;
        $query .= ' WHERE param ' . $this->conn->in($params);
        $this->conn->db_query($query);
    }
}
