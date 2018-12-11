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

class ThemeRepository extends BaseRepository
{
    public function findAll()
    {
        $query = 'SELECT id, version, name FROM ' . self::THEMES_TABLE;
        $query .= ' ORDER BY name ASC';

        return $this->conn->db_query($query);
    }

    public function addTheme(string $id, string $version, string $name)
    {
        return $this->conn->single_insert(self::THEMES_TABLE, ['id' => $id, 'version' => $version, 'name' => $name], false);
    }

    public function findById(string $theme_id)
    {
        $query = 'SELECT id FROM ' . self::THEMES_TABLE;
        $query .= ' WHERE id != \'' . $this->conn->db_real_escape_string($theme_id) . '\'';

        return $this->conn->db_query($query);
    }

    public function findExcept(array $ids)
    {
        $query = 'SELECT id,name  FROM ' . self::THEMES_TABLE;
        $query .= ' WHERE id NOT ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function deleteById(string $theme_id)
    {
        $query = 'DELETE FROM ' . self::THEMES_TABLE;
        $query .= ' WHERE id \'' . $this->conn->db_real_escape_string($theme_id) . '\'';
        $this->conn->db_query($query);
    }

    public function deleteByIds(array $theme_ids)
    {
        $query = 'DELETE FROM ' . self::THEMES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($theme_ids);
        $this->conn->db_query($query);
    }
}
