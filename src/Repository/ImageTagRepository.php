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
    public function count() : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::IMAGE_TAG_TABLE;
        $result = $this->conn->db_query($query);
        list($nb_image_tag) = $this->conn->db_fetch_row($result);

        return $nb_image_tag;
    }

    public function findBy(string $field, string $value)
    {
        $query = 'SELECT id FROM ' . self::IMAGE_TAG_TABLE;
        $query .= sprintf(' WHERE %s = \'%s\'', $field, $this->conn->db_real_escape_string($value));

        return $this->conn->db_query($query);
    }

    public function findImageIds()
    {
        $query = 'SELECT DISTINCT image_id FROM ' . self::IMAGE_TAG_TABLE;

        return $this->conn->db_query($query);
    }

    public function findImageByTags(array $tag_ids)
    {
        $query = 'SELECT DISTINCT(image_id)  FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE tag_id ' . $this->conn->in($tag_ids);

        return $this->conn->db_query($query);
    }

    public function findImageTags(array $tag_ids, array $image_ids)
    {
        $query = 'SELECT image_id, ' . $this->conn->db_group_concat('tag_id') . ' AS tag_ids FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE tag_id ' . $this->conn->in($tag_ids);
        $query .= ' AND image_id ' . $this->conn->in($image_ids) . ' GROUP BY image_id';

        return $this->conn->db_query($query);
    }

    public function getTagCounters()
    {
        $query = 'SELECT tag_id, COUNT(image_id) AS counter FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' GROUP BY tag_id';

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

    public function deleteByImagesAndTags(array $image_ids, array $tag_ids)
    {
        $query = 'DELETE FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE image_id ' . $this->conn->in($image_ids);
        $query .= ' AND tag_id ' . $this->conn->in($tag_ids);
        $this->conn->db_query($query);
    }
}
