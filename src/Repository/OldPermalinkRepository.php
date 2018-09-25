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
    public function deleteByCatIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::OLD_PERMALINKS_TABLE;
        $query .= ' WHERE cat_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
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

    public function updateOldPermalink(string $permalink, int $cat_id)
    {
        $query = ' UPDATE ' . self::OLD_PERMALINKS_TABLE . ' SET last_hit = NOW(), hit = hit + 1 ';
        $query .= ' WHERE permalink = \'' . $permalink . '\' AND cat_id=' . $cat_id;

        $this->conn->db_query($query);
    }
}
