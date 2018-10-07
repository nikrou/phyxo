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

class ImageCategoryRepository extends BaseRepository
{
    public function count() : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::IMAGE_CATEGORY_TABLE;
        $result = $this->conn->db_query($query);
        list($nb_image_category) = $this->conn->db_fetch_row($result);

        return $nb_image_category;
    }

    public function countByCategory(? int $category_id = null)
    {
        $query = 'SELECT category_id, COUNT(1) AS counter FROM ' . self::IMAGE_CATEGORY_TABLE;

        if (!is_null($category_id)) {
            $query .= ' WHERE category_id = ' . $category_id;
        }

        $query .= ' GROUP BY category_id';

        return $this->conn->db_query($query);
    }

    public function countTotalImages(array $forbidden_categories, string $access_type, array $image_ids) : int
    {
        $query = 'SELECT COUNT(DISTINCT(image_id)) as total FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id NOT' . $this->conn->in($forbidden_categories);

        if ($access_type === 'NOT IN') {
            $query .= ' AND image_id NOT ' . $this->conn->in($image_ids);
        } else {
            $query .= ' AND image_id ' . $this->conn->in($image_ids);
        }

        $result = $this->conn->db_query($query);
        list($nb_total_images) = $this->conn->db_fetch_row($result);

        return $nb_total_images;
    }

    public function findDistinctCategoryId(int $category_id)
    {
        $query = 'SELECT DISTINCT category_id FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id = ' . $category_id;
        $query .= ' LIMIT 1';

        return $this->conn->db_query($query);
    }

    public function findCategoriesWithImages()
    {
        $query = 'SELECT category_id, COUNT(1) AS nb_photos FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' GROUP BY category_id;';

        return $this->conn->db_query($query);
    }

    public function findRandomRepresentant(int $category_id)
    {
        $query = 'SELECT image_id FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id = ' . $category_id;
        $query .= ' ORDER BY ' . $this->conn::RANDOM_FUNCTION . '()  LIMIT 1';

        return $this->conn->db_query($query);
    }

    public function dateOfCategories(array $category_ids)
    {
        $query = 'SELECT category_id, MIN(date_creation) AS _from,';
        $query .= ' MAX(date_creation) AS _to FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGES_TABLE . ' ON image_id = id';
        $query .= ' WHERE category_id ' . $this->conn->in($category_ids);
        $query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(['visible_categories' => 'category_id', 'visible_images' => 'id'], 'AND');
        $query .= ' GROUP BY category_id';

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

        if (!$isAdmin) {
            $query .= ' AND validated = \'' . $this->conn->boolean_to_db(true) . '\'';
        }

        list($nb_available_comments) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_available_comments;
    }

    public function getRelatedCategory(int $image_id)
    {
        $query = 'SELECT id,uppercats,commentable,visible,status,global_rank  FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' ON category_id = id';
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(['forbidden_categories' => 'id', 'visible_categories' => 'id'], ' AND ');

        return $this->conn->db_query($query);
    }

    public function getCategoryWithLastPhotoAdded() : int
    {
        $query = 'SELECT category_id FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' ORDER BY image_id DESC LIMIT 1';
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return $row['category_id'];
    }

    public function deleteByCategory(array $ids = [], array $image_ids = [])
    {
        if (count($ids) === 0 && count($image_ids) === 0) {
            return;
        }

        $query = 'DELETE FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE ';

        if (count($ids) > 0) {
            $query .= ' category_id ' . $this->conn->in($ids);
        }

        if (count($image_ids) > 0) {
            if (count($ids) > 0) {
                $query .= ' AND ';
            }
            $query .= ' image_id ' . $this->conn->in($image_ids);
        }

        $this->conn->db_query($query);
    }

    public function deleteBy(string $field, array $values)
    {
        $query = 'DELETE  FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE ' . $field . ' ' . $this->conn->in($values);
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

    public function findByImageId(int $image_id)
    {
        $query = 'SELECT category_id FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE image_id = ' . $image_id;

        return $this->conn->db_query($query);
    }

    public function isImageAssociatedToCategory(int $image_id, int $category_id)
    {
        $query = 'SELECT COUNT(1) FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= ' AND category_id = ' . $category_id;
        list($count) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $count === 1;
    }

    public function maxRankForCategory(int $category_id) : int
    {
        $query = 'SELECT MAX(rank) AS max_rank FROM ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id = ' . $category_id;
        $row = $this->conn->db_fetch_assoc($this->conn->db_query($query));

        return $row['max_rank'];
    }

    public function updateRankForCategory(int $rank, int $category_id)
    {
        $query = 'UPDATE ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' SET rank = rank + 1';
        $query .= ' WHERE category_id = ' . $category_id;
        $query .= ' AND rank IS NOT NULL AND rank >= ' . $rank;
        $this->conn->db_query($query);
    }

    public function updateRankForImage(int $rank, int $image_id, int $category_id)
    {
        $query = 'UPDATE ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' SET rank = ' . $rank;
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= ' AND category_id = ' . $category_id;
        $this->conn->db_query($query);
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

    public function massUpdates(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::IMAGE_CATEGORY_TABLE, $fields, $datas);
    }
}
