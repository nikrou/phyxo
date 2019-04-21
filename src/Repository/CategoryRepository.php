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

use App\Entity\User;


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

    public function findAll(? string $order = null)
    {
        $query = 'SELECT id, name, id_uppercat, comment, dir, rank, status, site_id, visible, representative_picture_id, uppercats,';
        $query .= ' commentable, global_rank, image_order, permalink, lastmodified FROM ' . self::CATEGORIES_TABLE;

        if (!is_null($order)) {
            $query .= ' ORDER BY ' . $order;
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
        $query .= ' AND id ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function findByIdsAndStatus(array $ids, string $status)
    {
        $query = 'SELECT id  FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' AND status = \'' . $status . '\'';

        return $this->conn->db_query($query);
    }

    public function findWithCondition(array $where)
    {
        $query = 'SELECT id, name, id_uppercat, comment, dir, rank, status, site_id, visible, representative_picture_id, uppercats,';
        $query .= ' commentable, global_rank, image_order, permalink, lastmodified FROM ' . self::CATEGORIES_TABLE;

        if (!empty(array_filter($where))) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        return $this->conn->db_query($query);
    }

    public function hasAccessToImage(int $image_id) : bool
    {
        $query = 'SELECT id FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON category_id = id';
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(['forbidden_categories' => 'category_id', 'forbidden_images' => 'image_id'], ' AND ');
        $query .= ' LIMIT 1';

        return ($this->conn->db_num_rows($this->conn->db_query($query)) >= 1);
    }

    public function findCommentable(int $image_id)
    {
        $query = 'SELECT DISTINCT image_id  FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON category_id = id';
        $query .= ' WHERE commentable = \'' . $this->conn->boolean_to_db(true) . '\'';
        $query .= ' AND image_id = ' . $image_id;
        $query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(
            [
                'forbidden_categories' => 'id',
                'visible_categories' => 'id',
                'visible_images' => 'image_id'
            ],
            ' AND '
        );

        return $this->conn->db_query($query);
    }

    public function findCategoriesForImage(int $image_id)
    {
        $query = 'SELECT c.id, category_id, uppercats FROM ' . self::CATEGORIES_TABLE . ' AS c';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON c.id = ic.category_id';
        $query .= ' WHERE image_id = ' . $image_id;

        return $this->conn->db_query($query);
    }

    public function findRelative(int $image_id)
    {
        $query = 'SELECT id, name, permalink, uppercats, global_rank, commentable FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON category_id = id';
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(['forbidden_categories' => 'category_id'], ' AND ');

        return $this->conn->db_query($query);
    }

    public function findWithUserAndCondition(int $user_id, array $where, string $order = '')
    {
        $query = 'SELECT id, name, comment, permalink, uppercats, global_rank, id_uppercat,';
        $query .= 'nb_images, count_images AS total_nb_images, representative_picture_id,';
        $query .= 'user_representative_picture_id, count_images, count_categories,';
        $query .= 'date_last, max_date_last, count_categories AS nb_categories';
        $query .= ' FROM ' . self::CATEGORIES_TABLE;
        $query .= ' INNER JOIN ' . self::USER_CACHE_CATEGORIES_TABLE;
        $query .= ' ON id = cat_id AND user_id = ' . $user_id;
        $query .= ' WHERE ' . implode(' AND ', $where);

        if ($order) {
            $query .= ' ORDER BY ' . $order;
        }

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

    public function findRandomRepresentantAmongSubCategories(int $user_id, string $uppercats)
    {
        $query = 'SELECT representative_picture_id FROM ' . self::CATEGORIES_TABLE;
        $query .= ' INNER JOIN ' . self::USER_CACHE_CATEGORIES_TABLE;
        $query .= ' ON id = cat_id AND user_id=' . $user_id;
        $query .= ' WHERE uppercats LIKE \'' . $this->conn->db_real_escape_string($uppercats) . ',%\' AND representative_picture_id IS NOT NULL';
        $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(['visible_categories' => 'id'], ' AND ');
        $query .= ' ORDER BY ' . $this->conn::RANDOM_FUNCTION . '() LIMIT 1';

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
            $query .= ' WHERE c.id NOT ' . $this->conn->in(explode(',', $userdata['forbidden_categories']));
        }

        $query .= ' GROUP BY c.id';

        return $this->conn->db_query($query);
    }

    public function findPhysicalsBySite(int $site_id, ? int $category_id = null, bool $recursive)
    {
        $query = 'SELECT id FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE site_id = ' . $site_id . ' AND dir IS NOT NULL';
        if (!is_null($category_id)) {
            if ($recursive) {
                $query .= ' AND uppercats ' . $this->conn::REGEX_OPERATOR . ' \'(^|,)' . $category_id . '(,|$)\'';
            } else {
                $query .= ' AND id = ' . $category_id;
            }
        }

        return $this->conn->db_query($query);
    }

    public function findWithSubCategories()
    {
        $query = 'SELECT id_uppercat, MAX(rank)+1 AS next_rank FROM ' . self::CATEGORIES_TABLE;
        $query .= ' GROUP BY id_uppercat';

        return $this->conn->db_query($query);
    }

    public function getNextId() : int
    {
        return $this->conn->db_nextval('id', self::CATEGORIES_TABLE);
    }

    public function findById(int $id) : array
    {
        $query = 'SELECT id, name, id_uppercat, comment, dir, rank, status, site_id, visible, representative_picture_id, uppercats,';
        $query .= ' commentable, global_rank, image_order, permalink, lastmodified FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE id = ' . $id;
        $result = $this->conn->db_query($query);

        return $this->conn->db_fetch_assoc($result);
    }

    public function findByIds(array $ids, ? string $status = null)
    {
        $query = 'SELECT id, name, id_uppercat, comment, dir, rank, status, site_id, visible, representative_picture_id, uppercats,';
        $query .= ' commentable, global_rank, image_order, permalink, lastmodified FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        if (!is_null($status)) {
            $query .= ' AND status = \'' . $status . '\'';
        }

        return $this->conn->db_query($query);
    }

    public function findByField(string $field, $value)
    {
        $query = 'SELECT id, name, id_uppercat, comment, dir, rank, status, site_id, visible, representative_picture_id, uppercats,';
        $query .= ' commentable, global_rank, image_order, permalink, lastmodified FROM ' . self::CATEGORIES_TABLE;

        if (is_bool($value)) {
            $query .= ' WHERE ' . $field . ' = \'' . $this->conn->boolean_to_db($value) . '\'';
        } elseif (!isset($value) or $value === '') {
            $query .= ' WHERE ' . $field . ' IS null ';
        } else {
            $query .= ' WHERE ' . $field . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
        }

        return $this->conn->db_query($query);
    }

    public function findAllowedSubCategories(string $uppercats)
    {
        $query = 'SELECT id FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE uppercats LIKE \'' . $this->conn->db_real_escape_string($uppercats) . ',%\'';
        $query .= ' AND ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(['forbidden_categories' => 'id', 'visible_categories' => 'id']);

        return $this->conn->db_query($query);
    }

    public function findSitesDetail()
    {
        $query = 'SELECT c.site_id, COUNT(DISTINCT c.id) AS nb_categories, COUNT(i.id) AS nb_images';
        $query .= ' FROM ' . self::CATEGORIES_TABLE . ' AS c';
        $query .= ' LEFT JOIN ' . self::IMAGES_TABLE . ' AS i';
        $query .= ' ON c.id = i.storage_category_id';
        $query .= ' WHERE c.site_id IS NOT NULL GROUP BY c.site_id';

        return $this->conn->db_query($query);
    }

    public function findRepresentants(array $ids)
    {
        $query = 'SELECT id FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE representative_picture_id ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function findWithRepresentant()
    {
        $query = 'SELECT id, name, uppercats, global_rank FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE representative_picture_id IS NOT NULL';

        return $this->conn->db_query($query);
    }

    public function findWithNoRepresentant()
    {
        $query = 'SELECT DISTINCT id, name, uppercats, global_rank FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE;
        $query .= ' ON id = category_id';
        $query .= ' WHERE representative_picture_id IS NULL';

        return $this->conn->db_query($query);
    }

    public function findWithPermalinks(string $order = '')
    {
        $query = 'SELECT id, permalink, uppercats, global_rank FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE permalink IS NOT NULL';

        if ($order) {
            $query .= ' ORDER BY ' . $order;
        }

        return $this->conn->db_query($query);
    }

    public function findWithGroupAccess(int $group_id)
    {
        $query = 'SELECT id, name, uppercats, global_rank FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::GROUP_ACCESS_TABLE . ' ON cat_id = id';
        $query .= ' WHERE status = \'private\' AND group_id = ' . $group_id;

        return $this->conn->db_query($query);
    }

    public function findUnauthorized(array $cat_ids = [])
    {
        $query = 'SELECT id, name, uppercats, global_rank FROM ' . self::CATEGORIES_TABLE;
        $query .= ' WHERE status = \'private\'';
        if (count($cat_ids) > 0) {
            $query .= ' AND id NOT ' . $this->conn->in($cat_ids);
        }

        return $this->conn->db_query($query);
    }

    public function findWithUserAccess(int $user_id, array $exclude_cats = [])
    {
        $query = 'SELECT id, name, uppercats, global_rank FROM ' . self::CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . self::USER_ACCESS_TABLE . ' ON cat_id = id';
        $query .= ' WHERE status = \'private\' AND user_id = ' . $user_id;
        if (count($exclude_cats) > 0) {
            $query .= ' AND cat_id NOT ' . $this->conn->in($exclude_cats);
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
        $is_first = true;

        $query = 'UPDATE ' . self::CATEGORIES_TABLE;
        $query .= ' SET ';
        foreach ($fields as $key => $value) {
            $separator = $is_first ? '' : ', ';

            if (is_bool($value)) {
                $query .= $separator . $key . ' = \'' . $this->conn->boolean_to_db($value) . '\'';
            } elseif (isset($value)) {
                $query .= $separator . $key . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
            } else {
                $query .= $separator . $key . ' = NULL';
            }
            $is_first = false;
        }
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function updateByUppercats(array $datas, string $uppercats)
    {
        $is_first = true;

        $query = 'UPDATE ' . self::CATEGORIES_TABLE;
        $query .= ' SET ';
        foreach ($datas as $key => $value) {
            $separator = $is_first ? '' : ', ';

            if (is_bool($value)) {
                $query .= $separator . $key . ' = \'' . $this->conn->boolean_to_db($value) . '\'';
            } elseif (!empty($value)) {
                $query .= $separator . $key . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
            } else {
                $query .= $separator . $key . ' IS NULL';
            }
            $is_first = false;
        }
        $query .= ' WHERE uppercats LIKE \'' . $this->conn->db_real_escape_string($uppercats) . ',%\'';
        $this->conn->db_query($query);
    }

    public function massUpdatesCategories(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::CATEGORIES_TABLE, $fields, $datas);
    }

    public function addCategories(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::CATEGORIES_TABLE, $fields, $datas);
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

    public function getCategoriesForMenu(User $user, bool $filter_enabled, array $ids_uppercat = [])
    {
        $query = 'SELECT id, name, permalink, nb_images, global_rank,uppercats,';
        $query .= 'date_last, max_date_last, count_images, count_categories';
        $query .= ' FROM ' . self::CATEGORIES_TABLE;
        $query .= ' INNER JOIN ' . self::USER_CACHE_CATEGORIES_TABLE;
        $query .= ' ON id = cat_id and user_id = ' . $user->getId();
        $query .= ' WHERE';
        // Always expand when filter is activated
        if (!$user->wantExpand() && !$filter_enabled) {
            $query .= ' (id_uppercat is NULL';
            if (!empty($ids_uppercat)) {
                $query .= ' OR id_uppercat ' . $this->conn->in($ids_uppercat);
            }
            $query .= ')';
        } else {
            $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(['visible_categories' => 'id'], null, true);
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
        $query .= ' WHERE id ' . $this->conn->in($ids);

        $this->conn->db_query($query);
    }

    public function getMaxLastModified()
    {
        $query = 'SELECT ' . $this->conn->db_date_to_ts('MAX(lastmodified)') . ', COUNT(1)';
        $query .= ' FROM ' . self::CATEGORIES_TABLE;

        return $this->conn->db_query($query);
    }
}
