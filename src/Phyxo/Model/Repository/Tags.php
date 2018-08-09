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

namespace Phyxo\Model\Repository;

use Phyxo\Image\DerivativeImage;
use Phyxo\Functions\Plugin;

class Tags extends BaseRepository
{
    protected $conn;

    /**
     * Returns all tags even associated to no image.
     * The list can be filtered
     *
     * @param  q string substring of tag to search
     * @return array [id, name, url_name]
     */
    public function getAllTags($q = '')
    {
        $query = 'SELECT t.id, name, url_name, lastmodified FROM ' . TAGS_TABLE . ' AS t';
        if (!empty($q)) {
            $query .= sprintf(' WHERE LOWER(name) like \'%%%s%%\'', strtolower($this->conn->db_real_escape_string($q)));
        }

        $result = $this->conn->db_query($query);
        $tags = array();
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $row['name'] = Plugin::trigger_change('render_tag_name', $row['name'], $row);
            $tags[] = $row;
        }

        usort($tags, 'tag_alpha_compare');

        return $tags;
    }

    public function getPendingTags()
    {
        $query = 'SELECT t.id, t.name,it.image_id, url_name, created_by,';
        $query .= ' i.path,u.username, status FROM ' . IMAGE_TAG_TABLE . ' AS it';
        $query .= ' LEFT JOIN ' . TAGS_TABLE . ' AS t ON it.tag_id=t.id';
        $query .= ' LEFT JOIN ' . IMAGES_TABLE . ' AS i ON i.id=it.image_id';
        $query .= ' LEFT JOIN ' . USERS_TABLE . ' AS u ON u.id=created_by';
        $query .= ' WHERE validated=\'' . $this->conn->boolean_to_db(false) . '\'';
        $query .= ' AND created_by IS NOT NULL';

        $result = $this->conn->db_query($query);
        $tags = array();
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $row['thumb_src'] = DerivativeImage::thumb_url(array('id' => $row['image_id'], 'path' => $row['path']));
            $row['picture_url'] = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo-' . $row['image_id'];
            $row['name'] = Plugin::trigger_change('render_tag_name', $row['name'], $row);
            $tags[] = $row;
        }

        usort($tags, 'tag_alpha_compare');

        return $tags;
    }

    /**
     * Returns the number of available tags for the connected user.
     *
     * @return int
     */
    public function getNbAvailableTags($user)
    {
        if (!isset($user['nb_available_tags'])) {
            $user['nb_available_tags'] = count($this->getAvailableTags());
            $this->conn->single_update(
                USER_CACHE_TABLE,
                array('nb_available_tags' => $user['nb_available_tags']),
                array('user_id' => $user['id'])
            );
        }

        return $user['nb_available_tags'];
    }

    /**
     * Returns all available tags for the connected user (not sorted).
     * The returned list can be a subset of all existing tags due to permissions,
     * also tags with no images are not returned.
     *
     * @return array [id, name, counter, url_name]
     */
    public function getAvailableTags()
    {
        global $user;

        // we can find top fatter tags among reachable images
        $query = 'SELECT tag_id, validated, status, created_by,';
        $query .= ' COUNT(DISTINCT(it.image_id)) AS counter FROM ' . IMAGE_CATEGORY_TABLE . ' ic';
        $query .= ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' AS it ON ic.image_id=it.image_id';
        $query .= ' ' . get_sql_condition_FandF(
            array(
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'ic.image_id'
            ),
            ' WHERE '
        );
        $query .= ' AND (' . $this->validatedCondition($user['id']) . ')';
        $query .= ' GROUP BY tag_id,validated,created_by,status';
        $result = $this->conn->db_query($query);

        // merge tags whether they are validated or not
        $tag_counters = array();
        while ($row = $this->conn->db_fetch_assoc($result)) {
            if (!isset($tag_counters[$row['tag_id']])) {
                $tag_counters[$row['tag_id']] = $row;
            } else {
                $tag_counters[$row['tag_id']]['counter'] += $row['counter'];
            }
        }

        if (empty($tag_counters)) {
            return array();
        }

        $query = 'SELECT id, name, url_name FROM ' . TAGS_TABLE;
        $result = $this->conn->db_query($query);

        $tags = array();
        while ($row = $this->conn->db_fetch_assoc($result)) {
            if (!empty($tag_counters[$row['id']])) {
                $row['counter'] = (int)$tag_counters[$row['id']]['counter'];
                $row['name'] = Plugin::trigger_change('render_tag_name', $row['name'], $row);
                $row['status'] = $tag_counters[$row['id']]['status'];
                $row['created_by'] = $tag_counters[$row['id']]['created_by'];
                $row['validated'] = $this->conn->get_boolean($tag_counters[$row['id']]['validated']);
                $tags[] = $row;
            }
        }

        return $tags;
    }

    public function getCommonTags($items, $max_tags, $excluded_tag_ids = array())
    {
        global $user;

        if (empty($items)) {
            return array();
        }

        $query = 'SELECT id,name,validated,created_by,status,';
        $query .= ' url_name, count(1) AS counter FROM ' . TAGS_TABLE . ' AS t';
        $query .= ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' ON tag_id = id';
        $query .= ' WHERE image_id ' . $this->conn->in($items);
        $query .= ' AND (' . $this->validatedCondition($user['id']) . ')';
        if (!empty($excluded_tag_ids)) {
            $query .= ' AND tag_id NOT ' . $this->conn->in($excluded_tag_ids);
        }
        $query .= ' GROUP BY validated,created_by,status,t.id';

        if ($max_tags > 0) {
            $query .= ' ORDER BY counter DESC LIMIT ' . $max_tags;
        }

        $result = $this->conn->db_query($query);
        $tags = array();
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $row['name'] = Plugin::trigger_change('render_tag_name', $row['name'], $row);
            $row['validated'] = $this->conn->get_boolean($row['validated']);
            $tags[] = $row;
        }
        usort($tags, 'tag_alpha_compare');

        return $tags;
    }

    /**
     * Get tags list from SQL query (ids are surrounded by ~~, for getTagsIds()).
     *
     * @param string $query
     * @param boolean $only_user_language - if true, only local name is returned for
     *    multilingual tags (if ExtendedDescription plugin is active)
     * @return array[] ('id', 'name')
     */
    public function getTagsList($query, $only_user_language = true)
    {
        $result = $this->conn->db_query($query);

        $taglist = array();
        $altlist = array();
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $raw_name = $row['name'];
            $name = Plugin::trigger_change('render_tag_name', $raw_name, $row);

            $taglist[] = array(
                'name' => $name,
                'id' => '~~' . $row['id'] . '~~',
            );

            if (!$only_user_language) {
                $alt_names = Plugin::trigger_change('get_tag_alt_names', array(), $raw_name);

                foreach (array_diff(array_unique($alt_names), array($name)) as $alt) {
                    $altlist[] = array(
                        'name' => $alt,
                        'id' => '~~' . $row['id'] . '~~',
                    );
                }
            }
        }
        usort($taglist, 'tag_alpha_compare');
        if (count($altlist)) {
            usort($altlist, 'tag_alpha_compare');
            $taglist = array_merge($taglist, $altlist);
        }

        return $taglist;
    }

    /**
     * Get tags ids from a list of raw tags (existing tags or new tags).
     *
     * In $raw_tags we receive something like array('~~6~~', '~~59~~', 'New
     * tag', 'Another new tag') The ~~34~~ means that it is an existing
     * tag. We added the surrounding ~~ to permit creation of tags like "10"
     * or "1234" (numeric characters only)
     *
     * @param string|string[] $raw_tags - array or comma separated string
     * @param boolean $allow_create
     * @return int[]
     */
    public function getTagsIds($raw_tags, $allow_create = true)
    {
        $tag_ids = array();
        if (!is_array($raw_tags)) {
            $raw_tags = explode(',', $raw_tags);
        }

        foreach ($raw_tags as $raw_tag) {
            if (preg_match('/^~~(\d+)~~$/', $raw_tag, $matches)) {
                $tag_ids[] = $matches[1];
            } elseif ($allow_create) {
                // we have to create a new tag
                $tag_ids[] = $this->tagIdFromTagName($raw_tag);
            }
        }

        return $tag_ids;
    }

    /**
     * Returns a tag id from its name. If nothing found, create a new tag.
     *
     * @param string $tag_name
     * @return int
     */
    public function tagIdFromTagName($tag_name)
    {
        global $page;

        $tag_name = trim($tag_name);
        if (isset($page['tag_id_from_tag_name_cache'][$tag_name])) {
            return $page['tag_id_from_tag_name_cache'][$tag_name]; // @TODO: change from call to avoid global $page
        }

        // search existing by exact name
        $query = 'SELECT id FROM ' . $this->table;
        $query .= ' WHERE name = \'' . $this->conn->db_real_escape_string($tag_name) . '\';';

        if (count($existing_tags = $this->conn->query2array($query, null, 'id')) == 0) {
            $url_name = Plugin::trigger_change('render_tag_url', $tag_name);
            // search existing by url name
            $query = 'SELECT id FROM ' . $this->table;
            $query .= ' WHERE url_name = \'' . $this->conn->db_real_escape_string($url_name) . '\';';
            if (count($existing_tags = $this->conn->query2array($query, null, 'id')) == 0) {
                // search by extended description (plugin sub name)
                $sub_name_where = Plugin::trigger_change('get_tag_name_like_where', array(), $tag_name);
                if (count($sub_name_where)) {
                    $query = 'SELECT id FROM ' . $this->table;
                    $query .= ' WHERE ' . implode(' OR ', $sub_name_where) . ';';
                    $existing_tags = $this->conn->query2array($query, null, 'id');
                }

                if (count($existing_tags) == 0) { // finally create the tag
                    $this->conn->mass_inserts(
                        $this->table,
                        array('name', 'url_name'),
                        array(array('name' => $tag_name, 'url_name' => $url_name))
                    );

                    $page['tag_id_from_tag_name_cache'][$tag_name] = $this->conn->db_insert_id($this->table);

                    \invalidate_user_cache_nb_tags();

                    return $page['tag_id_from_tag_name_cache'][$tag_name];
                }
            }
        }

        $page['tag_id_from_tag_name_cache'][$tag_name] = $existing_tags[0];

        return $page['tag_id_from_tag_name_cache'][$tag_name];
    }

    /**
     * Add new tags to a set of images.
     *
     * @param int[] $tags
     * @param int[] $images
     */
    public function addTags($tags, $images)
    {
        if (count($tags) == 0 or count($images) == 0) {
            return;
        }

        // we can't insert twice the same {image_id,tag_id} so we must first
        // delete lines we'll insert later
        $query = 'DELETE FROM ' . IMAGE_TAG_TABLE;
        $query .= ' WHERE image_id ' . $this->conn->in($images);
        $query .= ' AND tag_id ' . $this->conn->in($tags);
        $this->conn->db_query($query);

        $inserts = array();
        foreach ($images as $image_id) {
            foreach (array_unique($tags) as $tag_id) {
                $inserts[] = array(
                    'image_id' => $image_id,
                    'tag_id' => $tag_id,
                );
            }
        }
        $this->conn->mass_inserts(
            IMAGE_TAG_TABLE,
            array_keys($inserts[0]),
            $inserts
        );
        invalidate_user_cache_nb_tags();
    }

    /**
     * Set tags to an image.
     * Warning: given tags are all tags associated to the image, not additionnal tags.
     *
     * @param int[] $tags
     * @param int $image_id
     */
    public function setTags($tags, $image_id)
    {
        $this->setTagsOf(array($image_id => $tags));
    }

    /**
     * Delete tags and tags associations.
     *
     * @param int[] $tag_ids
     */
    public function deleteTags($tag_ids)
    {
        if (is_numeric($tag_ids)) {
            $tag_ids = array($tag_ids);
        }

        if (!is_array($tag_ids)) {
            return false;
        }

        $query = 'DELETE  FROM ' . IMAGE_TAG_TABLE;
        $query .= ' WHERE tag_id ' . $this->conn->in($tag_ids);
        $this->conn->db_query($query);

        $query = 'DELETE FROM ' . TAGS_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($tag_ids);
        $this->conn->db_query($query);

        invalidate_user_cache_nb_tags();
    }

    /**
     * Set tags of images. Overwrites all existing associations.
     *
     * @param array $tags_of - keys are image ids, values are array of tag ids
     */
    public function setTagsOf($tags_of)
    {
        if (count($tags_of) > 0) {
            $query = 'DELETE FROM ' . IMAGE_TAG_TABLE;
            $query .= ' WHERE image_id ' . $this->conn->in(array_keys($tags_of));
            $this->conn->db_query($query);

            $inserts = array();

            foreach ($tags_of as $image_id => $tag_ids) {
                foreach (array_unique($tag_ids) as $tag_id) {
                    $inserts[] = array(
                        'image_id' => $image_id,
                        'tag_id' => $tag_id
                    );
                }
            }

            if (count($inserts)) {
                $this->conn->mass_inserts(
                    IMAGE_TAG_TABLE,
                    array_keys($inserts[0]),
                    $inserts
                );
            }

            invalidate_user_cache_nb_tags();
        }
    }

    /**
     * Giving a set of tags with a counter for each one, calculate the display
     * level of each tag.
     *
     * The level of each tag depends on the average count of tags. This
     * calculation method avoid having very different levels for tags having
     * nearly the same count when set are small.
     *
     * @param array $tags at least [id, counter]
     * @return array [..., level]
     */
    public function addLevelToTags($tags)
    {
        global $conf;

        if (count($tags) == 0) {
            return $tags;
        }

        $total_count = 0;

        foreach ($tags as $tag) {
            $total_count += $tag['counter'];
        }

        // average count of available tags will determine the level of each tag
        $tag_average_count = $total_count / count($tags);

        // tag levels threshold calculation: a tag with an average rate must have
        // the middle level.
        for ($i = 1; $i < $conf['tags_levels']; $i++) {
            $threshold_of_level[$i] = 2 * $i * $tag_average_count / $conf['tags_levels'];
        }

        // display sorted tags
        foreach ($tags as &$tag) {
            $tag['level'] = 1;

            // based on threshold, determine current tag level
            for ($i = $conf['tags_levels'] - 1; $i >= 1; $i--) {
                if ($tag['counter'] > $threshold_of_level[$i]) {
                    $tag['level'] = $i + 1;
                    break;
                }
            }
        }
        unset($tag);

        return $tags;
    }

    /**
     * Return the list of image ids corresponding to given tags.
     * AND & OR mode supported.
     *
     * @param int[] $tag_ids
     * @param string mode
     * @param string $extra_images_where_sql - optionally apply a sql where filter to retrieved images
     * @param string $order_by - optionally overwrite default photo order
     * @param bool $user_permissions
     * @return array
     */
    public function getImageIdsForTags($tag_ids, $mode = 'AND', $extra_images_where_sql = '', $order_by = '', $use_permissions = true)
    {
        global $conf;

        if (empty($tag_ids)) {
            return array();
        }

        $query = 'SELECT id FROM ' . IMAGES_TABLE . ' i ';

        if ($use_permissions) {
            $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id=ic.image_id';
        }

        $query .= ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' AS it ON id=it.image_id';
        $query .= ' WHERE tag_id ' . $this->conn->in($tag_ids);

        if ($use_permissions) {
            $query .= get_sql_condition_FandF(
                array(
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id'
                ),
                "\n  AND"
            );
        }

        $query .= (empty($extra_images_where_sql) ? '' : " \nAND (" . $extra_images_where_sql . ')') . ' GROUP BY id';

        if ($mode == 'AND' and count($tag_ids) > 1) {
            $query .= ' HAVING COUNT(DISTINCT tag_id)=' . count($tag_ids);
        }
        $query .= ' ' . (empty($order_by) ? $conf['order_by'] : $order_by);

        return $this->conn->query2array($query, null, 'id');
    }

    /**
     * Return a list of tags corresponding to any of ids, url_names or names.
     *
     * @param int[] $ids
     * @param string[] $url_names
     * @param string[] $names
     * @return array [id, name, url_name]
     */
    public function findTags($ids = array(), $url_names = array(), $names = array())
    {
        $where_clauses = array();
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
            return array();
        }

        $query = 'SELECT id,name,url_name,lastmodified FROM ' . TAGS_TABLE;
        $query .= ' WHERE ' . implode(' OR ', $where_clauses);

        return $this->conn->query2array($query);
    }

    /**
     * Deletes all tags linked to no photo
     */
    public function deleteOrphanTags()
    {
        $orphan_tags = $this->getOrphanTags();

        if (count($orphan_tags) > 0) {
            $orphan_tag_ids = array();
            foreach ($orphan_tags as $tag) {
                $orphan_tag_ids[] = $tag['id'];
            }

            $this->deleteTags($orphan_tag_ids);
        }
    }

    /**
     * Get all tags (id + name) linked to no photo
     */
    public function getOrphanTags()
    {
        $query = 'SELECT id,name FROM ' . TAGS_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' ON id = tag_id';
        $query .= ' WHERE tag_id IS NULL;';

        return $this->conn->query2array($query);
    }

    /**
     * Create a new tag.
     *
     * @param string $tag_name
     * @return array ('id', info') or ('error')
     */
    public function createTag($tag_name)
    {
        // does the tag already exists?
        $query = 'SELECT id FROM ' . TAGS_TABLE;
        $query .= ' WHERE name = \'' . $this->conn->db_real_escape_string($tag_name) . '\';';
        $existing_tags = $this->conn->query2array($query, null, 'id');

        if (count($existing_tags) == 0) {
            $this->conn->single_insert(
                TAGS_TABLE,
                array(
                    'name' => $tag_name,
                    'url_name' => Plugin::trigger_change('render_tag_url', $tag_name),
                )
            );

            $inserted_id = $this->conn->db_insert_id(TAGS_TABLE);

            return array(
                'info' => \Phyxo\Functions\Language::l10n('Tag "%s" was added', stripslashes($tag_name)), // @TODO: remove stripslashes
                'id' => $inserted_id,
            );
        } else {
            return array('error' => \Phyxo\Functions\Language::l10n('Tag "%s" already exists', stripslashes($tag_name))); // @TODO: remove stripslashes
        }
    }

    public function associateTags($tag_ids, $image_id)
    {
        if (!is_array($tag_ids)) {
            return;
        }

        $inserts = array();
        foreach ($tag_ids as $tag_id) {
            $inserts[] = array(
                'image_id' => $image_id,
                'tag_id' => $tag_id
            );
        }
        $this->conn->mass_inserts(
            IMAGE_TAG_TABLE,
            array_keys($inserts[0]),
            $inserts
        );
        invalidate_user_cache_nb_tags();
    }

    /*
     * @param $elements in an array of tags indexed by image_id
     */
    public function rejectTags($elements)
    {
        if (empty($elements)) {
            return;
        }
        $deletes = array();
        foreach ($elements as $image_id => $tag_ids) {
            foreach ($tag_ids as $tag_id) {
                $deletes[] = array(
                    'image_id' => $image_id,
                    'tag_id' => $tag_id
                );
            }
        }
        $this->conn->mass_deletes(
            IMAGE_TAG_TABLE,
            array('tag_id', 'image_id'),
            $deletes
        );
    }

    /*
     * @param $elements in an array of tags indexed by image_id
     */
    public function validateTags(array $elements)
    {
        if (empty($elements)) {
            return;
        }
        $updates = array();
        foreach ($elements as $image_id => $tag_ids) {
            foreach ($tag_ids as $tag_id) {
                $updates[] = array(
                    'image_id' => $image_id,
                    'tag_id' => $tag_id,
                    'validated' => $this->conn->boolean_to_db(true)
                );
            }
        }
        $this->conn->mass_updates(
            IMAGE_TAG_TABLE,
            array(
                'primary' => array('tag_id', 'image_id'),
                'update' => array('validated')
            ),
            $updates
        );
        $query = 'DELETE FROM ' . IMAGE_TAG_TABLE;
        $query .= ' WHERE status = 0 AND validated = \'' . $this->conn->boolean_to_db(true) . '\'';
        $this->conn->db_query($query);
        invalidate_user_cache_nb_tags();
    }

    public function dissociateTags($tag_ids, $image_id)
    {
        if (!is_array($tag_ids)) {
            return;
        }

        $query = 'DELETE FROM ' . IMAGE_TAG_TABLE;
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= ' AND tag_id ' . $this->conn->in($tag_ids);
        $this->conn->db_query($query);
    }

    /**
     * Mark tags as to be validated for addition or deletion.
     *
     * @param array     $tags_ids
     * @param int       $image_id
     * @param array     $infos, keys are:
     *                      status[0|1] - 0 for deletion, 1 for addition
     *                      user_id -id user who add or delete tags
     */
    public function toBeValidatedTags($tags_ids, $image_id, array $infos)
    {
        $rows = array();
        foreach ($tags_ids as $id) {
            $rows[] = array(
                'tag_id' => $id,
                'image_id' => $image_id,
                'status' => $infos['status'],
                'created_by' => $infos['user_id'],
                'validated' => false
            );
        }

        if (count($rows) > 0) {
            if ($infos['status'] == 1) {
                $this->conn->mass_inserts(
                    IMAGE_TAG_TABLE,
                    array_keys($rows[0]),
                    $rows
                );
            } else {
                $this->conn->mass_updates(
                    IMAGE_TAG_TABLE,
                    array(
                        'primary' => array('tag_id', 'image_id'),
                        'update' => array('status', 'validated', 'created_by')
                    ),
                    $rows
                );
            }
        }

        invalidate_user_cache_nb_tags();
    }

    private function validatedCondition($user_id)
    {
        global $conf;

        $sql = ' ((validated = \'' . $this->conn->boolean_to_db(true) . '\' AND status = 1)';
        $sql .= ' OR (validated = \'' . $this->conn->boolean_to_db(false) . '\' AND status = 0))';

        if (!empty($conf['show_pending_added_tags'])) {
            $sql .= ' OR (created_by = ' . $user_id . ')';
        }

        return $sql;
    }
}
