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

class HistorySummaryRepository extends BaseRepository
{
    public function getSummary(int $year = null, int $month = null, int $day = null)
    {
        $query = 'SELECT year, month, day, hour, nb_pages FROM ' . self::HISTORY_SUMMARY_TABLE;

        if (!is_null($day)) {
            $query .= ' WHERE year = ' . $year . ' AND month = ' . $month;
            $query .= ' AND day = ' . $day . ' AND hour IS NOT NULL';
            $query .= ' ORDER BY year ASC, month ASC, day ASC, hour ASC;';
        } elseif (!is_null($month)) {
            $query .= ' WHERE year = ' . $year . ' AND month = ' . $month;
            $query .= ' AND day IS NOT NULL AND hour IS NULL';
            $query .= ' ORDER BY year ASC, month ASC, day ASC;';
        } elseif (!is_null($year)) {
            $query .= ' WHERE year = ' . $year . ' AND month IS NOT NULL';
            $query .= ' AND day IS NULL';
            $query .= ' ORDER BY year ASC, month ASC;';
        } else {
            $query .= ' WHERE year IS NOT NULL';
            $query .= ' AND month IS NULL';
            $query .= ' ORDER BY year ASC;';
        }

        return $this->conn->db_query($query);
    }

    public function getHistory($data, $search, $type)
    {
        if (isset($search['fields']['filename'])) {
            $query = 'SELECT id FROM ' . IMAGES_TABLE;
            $query .= ' WHERE file LIKE \'' . $search['fields']['filename'] . '\'';
            $search['image_ids'] = $conn->query2array($query, null, 'id');
        }

        $clauses = [];

        if (isset($search['fields']['date-after'])) {
            $clauses[] = "date >= '" . $search['fields']['date-after'] . "'";
        }

        if (isset($search['fields']['date-before'])) {
            $clauses[] = "date <= '" . $search['fields']['date-before'] . "'";
        }

        if (isset($search['fields']['types'])) {
            $local_clauses = [];

            foreach ($types as $type) {
                if (in_array($type, $search['fields']['types'])) {
                    $clause = 'image_type ';
                    if ($type == 'none') {
                        $clause .= 'IS NULL';
                    } else {
                        $clause .= "= '" . $type . "'";
                    }

                    $local_clauses[] = $clause;
                }
            }

            if (count($local_clauses) > 0) {
                $clauses[] = implode(' OR ', $local_clauses);
            }
        }

        if (isset($search['fields']['user']) and $search['fields']['user'] != -1) {
            $clauses[] = 'user_id = ' . $search['fields']['user'];
        }

        if (isset($search['fields']['image_id'])) {
            $clauses[] = 'image_id = ' . $search['fields']['image_id'];
        }

        if (isset($search['fields']['filename'])) {
            if (count($search['image_ids']) == 0) {
                // a clause that is always false
                $clauses[] = '1 = 2 ';
            } else {
                $clauses[] = 'image_id ' . $conn->in($search['image_ids']);
            }
        }

        if (isset($search['fields']['ip'])) {
            $clauses[] = 'ip LIKE \'' . $search['fields']['ip'] . '\'';
        }

        $clauses = \Phyxo\Functions\Utils::prepend_append_array_items($clauses, '(', ')');

        $where_separator = implode(' AND ', $clauses);

        $query = 'SELECT date,time,user_id,ip,section,category_id,tag_ids,';
        $query .= 'image_id,image_type FROM ' . self::HISTORY_TABLE;
        $query .= ' WHERE ' . $where_separator;

        $result = $conn->db_query($query);

    }
}
