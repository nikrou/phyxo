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

class SQL
{
    public static function addOrderByFields($order_by_string)
    {
        return str_ireplace(array('order by', ' asc', ' desc'), array('', '', ''), $order_by_string);
    }


    /**
     * Compute sql WHERE condition with restrict and filter data.
     * "FandF" means Forbidden and Filters.
     *
     * @param array $condition_fields one witch fields apply each filter
     *    - forbidden_categories
     *    - visible_categories
     *    - forbidden_images
     *    - visible_images
     * @param string $prefix_condition prefixes query if condition is not empty
     * @param boolean $force_one_condition use at least "1 = 1"
     * @return string
     */
    public static function get_sql_condition_FandF($condition_fields, $prefix_condition = null, $force_one_condition = false)
    {
        global $user, $filter, $conn;

        $sql_list = array();

        foreach ($condition_fields as $condition => $field_name) {
            switch ($condition) {
                case 'forbidden_categories':
                    {
                        if (!empty($user['forbidden_categories'])) {
                            $sql_list[] = $field_name . ' NOT IN (' . $user['forbidden_categories'] . ')';
                        }
                        break;
                    }
                case 'visible_categories':
                    {
                        if (!empty($filter['visible_categories'])) {
                            $sql_list[] = $field_name . ' IN (' . $filter['visible_categories'] . ')';
                        }
                        break;
                    }
                case 'visible_images':
                    if (!empty($filter['visible_images'])) {
                        $sql_list[] = $field_name . ' IN (' . $filter['visible_images'] . ')';
                    }
                // note there is no break - visible include forbidden
                case 'forbidden_images':
                    if (!empty($user['image_access_list']) or $user['image_access_type'] != 'NOT IN') {
                        $table_prefix = null;
                        if ($field_name == 'id') {
                            $table_prefix = '';
                        } elseif ($field_name == 'i.id') {
                            $table_prefix = 'i.';
                        }
                        if (isset($table_prefix)) {
                            $sql_list[] = $table_prefix . 'level<=' . $user['level'];
                        } elseif (!empty($user['image_access_list']) and !empty($user['image_access_type'])) {
                            $sql_list[] = $field_name . ' ' . $user['image_access_type'] . ' (' . $user['image_access_list'] . ')';
                        }
                    }
                    break;
                default:
                    {
                        die('Unknown condition: ' . $condition);
                        break;
                    }
            }
        }

        if (count($sql_list) > 0) {
            $sql = '(' . implode(' AND ', $sql_list) . ')';
        } else {
            $sql = $force_one_condition ? '1 = 1' : '';
        }

        if (isset($prefix_condition) and !empty($sql)) {
            $sql = $prefix_condition . ' ' . $sql;
        }

        return $sql;
    }

    /**
     * Returns sql WHERE condition for recent photos/albums for current user.
     *
     * @param string $db_field
     * @return string
     */
    public static function get_recent_photos($db_field)
    {
        global $user, $conn;

        if (!isset($user['last_photo_date'])) {
            return '0=1';
        }

        return $db_field . '>=LEAST('
            . $conn->db_get_recent_period_expression($user['recent_period'])
            . ',' . $conn->db_get_recent_period_expression(1, $user['last_photo_date']) . ')';
    }

}