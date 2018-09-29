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

class ImageTagRepository extends BaseRepository
{
    public function findBy(string $field, string $value)
    {
        $query = 'SELECT id FROM ' . self::IMAGE_TAG_TABLE;
        $query .= sprintf(' WHERE %s = \'%s\'', $key, $this->conn->db_real_escape_string($value));

        return $this->conn->db_query($query);
    }

    public function deleteBy(string $field, array $values)
    {
        $query = 'DELETE  FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE ' . $field . ' ' . $this->conn->in($values);
        $this->conn->db_query($query);
    }

    public function updateImageTags(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::IMAGE_TAG_TABLE, $fields, $datas);
    }

    public function insertImageTags(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::IMAGE_TAG_TABLE, $fields, $datas);
    }

    public function deleteImageTags(array $datas)
    {
        $base_query = 'DELETE FROM ' . self::IMAGE_TAG_TABLE;

        // @TODO: find a better way to delete multiple rows. DBLayer::mass_deletes is buggy.
        foreach ($datas as $data) {
            $query = $base_query;
            $query .= ' WHERE image_id = ' . (int)$data['image_id'];
            $query .= ' AND tag_id = ' . (int)$data['tag_id'];
            $this->conn->db_query($query);
        }
    }
}
