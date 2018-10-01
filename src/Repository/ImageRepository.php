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
    public function count() : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::IMAGES_TABLE;
        $result = $this->conn->db_query($query);
        list($nb_images) = $this->conn->db_fetch_row($result);

        return $nb_images;
    }

    public function getImagesFromCaddie(array $image_ids, int $user_id)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::CADDIE_TABLE;
        $query .= ' ON id = element_id AND user_id=' . $user_id;
        $query .= ' WHERE id ' . $this->conn->in($image_ids);
        $query .= ' AND element_id IS NULL';

        return $this->conn->db_query($query);
    }

    public function findFirstDate()
    {
        $query = 'SELECT MIN(date_available) FROM ' . self::IMAGES_TABLE;
        $result = $this->conn->db_query($query);

        list($first_date) = $this->conn->db_fetch_row($result);

        return $first_date;
    }

    public function findImageWithNoTag()
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_TAG_TABLE . ' ON id = image_id';
        $query .= ' WHERE tag_id is null';

        return $this->conn->db_query($query);
    }

    public function findImageWithNoAlbum()
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE category_id is null';

        return $this->conn->db_query($query);
    }

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

    public function getRecentImages(string $where_sql, $date_available, int $limit)
    {
        $query = 'SELECT DISTINCT c.uppercats, COUNT(DISTINCT i.id) AS img_count FROM ' . self::IMAGES_TABLE . ' i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON i.id = image_id';
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' c ON c.id = category_id';
        $query .= ' ' . $where_sql;
        $query .= ' AND date_available = \'' . $this->conn->db_real_escape_string($date_available) . '\'';
        $query .= ' GROUP BY category_id, c.uppercats ORDER BY img_count DESC LIMIT ' . $limit;

        return $this->conn->db_query($query);
    }

    public function findCategoryWithLastImageAdded()
    {
        $query = 'SELECT category_id FROM ' . self::IMAGES_TABLE . ' AS i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON image_id = i.id';
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' AS c ON category_id = c.id';
        $query .= ' ORDER BY i.id DESC LIMIT 1';

        return $this->conn->db_query($query);
    }
}
