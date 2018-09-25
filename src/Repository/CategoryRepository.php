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

class CategoryRepository extends BaseRepository
{
    public function count(string $condition = '') : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::CATEGORIES_TABLE;
        if (!empty($condition)) {
            $query .= ' WHERE ' . $condition;
        }
        list($nb_categories) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_categories;
    }

    public function findAll(array $ids = [])
    {
        $query = 'SELECT id, name, id_uppercat, comment, dir, rank, status, site_id, visible, representative_picture_id, uppercats,';
        $query .= ' commentable, global_rank, image_order, permalink, lastmodified FROM ' . self::CATEGORIES_TABLE;

        if (count($ids) > 0) {
            $query .= ' WHERE id ' . $this->conn->in($ids);
        }

        return $this->conn->db_query($query);
    }

    public function findByDir(string $condition)
    {
        $query = 'SELECT id, dir  FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE dir \'' . $condition . '\'';

        return $this->conn->db_query($query);
    }

    public function findByIdsAndDir(array $ids, string $condition)
    {
        $query = 'SELECT id, uppercats, site_id FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE dir \'' . $condition . '\'';
        $query .= ' AND id ' . $this->conn->in($cat_ids);

        return $this->conn->db_query($query);
    }

    public function findByIdsAndStatus(array $ids, string $status)
    {
        $query = 'SELECT id  FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' AND status = \'' . $status . '\'';

        return $this->conn->db_query($query);
    }

    public function findWrongRepresentant(string $where_cats)
    {
        $query = 'SELECT DISTINCT c.id FROM ' . self::CATEGORIES_TABLE . ' AS c';
        $query .= ' LEFT JOIN ' . self::IMAGES_TABLE . ' AS i ON c.representative_picture_id = i.id';
        $query .= ' WHERE representative_picture_id IS NOT NULL';
        $query .= ' AND ' . sprintf($where_cats, 'c.id') . ' AND i.id IS NULL;';

        return $this->conn->db_query($query);
    }

    public function findRandomRepresentant(string $where_cats)
    {
        $query = 'SELECT DISTINCT id FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = category_id';
        $query .= ' WHERE representative_picture_id IS NULL';
        $query .= ' AND ' . sprintf($where_cats, 'category_id');

        return $this->conn->db_query($query);
    }

    public function getComputedCategories(array $userdata, $filter_days)
    {
        $query = 'SELECT c.id AS cat_id, id_uppercat';
        $query .= ', MAX(date_available) AS date_last, COUNT(date_available) AS nb_images FROM ' . self::CATEGORIES_TABLE . ' as c';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON ic.category_id = c.id';
        $query .= ' LEFT JOIN ' . self::IMAGES_TABLE . ' AS i ON ic.image_id = i.id AND i.level<=' . $userdata['level'];

        if (isset($filter_days)) {
            $query .= ' AND i.date_available > ' . $this->conn->db_get_recent_period_expression($filter_days);
        }

        if (!empty($userdata['forbidden_categories'])) {
            $query .= ' WHERE c.id NOT IN (' . $userdata['forbidden_categories'] . ')';
        }

        $query .= ' GROUP BY c.id';

        return $this->conn->db_query($query);
    }

    public function findById(int $id) : array
    {
        $query = 'SELECT * FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE id = ' . $id;
        $result = $this->conn->db_query($query);

        return $this->conn->db_fetch_assoc($result);
    }

    public function findByIds(array $ids, ? string $status = null)
    {
        $query = 'SELECT * FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        if (!is_null($status)) {
            $query .= ' AND status = \'' . $status . '\'';
        }

        return $this->conn->db_query($query);
    }

    public function insertCategory(array $datas) : int
    {
        return $this->conn->single_insert(self::CATEGORIES_TABLE, $datas);
    }

    public function updateCategory(array $datas, int $id)
    {
        $this->conn->single_update(self::CATEGORIES_TABLE, $datas, ['id' => $id]);
    }

    public function updateCategories(array $fields, array $ids)
    {
        $query = 'UPDATE ' . self::CATEGORIES_TABLE;
        $query .= ' SET ';
        foreach ($fields as $key => $value) {
            if (is_bool($value)) {
                $query .= $key . ' = \'' . $this->conn->boolean_to_db($value) . '\'';
            } elseif (!empty($value)) {
                $query .= $key . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
            } else {
                $query .= $key . ' IS NULL';
            }
            $query .= ' WHERE id ' . $this->conn->in($ids);

            $this->conn->db_query($query);
        }
    }

    public function massUpdatesCategories(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::CATEGORIES_TABLE, $fields, $datas);
    }

    /**
     * Find a random photo among all photos inside an album (including sub-albums)
     */
    public function getRandomImageInCategory(array $category, bool $recursive = true)
    {
        $image_id = null;
        if ($category['count_images'] > 0) {
            $query = 'SELECT image_id FROM ' . self::CATEGORIES_TABLE . ' AS c';
            $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON ic.category_id = c.id WHERE ';
            if ($recursive) {
                $query .= '(c.id=' . $category['id'] . ' OR uppercats LIKE \'' . $category['uppercats'] . ',%\')';
            } else {
                $query .= ' c.id=' . $category['id'];
            }
            $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'c.id',
                    'visible_categories' => 'c.id',
                    'visible_images' => 'image_id',
                ],
                ' AND '
            );
            $query .= ' ORDER BY ' . $this->conn::RANDOM_FUNCTION . '() LIMIT 1;';
            $result = $this->conn->db_query($query);
            if ($this->conn->db_num_rows($result) > 0) {
                list($image_id) = $this->conn->db_fetch_row($result);
            }
        }

        return $image_id;
    }

    public function getCategoriesForMenu($user, bool $filter_enabled, array $ids_uppercat = [])
    {
        $query = 'SELECT id, name, permalink, nb_images, global_rank,uppercats,';
        $query .= 'date_last, max_date_last, count_images, count_categories';
        $query .= ' FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::USER_CACHE_CATEGORIES_TABLE;
        $query .= ' ON id = cat_id and user_id = ' . $user['id'];

        $query .= ' WHERE';
        // Always expand when filter is activated
        if (!$user['expand'] and !$filter_enabled) {
            $query .= ' (id_uppercat is NULL';
            if (!empty($ids_uppercat)) {
                $query .= ' OR id_uppercat ' . $this->conn->in($ids_uppercat);
            }
            $query .= ')';
        } else {
            $query = ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(['visible_categories' => 'id'], null, true);
        }

        return $this->conn->db_query($query);
    }

    /**
     * Returns all subcategory identifiers of given category ids
     */
    public function getSubcatIds(array $ids) : array
    {
        $query = 'SELECT DISTINCT(id) FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE ';

        foreach ($ids as $num => $category_id) {
            if ($num > 0) {
                $query .= ' OR ';
            }
            $query .= 'uppercats ' . $this->conn::REGEX_OPERATOR . ' \'(^|,)' . $this->conn->db_real_escape_string($category_id) . '(,|$)\'';
        }

        return $this->conn->query2array($query, null, 'id');
    }

    public function deleteByIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::CATEGORIES_TABLE;
        $query = ' WHERE id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }
}
