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

class OldPermalinkRepository extends BaseRepository
{
    public function findAll(string $order = '')
    {
        $query = 'SELECT cat_id, permalink, date_deleted, last_hit, hit FROM ' . self::OLD_PERMALINKS_TABLE;

        if (!empty($order)) {
            $query .= ' ORDER BY ' . $order;
        }

        return $this->conn->db_query($query);
    }

    public function addOldPermalink(array $datas) : int
    {
        return $this->conn->single_insert(self::OLD_PERMALINKS_TABLE, $datas);
    }

    public function deleteByCatIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::OLD_PERMALINKS_TABLE;
        $query .= ' WHERE cat_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function deleteByCatIdAndPermalink(int $cat_id, string $permalink)
    {
        $query = 'DELETE FROM ' . self::OLD_PERMALINKS_TABLE;
        $query .= ' WHERE cat_id = ' . $cat_id;
        $query .= ' AND permalink = \'' . $this->conn->db_real_escape_string($permalink) . '\'';
        $this->conn->db_query($query);
    }

    public function deleteByPermalink(string $permalink)
    {
        $query = 'DELETE FROM ' . self::OLD_PERMALINKS_TABLE;
        $query .= ' WHERE permalink = \'' . $this->conn->db_real_escape_string($permalink) . '\'';

        return $this->conn->db_query($query);
    }

    public function findCategoryFromPermalinks(array $permalinks)
    {
        $query = 'SELECT cat_id AS id, permalink, 1 AS is_old FROM ' . self::OLD_PERMALINKS_TABLE;
        $query .= ' WHERE permalink ' . $this->conn->in($permalinks);
        $query .= ' UNION ';
        $query .= ' SELECT id, permalink, 0 AS is_old FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE permalink ' . $this->conn->in($permalinks);

        return $this->conn->db_query($query);
    }

    public static function getCategoryIdFromOldPermalink(string $permalink) : string
    {
        $query = 'SELECT c.id FROM ' . self::OLD_PERMALINKS_TABLE . ' AS op';
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' AS c ON op.cat_id=c.id';
        $query .= ' WHERE op.permalink=\'' . $this->conn->db_real_escape_string($permalink) . '\'';
        $query .= ' LIMIT 1';
        $result = $this->conn->db_query($query);

        $cat_id = null;
        if ($this->conn->db_num_rows($result)) {
            list($cat_id) = $this->conn->db_fetch_row($result);
        }

        return $cat_id;
    }

    public function updateOldPermalink(string $permalink, int $cat_id)
    {
        $query = ' UPDATE ' . self::OLD_PERMALINKS_TABLE . ' SET last_hit = NOW(), hit = hit + 1 ';
        $query .= ' WHERE permalink = \'' . $permalink . '\' AND cat_id=' . $cat_id;

        $this->conn->db_query($query);
    }
}
