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
use App\Repository\OldPermalinkRepository;
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
     * Returns the category comment for rendering in html textual mode (subcatify)
     *
     * @param string $desc
     * @return string
     */
    public static function render_category_literal_description($desc)
    {
        return strip_tags($desc, '<span><p><a><br><b><i><small><big><strong><em>');
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

        if (!empty($filter['enabled'])) {
            $upd_fields = ['date_last', 'max_date_last', 'count_images', 'count_categories', 'nb_images'];

            foreach ($cats as $cat_id => $category) {
                foreach ($upd_fields as $upd_field) {
                    $cats[$cat_id][$upd_field] = $filter['categories'][$category['id']][$upd_field];
                }
            }
        }
    }
}
