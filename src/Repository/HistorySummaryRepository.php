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
    public function getSummary(? int $year = null, ? int $month = null, ? int $day = null)
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

    public function getSummaryToUpdate(int $year, ? int $month = null, ? int $day = null, ? int $hour = null)
    {
        $query = 'SELECT id, year, month, day, hour, nb_pages FROM ' . self::HISTORY_SUMMARY_TABLE;
        $query .= ' WHERE year = ' . $year;
        $query .= ' AND ( month IS NULL OR ( month=' . $month . ' AND ( day is NULL OR (day=' . $day . ' AND (hour IS NULL OR hour=' . $hour . ')))))';

        return $this->conn->db_query($query);
    }

    public function deleteAll()
    {
        $query = 'DELETE FROM ' . self::HISTORY_SUMMARY_TABLE;

        return $this->conn->db_query($query);
    }

    public function massUpdates(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::HISTORY_SUMMARY_TABLE, $fields, $datas);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::HISTORY_SUMMARY_TABLE, $fields, $datas);
    }
}
