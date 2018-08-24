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

namespace Phyxo;

use Phyxo\DBLayer\DBLayer;

/**
 *  Manage configuration of Phyxo in two ways :
 *  - in php files (throught include/config_default.inc.php override by local/config/config.inc.php)
 *  - in database : can be update throught admin area.
 *
 *  Add prefix file_ or db_ to configuration keys to know if database update is needed
 */
class Conf implements \ArrayAccess
{
    private $conn, $keys = [];
    const FILE_PREFIX = 'file_';
    const DB_PREFIX = 'db_';

    public function __construct(DBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function loadFromFile($conf_file)
    {
        if (!is_readable($conf_file)) {
            return;
        }

        // in case of unwanted output
        ob_start();
        require $conf_file;
        ob_end_clean();

        foreach ($conf as $key => $value) {
            $this->keys[self::FILE_PREFIX . $key] = $value;
        }
        unset($conf);
    }

    /**
     * Add configuration parameters from database to global $conf array
     *
     * @param string $condition SQL condition
     * @return void
     */
    public function loadFromDB($condition = '')
    {
        $query = 'SELECT param, value  FROM ' . CONFIG_TABLE;
        if (!empty($condition)) {
            $query .= ' WHERE ' . $condition;
        }

        $result = $this->conn->db_query($query);
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $value = isset($row['value']) ? $row['value'] : '';
            if ($this->conn->is_boolean($value)) {
                $value = $this->conn->get_boolean($value);
            }
            $this->keys[self::DB_PREFIX . $row['param']] = $value;
        }
    }

    /**
     * Add or update a config parameter
     *
     * @param string $param
     * @param string $value
     */
    protected function addOrUpdateParam($param, $value)
    {
        if (is_array($value) || is_object($value)) {
            $dbValue = json_encode($value);
        } else {
            $dbValue = $this->conn->boolean_to_string($value);
        }

        $query = 'SELECT count(1) FROM ' . CONFIG_TABLE;
        $query .= ' WHERE param = \'' . $this->conn->db_real_escape_string($param) . '\'';

        list($counter) = $this->conn->db_fetch_row($this->conn->db_query($query));
        if ($counter == 0) {
            $query = 'INSERT INTO ' . CONFIG_TABLE . ' (param, value)';
            $query .= ' VALUES(\'' . $this->conn->db_real_escape_string($param) . '\', \'' . $this->conn->db_real_escape_string($dbValue) . '\')';
            $this->conn->db_query($query);
        } else {
            $query = 'UPDATE ' . CONFIG_TABLE;
            $query .= ' SET value = \'' . $this->conn->db_real_escape_string($dbValue) . '\'';
            $query .= ' WHERE param = \'' . $this->conn->db_real_escape_string($param) . '\'';
            $this->conn->db_query($query);
        }

        $this->keys[self::DB_PREFIX . $param] = $value;
    }

    // ArrayAccess methods
    public function offsetExists($param)
    {
        return isset($this->keys[self::FILE_PREFIX . $param]) || isset($this->keys[self::DB_PREFIX . $param]);
    }

    public function offsetGet($param)
    {
        if (isset($this->keys[self::DB_PREFIX . $param])) {
            return $this->keys[self::DB_PREFIX . $param];
        } elseif (isset($this->keys[self::FILE_PREFIX . $param])) {
            return $this->keys[self::FILE_PREFIX . $param];
        } else {
            return null;
        }
    }

    public function offsetSet($param, $value)
    {
        if (isset($this->keys[self::FILE_PREFIX . $param])) {
            $this->keys[self::FILE_PREFIX . $param] = $value;
        } else {
            // if new key add it to database
            $this->keys[self::DB_PREFIX . $param] = $value;
            $this->addOrUpdateParam($param, $value);
        }
    }

    public function offsetUnset($param)
    {
        if (isset($this->keys[self::DB_PREFIX . $param])) {
            unset($this->keys[self::DB_PREFIX . $param]);
            $this->deleteParam($param);
        } elseif (isset($this->keys[self::FILE_PREFIX . $param])) {
            unset($this->keys[self::FILE_PREFIX . $param]);
        }
    }

    /**
     * Delete one or more config parameters
     *
     * @param string|string[] $params
     */
    protected function deleteParam($params)
    {
        if (!is_array($params)) {
            $params = array($params);
        }

        if (empty($params)) {
            return;
        }

        $query = 'DELETE FROM ' . CONFIG_TABLE;
        $query .= ' WHERE param ' . $this->conn->in($params);
        $this->conn->db_query($query);

        foreach ($params as $param) {
            unset($this->keys[self::DB_PREFIX . $param]);
        }
    }

}