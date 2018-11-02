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

class LanguageRepository extends BaseRepository
{
    public function findAll()
    {
        $query = 'SELECT id, version, name FROM ' . self::LANGUAGES_TABLE;
        $query .= ' ORDER BY name ASC';

        return $this->conn->db_query($query);
    }

    public function addLanguage(string $id, string $name, string $version)
    {
        return $this->conn->single_insert(self::LANGUAGES_TABLE, ['id' => $id, 'name' => $name, 'version' => $version], false);
    }

    public function deleteLanguage(string $id)
    {
        $query = 'DELETE FROM ' . self::LANGUAGES_TABLE;
        $query .= ' WHERE id= \'' . $this->conn->db_real_escape_string($id) . '\'';
        $this->conn->db_query($query);
    }

    public function updateLanguage(array $datas, array $where)
    {
        $this->conn->single_update(self::LANGUAGES_TABLE, $datas, $where);
    }
}
