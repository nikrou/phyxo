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

class ImageRepository extends BaseRepository
{
    public function findByFields(string $field, array $ids)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . $field . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function findByField(string $field, string $value)
    {
        $query = 'SELECT id, path FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . $field . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';

        return $this->conn->db_query($query);
    }

    public function searchByField($field, $value)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . $field . ' LIKE \'' . $this->conn->db_real_escape_string($value) . '\'';

        $this->conn->db_query($query);
    }

    public function findWithNoStorageOrStorageCategoryId(array $categories)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE (storage_category_id IS NULL OR storage_category_id NOT ' . $this->conn->in($categories) . ')';

        return $this->conn->db_query($query);
    }
}
