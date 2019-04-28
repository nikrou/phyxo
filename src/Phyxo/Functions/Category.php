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

namespace Phyxo\Functions;

use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\OldPermalinkRepository;
use App\Repository\SiteRepository;
use App\DataMapper\UserMapper;

class Category
{
    /**
     * Retrieves informations about a category.
     *
     * @param int $id
     * @return array
     */
    public static function get_cat_info($id)
    {
        global $conn;

        trigger_error('get_cat_info is deprecated. Use CategoryMapper::getCatInfo instead', E_USER_DEPRECATED);

        $cat = (new CategoryRepository($conn))->findById($id);
        if (empty($cat)) {
            return null;
        }

        foreach ($cat as $k => $v) {
            // If the field is true or false, the variable is transformed into a boolean value.
            if (!is_null($v) && $conn->is_boolean($v)) {
                $cat[$k] = $conn->get_boolean($v);
            }
        }

        $upper_ids = explode(',', $cat['uppercats']);
        if (count($upper_ids) == 1) { // no need to make a query for level 1
            $cat['upper_names'] = [
                [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'permalink' => $cat['permalink'],
                ]
            ];
        } else {
            $names = $conn->result2array((new CategoryRepository($conn))->findByIds($upper_ids), 'id');
            // category names must be in the same order than uppercats list
            $cat['upper_names'] = [];
            foreach ($upper_ids as $cat_id) {
                $cat['upper_names'][] = $names[$cat_id];
            }
        }

        return $cat;
    }

    /**
     * Returns an array of image orders available for users/visitors.
     * Each entry is an array containing
     *  0: name
     *  1: SQL ORDER command
     *  2: visiblity (true or false)
     *
     * @return array[]
     */
    public static function get_category_preferred_image_orders(UserMapper $userMapper)
    {
        global $conf;

        return \Phyxo\Functions\Plugin::trigger_change('get_category_preferred_image_orders', [
            [\Phyxo\Functions\Language::l10n('Default'), '', true],
            [\Phyxo\Functions\Language::l10n('Photo title, A &rarr; Z'), 'name ASC', true],
            [\Phyxo\Functions\Language::l10n('Photo title, Z &rarr; A'), 'name DESC', true],
            [\Phyxo\Functions\Language::l10n('Date created, new &rarr; old'), 'date_creation DESC', true],
            [\Phyxo\Functions\Language::l10n('Date created, old &rarr; new'), 'date_creation ASC', true],
            [\Phyxo\Functions\Language::l10n('Date posted, new &rarr; old'), 'date_available DESC', true],
            [\Phyxo\Functions\Language::l10n('Date posted, old &rarr; new'), 'date_available ASC', true],
            [\Phyxo\Functions\Language::l10n('Rating score, high &rarr; low'), 'rating_score DESC', $conf['rate']],
            [\Phyxo\Functions\Language::l10n('Rating score, low &rarr; high'), 'rating_score ASC', $conf['rate']],
            [\Phyxo\Functions\Language::l10n('Visits, high &rarr; low'), 'hit DESC', true],
            [\Phyxo\Functions\Language::l10n('Visits, low &rarr; high'), 'hit ASC', true],
            [\Phyxo\Functions\Language::l10n('Permissions'), 'level DESC', $userMapper->isAdmin()],
        ]);
    }

    /**
     * Verifies that the representative picture really exists in the db and
     * picks up a random representative if possible and based on config.
     *
     * @param 'all'|int|int[] $ids
     */
    public static function update_category($ids = 'all')
    {
        global $conf, $conn;

        trigger_error('update_category is deprecated. Use CategoryMapper::updateCategory instead', E_USER_DEPRECATED);

        if ($ids == 'all') {
            $where_cats = '1=1';
        } elseif (!is_array($ids)) {
            $where_cats = '%s=' . $ids;
        } else {
            if (count($ids) == 0) {
                return false;
            }
            $where_cats = '%s ' . $conn->in($ids);
        }

        // find all categories where the setted representative is not possible : the picture does not exist
        $wrong_representant = $conn->result2array((new CategoryRepository($conn))->findWrongRepresentant($where_cats), null, 'id');

        if (count($wrong_representant) > 0) {
            (new CategoryRepository($conn))->updateCategories(['representative_picture_id' => null], $wrong_representant);
        }

        if (!$conf['allow_random_representative']) {
            // If the random representant is not allowed, we need to find
            // categories with elements and with no representant. Those categories
            // must be added to the list of categories to set to a random
            // representant.
            $to_rand = $conn->result2array((new CategoryRepository($conn))->findRandomRepresentant($where_cats), null, 'id');
            if (count($to_rand) > 0) {
                \Phyxo\Functions\Utils::set_random_representant($to_rand);
            }
        }
    }

    /**
     * Finds a matching category id from a potential list of permalinks
     *
     * @param string[] $permalinks
     * @param int &$idx filled with the index in $permalinks that matches
     * @return int|null
     */
    public static function get_cat_id_from_permalinks($permalinks, &$idx)
    {
        global $conn;

        trigger_error('get_cat_id_from_permalinks is deprecated and will be deleted', E_USER_DEPRECATED);

        $perma_hash = $conn->result2array((new OldPermalinkRepository($conn))->findCategoryFromPermalinks($permalinks), 'permalink');

        if (empty($perma_hash)) {
            return null;
        }

        for ($i = count($permalinks) - 1; $i >= 0; $i--) {
            if (isset($perma_hash[$permalinks[$i]])) {
                $idx = $i;
                $cat_id = $perma_hash[$permalinks[$i]]['id'];
                if ($perma_hash[$permalinks[$i]]['is_old']) {
                    (new OldPermalinkRepository($conn))->updateOldPermalink($permalinks[$i], $cat_id);
                }
                return $cat_id;
            }
        }

        return null;
    }

    /**
     * Returns display text for images counter of category
     *
     * @param int $cat_nb_images nb images directly in category
     * @param int $cat_count_images nb images in category (including subcats)
     * @param int $cat_count_categories nb subcats
     * @param bool $short_message if true append " in this album"
     * @param string $separator
     * @return string
     */
    public static function get_display_images_count($cat_nb_images, $cat_count_images, $cat_count_categories, $short_message = true, $separator = '\n')
    {
        $display_text = '';

        if ($cat_count_images > 0) {
            if ($cat_nb_images > 0 and $cat_nb_images < $cat_count_images) {
                $display_text .= self::get_display_images_count($cat_nb_images, $cat_nb_images, 0, $short_message, $separator) . $separator;
                $cat_count_images -= $cat_nb_images;
                $cat_nb_images = 0;
            }

            //at least one image direct or indirect
            $display_text .= \Phyxo\Functions\Language::l10n_dec('%d photo', '%d photos', $cat_count_images);

            if ($cat_count_categories == 0 or $cat_nb_images == $cat_count_images) {
                //no descendant categories or descendants do not contain images
                if (!$short_message) {
                    $display_text .= ' ' . \Phyxo\Functions\Language::l10n('in this album');
                }
            } else {
                $display_text .= ' ' . \Phyxo\Functions\Language::l10n_dec('in %d sub-album', 'in %d sub-albums', $cat_count_categories);
            }
        }

        return $display_text;
    }

    /**
     * Returns the category comment for rendering in html textual mode (subcatify)
     * This method is called by a trigger_notify()
     *
     * @param string $desc
     * @return string
     */
    public static function render_category_literal_description($desc)
    {
        return strip_tags($desc, '<span><p><a><br><b><i><small><big><strong><em>');
    }

    /**
     * Associate a list of images to a list of categories.
     * The function will not duplicate links and will preserve ranks.
     *
     * @param int[] $images
     * @param int[] $categories
     */
    public static function associate_images_to_categories($images, $categories)
    {
        global $conn;

        trigger_error('associate_images_to_categories is deprecated. Use CategoryMapper::associateImagesToCategories instead', E_USER_DEPRECATED);

        if (count($images) == 0 || count($categories) == 0) {
            return false;
        }

        // get existing associations
        $result = (new ImageCategoryRepository($conn))->findAll($images, $categories);

        $existing = [];
        while ($row = $conn->db_fetch_assoc($result)) {
            $existing[$row['category_id']][] = $row['image_id'];
        }

        // get max rank of each categories
        $current_rank_of = $conn->result2array(
            (new ImageCategoryRepository($conn))->findMaxRankForEachCategories($categories),
            'category_id',
            'max_rank'
        );

        // associate only not already associated images
        $inserts = [];
        foreach ($categories as $category_id) {
            if (!isset($current_rank_of[$category_id])) {
                $current_rank_of[$category_id] = 0;
            }
            if (!isset($existing[$category_id])) {
                $existing[$category_id] = [];
            }

            foreach ($images as $image_id) {
                if (!in_array($image_id, $existing[$category_id])) {
                    $rank = ++$current_rank_of[$category_id];

                    $inserts[] = [
                        'image_id' => $image_id,
                        'category_id' => $category_id,
                        'rank' => $rank,
                    ];
                }
            }
        }

        if (count($inserts)) {
            (new ImageCategoryRepository($conn))->insertImageCategories(
                array_keys($inserts[0]),
                $inserts
            );

            \Phyxo\Functions\Category::update_category($categories);
        }
    }

    /**
     * Is the category accessible to the (Admin) user ?
     * Note : if the user is not authorized to see this category, category jump
     * will be replaced by admin cat_modify page
     *
     * @param int $category_id
     * @return bool
     */
    public static function cat_admin_access($category_id)
    {
        global $user;

        // $filter['visible_categories'] and $filter['visible_images']
        // are not used because it's not necessary (filter <> restriction)
        if (in_array($category_id, explode(',', $user['forbidden_categories']))) {
            return false;
        }

        return true;
    }

    /**
     * Updates data of categories with filtered values
     *
     * @param array &$cats
     */
    public static function update_cats_with_filtered_data(&$cats)
    {
        global $filter;

        if ($filter['enabled']) {
            $upd_fields = ['date_last', 'max_date_last', 'count_images', 'count_categories', 'nb_images'];

            foreach ($cats as $cat_id => $category) {
                foreach ($upd_fields as $upd_field) {
                    $cats[$cat_id][$upd_field] = $filter['categories'][$category['id']][$upd_field];
                }
            }
        }
    }
}
