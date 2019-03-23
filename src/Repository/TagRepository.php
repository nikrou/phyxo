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

class TagRepository extends BaseRepository
{
    public function findAll(string $q = '')
    {
        $query = 'SELECT id, name, url_name FROM ' . self::TAGS_TABLE;
        if (!empty($q)) {
            $query .= sprintf(' WHERE LOWER(name) like \'%%%s%%\'', strtolower($this->conn->db_real_escape_string($q)));
        }

        return $this->conn->db_query($query);
    }

    public function findByClause(string $clause = '')
    {
        $query = 'SELECT id, name, url_name FROM ' . self::TAGS_TABLE;
        if (!empty($clause)) {
            $query .= ' WHERE ' . $clause;
        }

        return $this->conn->db_query($query);
    }

    public function insertTag(string $tag_name, string $url_name)
    {
        return $this->conn->single_insert(self::TAGS_TABLE, ['name' => $tag_name, 'url_name' => $url_name]);
    }

    public function count() : int
    {
        $query = 'SELECT count(1) FROM ' . self::TAGS_TABLE;
        $result = $this->conn->db_query($query);
        list($nb_tags) = $this->conn->db_fetch_row($result);

        return $nb_tags;
    }

    public function findBy(string $field, string $value)
    {
        $query = 'SELECT id FROM ' . self::TAGS_TABLE;
        $query .= sprintf(' WHERE %s = \'%s\'', $field, $this->conn->db_real_escape_string($value));

        return $this->conn->db_query($query);
    }

    /**
     * Return a list of tags corresponding to any of ids, url_names or names.
     *
     * @param int[] $ids
     * @param string[] $url_names
     * @param string[] $names
     * @return array [id, name, url_name]
     */
    public function findTags($ids = [], $url_names = [], $names = [])
    {
        $where_clauses = [];

        if (!empty($ids)) {
            $where_clauses[] = 'id ' . $this->conn->in($ids);
        }

        if (!empty($url_names)) {
            $where_clauses[] = 'url_name ' . $this->conn->in($url_names);
        }

        if (!empty($names)) {
            $where_clauses[] = 'name ' . $this->conn->in($names);
        }

        if (empty($where_clauses)) {
            return [];
        }

        $query = 'SELECT id,name,url_name,lastmodified FROM ' . self::TAGS_TABLE;
        $query .= ' WHERE ' . implode(' OR ', $where_clauses);

        return $this->conn->db_query($query);
    }

    /**
     * Return the list of image ids corresponding to given tags.
     * AND & OR mode supported.
     *
     * @param int[] $tag_ids
     * @param string $mode
     * @param string $extra_images_where_sql - optionally apply a sql where filter to retrieved images
     * @param string $order_by - optionally overwrite default photo order
     * @param bool $use_permissions
     * @return array
     */
    public function getImageIdsForTags(array $tag_ids, string $mode = 'AND', ? string $extra_images_where_sql = null, string $order_by = '', bool $use_permissions = true)
    {
        if (empty($tag_ids)) {
            return [];
        }

        $query = 'SELECT id FROM ' . self::IMAGES_TABLE . ' i ';

        if ($use_permissions) {
            $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON id=ic.image_id';
        }

        $query .= ' LEFT JOIN ' . self::IMAGE_TAG_TABLE . ' AS it ON id=it.image_id';
        $query .= ' WHERE tag_id ' . $this->conn->in($tag_ids);

        // need $user
        if ($use_permissions) {
            $query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id'
                ],
                ' AND '
            );
        }

        if (!is_null($extra_images_where_sql)) {
            $query .= ' AND (' . $extra_images_where_sql . ')' . ' GROUP BY id';
        }

        if ($mode == 'AND' and count($tag_ids) > 1) {
            $query .= ' HAVING COUNT(DISTINCT tag_id)=' . count($tag_ids);
        }

        if (!empty($order_by)) {
            $query .= ' ' . $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function getTagsByImage(int $image_id, ? bool $validated = null)
    {
        $query = 'SELECT id,name,url_name FROM ' . self::TAGS_TABLE . ' AS t';
        $query .= ' LEFT JOIN ' . self::IMAGE_TAG_TABLE . ' AS it ON t.id = it.tag_id';
        $query .= ' WHERE image_id = ' . $this->conn->db_real_escape_string($image_id);
        if ($validated !== null) {
            $query .= ' AND validated = \'' . $this->conn->boolean_to_db(true) . '\'';
        }

        return $this->conn->db_query($query);
    }

    public function getPendingTags()
    {
        $query = 'SELECT t.id, t.name,it.image_id, url_name, created_by,';
        $query .= ' i.path,u.username, status FROM ' . self::IMAGE_TAG_TABLE . ' AS it';
        $query .= ' LEFT JOIN ' . self::TAGS_TABLE . ' AS t ON it.tag_id = t.id';
        $query .= ' LEFT JOIN ' . self::IMAGES_TABLE . ' AS i ON i.id = it.image_id';
        $query .= ' LEFT JOIN ' . self::USERS_TABLE . ' AS u ON u.id = created_by';
        $query .= ' WHERE validated=\'' . $this->conn->boolean_to_db(false) . '\'';
        $query .= ' AND created_by IS NOT NULL';

        return $this->conn->db_query($query);
    }

    public function getAvailableTags($user, bool $show_pending_added_tags = false)
    {
        // we can find top fatter tags among reachable images
        $query = 'SELECT tag_id, validated, status, created_by,';
        $query .= ' COUNT(DISTINCT(it.image_id)) AS counter FROM ' . self::IMAGE_CATEGORY_TABLE . ' ic';
        $query .= ' LEFT JOIN ' . self::IMAGE_TAG_TABLE . ' AS it ON ic.image_id=it.image_id';
        $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'ic.image_id'
            ],
            ' WHERE '
        );
        $query .= ' AND (' . $this->validatedCondition($user['id'], $show_pending_added_tags) . ')';
        $query .= ' GROUP BY tag_id,validated,created_by,status';

        return $this->conn->db_query($query);
    }

    public function getCommonTags($user, array $items, int $max_tags, bool $show_pending_added_tags, array $excluded_tag_ids = [])
    {
        $query = 'SELECT id,name,validated,created_by,status,';
        $query .= ' url_name, count(1) AS counter FROM ' . self::TAGS_TABLE . ' AS t';
        $query .= ' LEFT JOIN ' . self::IMAGE_TAG_TABLE . ' ON tag_id = id';
        $query .= ' WHERE image_id ' . $this->conn->in($items);
        $query .= ' AND (' . $this->validatedCondition($user['id'], $show_pending_added_tags) . ')';
        if (!empty($excluded_tag_ids)) {
            $query .= ' AND tag_id NOT ' . $this->conn->in($excluded_tag_ids);
        }
        $query .= ' GROUP BY validated,created_by,status,t.id';

        if ($max_tags > 0) {
            $query .= ' ORDER BY counter DESC LIMIT ' . $this->conn->db_real_escape_string($max_tags);
        }

        return $this->conn->db_query($query);
    }

    /**
     * Get all tags (id + name) linked to no photo
     */
    public function getOrphanTags()
    {
        $query = 'SELECT id,name FROM ' . self::TAGS_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_TAG_TABLE . ' ON id = tag_id';
        $query .= ' WHERE tag_id IS NULL;';

        return $this->conn->db_query($query);
    }

    public function deleteValidated()
    {
        $query = 'DELETE FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE status = 0 AND validated = \'' . $this->conn->boolean_to_db(true) . '\'';
        $this->conn->db_query($query);
    }

    public function deleteByImageAndTags(int $image_id, array $tags)
    {
        $query = 'DELETE FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE image_id ' . $this->conn->db_real_escape_string($image_id);
        $query .= ' AND tag_id ' . $this->conn->in($tags);
        $this->conn->db_query($query);
    }

    public function deleteByImagesAndTags(array $images, array $tags)
    {
        $query = 'DELETE FROM ' . self::IMAGE_TAG_TABLE;
        $query .= ' WHERE image_id ' . $this->conn->in($images);
        $query .= ' AND tag_id ' . $this->conn->in($tags);
        $this->conn->db_query($query);
    }

    public function deleteBy(string $field, array $values)
    {
        $query = 'DELETE  FROM ' . self::TAGS_TABLE;
        $query .= ' WHERE ' . $field . ' ' . $this->conn->in($values);
        $this->conn->db_query($query);
    }

    public function updateTags(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::TAGS_TABLE, $fields, $datas);
    }

    private function validatedCondition(int $user_id, bool $show_pending_added_tags = false)
    {
        $sql = '((validated = \'' . $this->conn->boolean_to_db(true) . '\' AND status = 1)';
        $sql .= ' OR (validated = \'' . $this->conn->boolean_to_db(false) . '\' AND status = 0))';

        if ($show_pending_added_tags) {
            $sql .= ' OR (created_by = ' . $this->conn->db_real_escape_string($user_id) . ')';
        }

        return $sql;
    }

    public function getMaxLastModified()
    {
        $query = 'SELECT ' . $this->conn->db_date_to_ts('MAX(lastmodified)') . ', COUNT(1)';
        $query .= ' FROM ' . self::TAGS_TABLE;

        return $this->conn->db_query($query);
    }
}
