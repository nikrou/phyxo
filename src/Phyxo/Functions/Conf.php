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

namespace Phyxo\Functions;

class Conf
{
    /**
     * Add configuration parameters from database to global $conf array
     *
     * @param string $condition SQL condition
     * @return void
     */
    public static function load_conf_from_db($condition = '')
    {
        global $conf, $conn;

        $query = 'SELECT param, value  FROM ' . CONFIG_TABLE;
        if (!empty($condition)) {
            $query .= ' WHERE ' . $condition;
        }
        $result = $conn->db_query($query);

        if (($conn->db_num_rows($result) == 0) and !empty($condition)) {
            fatal_error('No configuration data');
        }

        while ($row = $conn->db_fetch_assoc($result)) {
            $val = isset($row['value']) ? $row['value'] : '';
            if ($conn->is_boolean($val)) {
                $val = $conn->get_boolean($val);
            }
            $conf[$row['param']] = $val;
        }

        \Phyxo\Functions\Plugin::trigger_notify('load_conf', $condition);
    }

    /**
     * Add or update a config parameter
     *
     * @param string $param
     * @param string $value
     * @param boolean $updateGlobal update global *$conf* variable
     * @param callable $parser function to apply to the value before save in database
     * (eg: serialize, json_encode) will not be applied to *$conf* if *$parser* is *true*
     */
    public static function conf_update_param($param, $value, $updateGlobal = false, $parser = null)
    {
        global $conf, $conn;

        if ($parser != null) {
            $dbValue = call_user_func($parser, $value);
        } elseif (is_array($value) || is_object($value)) {
            $dbValue = json_encode($value);
        } else {
            $dbValue = $conn->boolean_to_string($value);
        }

        $query = 'SELECT count(1) FROM ' . CONFIG_TABLE;
        $query .= ' WHERE param = \'' . $conn->db_real_escape_string($param) . '\'';

        list($counter) = $conn->db_fetch_row($conn->db_query($query));
        if ($counter == 0) {
            $query = 'INSERT INTO ' . CONFIG_TABLE . ' (param, value)';
            $query .= ' VALUES(\'' . $conn->db_real_escape_string($param) . '\', \'' . $conn->db_real_escape_string($dbValue) . '\')';
            $conn->db_query($query);
        } else {
            $query = 'UPDATE ' . CONFIG_TABLE;
            $query .= ' SET value = \'' . $conn->db_real_escape_string($dbValue) . '\'';
            $query .= ' WHERE param = \'' . $conn->db_real_escape_string($param) . '\'';
            $conn->db_query($query);
        }

        if ($updateGlobal) {
            $conf[$param] = $value;
        }
    }

    /**
     * Delete one or more config parameters
     *
     * @param string|string[] $params
     */
    public static function conf_delete_param($params)
    {
        global $conf, $conn;

        if (!is_array($params)) {
            $params = array($params);
        }
        if (empty($params)) {
            return;
        }

        $query = 'DELETE FROM ' . CONFIG_TABLE;
        $query .= ' WHERE param ' . $conn->in($params);
        $conn->db_query($query);

        foreach ($params as $param) {
            unset($conf[$param]);
        }
    }

}