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

class ImageCategoryRepository extends BaseCategory
{
    public function countByCategory()
    {
        $query = 'SELECT category_id, COUNT(1) AS counter FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' GROUP BY category_id;';

        return $this->conn->db_query($query);
    }

    public function getImageIdsLinked(array $ids)
    {
        $query = 'SELECT DISTINCT(image_id) FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function getImageIdsNotOrphans(array $image_ids, array $cat_ids)
    {
        $query = 'SELECT DISTINCT(image_id) FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE image_id ' . $this->conn->in($image_ids);
        $query .= ' AND category_id NOT ' . $this->conn->in($cat_ids);

        return $this->conn->db_query($query);
    }

    public function countAvailableComments(bool $isAdmin = false)
    {
        $query = 'SELECT COUNT(DISTINCT(com.id)) FROM ' . self::IMAGE_CATEGORY_TABLE . ' AS ic';
        $query .= ' LEFT JOIN ' . self::COMMENTS_TABLE . ' AS com ON ic.image_id = com.image_id';
        $query .= ' WHERE ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'forbidden_images' => 'ic.image_id'
            ],
            '',
            true
        );

        if (!$isAdmin()) {
            $query .= ' AND validated = \'' . $this->conn->boolean_to_db(true) . '\'';
        }

        list($nb_available_comments) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_available_comments;
    }

    public function deleteByCategory(array $ids, array $image_ids = [])
    {
        $query = 'DELETE FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id ' . $this->conn->in($ids);

        if (count($image_ids) > 0) {
            $query .= ' AND image_id ' . $this->conn->in($image_ids);
        }

        $this->conn->db_query($query);
    }

    public function findAll(array $image_ids, array $category_ids = [])
    {
        $query = 'SELECT image_id,category_id FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE image_id ' . $this->conn->in($image_ids);

        if (count($category_ids) > 0) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }

        return $this->conn->db_query($query);
    }

    public function findMaxRankForEachCategories(array $ids)
    {
        $query = 'SELECT category_id, MAX(rank) AS max_rank FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE rank IS NOT NULL';
        $query .= ' AND category_id ' . $this->conn->in($ids);
        $query .= ' GROUP BY category_id';

        return $this->conn->db_query($query);
    }

    public function insertImageCategories(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::IMAGE_CATEGORY_TABLE, $fields, $datas);
    }
}
