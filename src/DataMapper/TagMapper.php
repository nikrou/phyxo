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

namespace App\DataMapper;

use Phyxo\Image\DerivativeImage;
use Phyxo\Functions\Plugin;
use Phyxo\DBLayer\iDBLayer;
use Phyxo\Conf;
use App\Repository\TagRepository;
use App\Repository\ImageTagRepository;
use App\Repository\UserCacheRepository;
use Phyxo\Functions\Metadata;
use App\Repository\ImageRepository;

class TagMapper
{
    private $conn, $conf;

    public function __construct(iDBLayer $conn, Conf $conf)
    {
        $this->conn = $conn;
        $this->conf = $conf;
    }

    /**
     * Returns all tags even associated to no image.
     * The list can be filtered
     *
     * @param  q string substring of tag to search
     * @return array [id, name, url_name]
     */
    public function getAllTags(string $q = '')
    {
        $result = (new TagRepository($this->conn))->findAll($q);
        $tags = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $row['name'] = Plugin::trigger_change('render_tag_name', $row['name'], $row);
            $tags[] = $row;
        }

        usort($tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

        return $tags;
    }

    public function getPendingTags()
    {
        $result = (new TagRepository($this->conn))->getPendingTags();
        $tags = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $row['thumb_src'] = DerivativeImage::thumb_url(['id' => $row['image_id'], 'path' => $row['path']]);
            $row['picture_url'] = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo-' . $row['image_id'];
            $row['name'] = Plugin::trigger_change('render_tag_name', $row['name'], $row);
            $tags[] = $row;
        }

        usort($tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

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
            $user['nb_available_tags'] = count($this->getAvailableTags($user));
            (new UserCacheRepository($this->conn))->updateUserCache(
                ['nb_available_tags' => $user['nb_available_tags']],
                ['user_id' => $user['id']]
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
    public function getAvailableTags($user)
    {
        $result = (new TagRepository($this->conn))->getAvailableTags($user, $this->conf['show_pending_added_tags'] ?? false);

        // merge tags whether they are validated or not
        $tag_counters = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            if (!isset($tag_counters[$row['tag_id']])) {
                $tag_counters[$row['tag_id']] = $row;
            } else {
                $tag_counters[$row['tag_id']]['counter'] += $row['counter'];
            }
        }

        if (empty($tag_counters)) {
            return [];
        }

        $result = (new TagRepository($this->conn))->findAll();

        $tags = [];
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

    public function getCommonTags($user, $items, $max_tags, $excluded_tag_ids = [])
    {
        if (empty($items)) {
            return [];
        }

        $result = (new TagRepository($this->conn))->getCommonTags($user, $items, $max_tags, $this->conf['show_pending_added_tags'] ?? false, $excluded_tag_ids);
        $tags = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $row['name'] = Plugin::trigger_change('render_tag_name', $row['name'], $row);
            $row['validated'] = $this->conn->get_boolean($row['validated']);
            $tags[] = $row;
        }
        usort($tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

        return $tags;
    }

    /**
     * Get tags list and surround ids by ~~, for getTagsIds()) to differenciate new tags from existing tags
     *
     * @param boolean $only_user_language - if true, only local name is returned for
     *    multilingual tags (if ExtendedDescription plugin is active)
     * @return array[] ('id', 'name')
     */
    public function prepareTagsListForUI(array $tags, $only_user_language = true) : array
    {
        $taglist = [];
        $altlist = [];
        foreach ($tags as $tag) {
            $raw_name = $tag['name'];
            $name = Plugin::trigger_change('render_tag_name', $raw_name, $tag);

            $taglist[] = [
                'name' => $name,
                'id' => '~~' . $tag['id'] . '~~',
            ];

            if (!$only_user_language) {
                $alt_names = Plugin::trigger_change('get_tag_alt_names', [], $raw_name);

                foreach (array_diff(array_unique($alt_names), [$name]) as $alt) {
                    $altlist[] = [
                        'name' => $alt,
                        'id' => '~~' . $tag['id'] . '~~',
                    ];
                }
            }
        }
        usort($taglist, '\Phyxo\Functions\Utils::tag_alpha_compare');
        if (count($altlist)) {
            usort($altlist, '\Phyxo\Functions\Utils::tag_alpha_compare');
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
    public function getTagsIds($raw_tags, bool $allow_create = true)
    {
        $tag_ids = [];
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
    public function tagIdFromTagName(string $tag_name) : int
    {
        $tag_name = trim($tag_name);

        // search existing by exact name
        $result = (new TagRepository($this->conn))->findBy('name', $tag_name);
        $existing_tags = $this->conn->result2array($result, null, 'id');

        if (count($existing_tags) === 0) {
            $url_name = Plugin::trigger_change('render_tag_url', $tag_name);
            // search existing by url name
            $result = (new TagRepository($this->conn))->findBy('url_name', $url_name);
            $existing_tags = $this->conn->result2array($result, null, 'id');

            if (count($existing_tags) === 0) { // finally create the tag
                $insert_tag_id = (new TagRepository($this->conn))->insertTag($tag_name, $url_name);

                \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();

                return $insert_tag_id;
            }
        }

        return $existing_tags[0];
    }

    /**
     * Add new tags to a set of images.
     */
    public function addTags(array $tags, array $images)
    {
        if (count($tags) == 0 or count($images) == 0) {
            return;
        }

        // we can't insert twice the same {image_id,tag_id} so we must first
        // delete lines we'll insert later
        (new TagRepository($this->conn))->deleteByImagesAndTags($images, $tags);

        $inserts = [];
        foreach ($images as $image_id) {
            foreach (array_unique($tags) as $tag_id) {
                $inserts[] = [
                    'image_id' => $image_id,
                    'tag_id' => $tag_id,
                ];
            }
        }
        (new ImageTagRepository($this->conn))->insertImageTags(
            array_keys($inserts[0]),
            $inserts
        );
        \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();
    }

    /**
     * Set tags to an image.
     * Warning: given tags are all tags associated to the image, not additionnal tags.
     *
     * @param int[] $tags
     * @param int $image_id
     */
    public function setTags(array $tags, int $image_id)
    {
        $this->setTagsOf([$image_id => $tags]);
    }

    /**
     * Delete tags and tags associations.
     *
     * @param int[] $tag_ids
     */
    public function deleteTags(array $tag_ids)
    {
        (new ImageTagRepository($this->conn))->deleteBy('tag_id', $tag_ids);
        (new TagRepository($this->conn))->deleteBy('id', $tag_ids);

        \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();
    }

    /**
     * Set tags of images. Overwrites all existing associations.
     *
     * @param array $tags_of - keys are image ids, values are array of tag ids
     */
    public function setTagsOf(array $tags_of)
    {
        if (count($tags_of) > 0) {
            (new ImageTagRepository($this->conn))->deleteBy('image_id', array_keys($tags_of));

            $inserts = [];

            foreach ($tags_of as $image_id => $tag_ids) {
                foreach (array_unique($tag_ids) as $tag_id) {
                    $inserts[] = [
                        'image_id' => $image_id,
                        'tag_id' => $tag_id
                    ];
                }
            }

            if (count($inserts)) {
                (new ImageTagRepository($this->conn))->insertImageTags(
                    array_keys($inserts[0]),
                    $inserts
                );
            }

            \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();
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
    public function addLevelToTags(array $tags) : array
    {
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
        for ($i = 1; $i < $this->conf['tags_levels']; $i++) {
            $threshold_of_level[$i] = 2 * $i * $tag_average_count / $this->conf['tags_levels'];
        }

        // display sorted tags
        foreach ($tags as &$tag) {
            $tag['level'] = 1;

            // based on threshold, determine current tag level
            for ($i = $this->conf['tags_levels'] - 1; $i >= 1; $i--) {
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
     * Deletes all tags linked to no photo
     */
    public function deleteOrphanTags()
    {
        $result = (new TagRepository($this->conn))->getOrphanTags();
        $orphan_tags = $this->conn->result2array($result);

        if (count($orphan_tags) > 0) {
            $orphan_tag_ids = [];
            foreach ($orphan_tags as $tag) {
                $orphan_tag_ids[] = $tag['id'];
            }

            $this->deleteTags($orphan_tag_ids);
        }
    }

    /**
     * Create a new tag.
     *
     * @param string $tag_name
     * @return array ('id', info') or ('error')
     */
    public function createTag(string $tag_name) : array
    {
        // does the tag already exists?
        $result = (new TagRepository($this->conn))->findBy('name', $tag_name);
        $existing_tags = $this->conn->result2array($result, null, 'id');

        if (count($existing_tags) === 0) {
            $inserted_id = (new TagRepository($this->conn))->insertTag($tag_name, Plugin::trigger_change('render_tag_url', $tag_name));

            return [
                'info' => \Phyxo\Functions\Language::l10n('Tag "%s" was added', stripslashes($tag_name)), // @TODO: remove stripslashes
                'id' => $inserted_id,
            ];
        } else {
            return ['error' => \Phyxo\Functions\Language::l10n('Tag "%s" already exists', stripslashes($tag_name))]; // @TODO: remove stripslashes
        }
    }

    public function associateTags(array $tag_ids, int $image_id)
    {
        if (!is_array($tag_ids)) {
            return;
        }

        $inserts = [];
        foreach ($tag_ids as $tag_id) {
            $inserts[] = [
                'image_id' => $image_id,
                'tag_id' => $tag_id
            ];
        }
        (new ImageTagRepository($this->conn))->insertImageTags(
            array_keys($inserts[0]),
            $inserts
        );
        \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();
    }

    // @param $elements in an array of tags indexed by image_id
    public function rejectTags(array $elements)
    {
        if (empty($elements)) {
            return;
        }
        $deletes = [];
        foreach ($elements as $image_id => $tag_ids) {
            foreach ($tag_ids as $tag_id) {
                $deletes[] = [
                    'image_id' => $image_id,
                    'tag_id' => $tag_id
                ];
            }
        }
        (new ImageTagRepository($this->conn))->deleteImageTags($deletes);
    }

    // @param $elements in an array of tags indexed by image_id
    public function validateTags(array $elements)
    {
        if (empty($elements)) {
            return;
        }
        $updates = [];
        foreach ($elements as $image_id => $tag_ids) {
            foreach ($tag_ids as $tag_id) {
                $updates[] = [
                    'image_id' => $image_id,
                    'tag_id' => $tag_id,
                    'validated' => $this->conn->boolean_to_db(true)
                ];
            }
        }
        (new ImageTagRepository($this->conn))->updateImageTags(
            [
                'primary' => ['tag_id', 'image_id'],
                'update' => ['validated']
            ],
            $updates
        );
        (new TagRepository($this->conn))->deleteValidated();
        \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();
    }

    public function dissociateTags($tag_ids, $image_id)
    {
        if (!is_array($tag_ids)) {
            return;
        }

        (new TagRepository($this->conn))->deleteByImageAndTags($image_id, $tag_ids);
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
    public function toBeValidatedTags(array $tags_ids, int $image_id, array $infos)
    {
        $rows = [];
        foreach ($tags_ids as $id) {
            $rows[] = [
                'tag_id' => $id,
                'image_id' => $image_id,
                'status' => $infos['status'],
                'created_by' => $infos['user_id'],
                'validated' => false
            ];
        }

        if (count($rows) > 0) {
            if ($infos['status'] === 1) {
                (new ImageTagRepository($this->conn))->insertImageTags(
                    array_keys($rows[0]),
                    $rows
                );
            } else {
                (new ImageTagRepository($this->conn))->updateImageTags(
                    [
                        'primary' => ['tag_id', 'image_id'],
                        'update' => ['status', 'validated', 'created_by']
                    ],
                    $rows
                );
            }
        }

        \Phyxo\Functions\Utils::invalidate_user_cache_nb_tags();
    }

    /**
     * Sync all metadata of a list of images.
     * Metadata are fetched from original files and saved in database.
     */
    public function sync_metadata(array $ids)
    {
        $now = date('Y-m-d');

        $datas = [];
        $tags_of = [];
        $result = (new ImageRepository($this->conn))->findByIds($ids);
        while ($data = $this->conn->db_fetch_assoc($result)) {
            $data = Metadata::get_sync_metadata($data);
            if ($data === false) {
                continue;
            }

            $id = $data['id'];
            foreach (['keywords', 'tags'] as $key) {
                if (isset($data[$key])) {
                    if (!isset($tags_of[$id])) {
                        $tags_of[$id] = [];
                    }

                    foreach (explode(',', $data[$key]) as $tag_name) {
                        $tags_of[$id][] = $this->tagIdFromTagName($tag_name);
                    }
                }
            }

            $data['date_metadata_update'] = $now;

            $datas[] = $data;
        }

        if (count($datas) > 0) {
            $update_fields = Metadata::get_sync_metadata_attributes();
            $update_fields[] = 'date_metadata_update';

            $update_fields = array_diff(
                $update_fields,
                ['tags', 'keywords']
            );

            (new ImageRepository($this->conn))->massUpdates(
                [
                    'primary' => ['id'],
                    'update' => $update_fields
                ],
                $datas,
                MASS_UPDATES_SKIP_EMPTY
            );
        }

        $this->setTagsOf($tags_of);
    }
}
