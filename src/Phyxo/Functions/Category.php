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
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\UserAccessRepository;
use App\Repository\GroupAccessRepository;
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
     * Generates breadcrumb for a category.
     * @see get_cat_display_name()
     *
     * @param int $cat_id
     * @param string|null $url
     * @return string
     */
    public static function get_cat_display_name_from_id($cat_id, $url = '')
    {
        $cat_info = self::get_cat_info($cat_id);
        return self::get_cat_display_name($cat_info['upper_names'], $url);
    }

    /**
     * Generates breadcrumb from categories list.
     * Categories string returned contains categories as given in the input
     * array $cat_informations. $cat_informations array must be an array
     * of array( id=>?, name=>?, permalink=>?). If url input parameter is null,
     * returns only the categories name without links.
     *
     * @param array $cat_informations
     * @param string|null $url
     * @return string
     */
    public static function get_cat_display_name($cat_informations, $url = '')
    {
        global $conf;

        $output = '';
        $is_first = true;

        foreach ($cat_informations as $cat) {
           // @TODO: find a better way to control input informations
            is_array($cat) or trigger_error(
                'get_cat_display_name wrong type for category ',
                E_USER_WARNING
            );

            $cat['name'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_category_name',
                $cat['name'],
                'get_cat_display_name'
            );

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $conf['level_separator'];
            }

            if (!isset($url)) {
                $output .= $cat['name'];
            } elseif ($url == '') {
                $output .= '<a href="' . \Phyxo\Functions\URL::make_index_url(['category' => $cat]) . '">';
                $output .= $cat['name'] . '</a>';
            } else {
                $output .= '<a href="' . $url . $cat['id'] . '">';
                $output .= $cat['name'] . '</a>';
            }
        }

        return $output;
    }

    /**
     * Generates breadcrumb from categories list using a cache.
     * @see get_cat_display_name()
     *
     * @param string $uppercats
     * @param string|null $url
     * @param bool $single_link
     * @param string|null $link_class
     * @return string
     */
    public static function get_cat_display_name_cache($uppercats, $url = '', $single_link = false, $link_class = null)
    {
        global $cache, $conf, $conn;

        if (!isset($cache['cat_names'])) {
            $cache['cat_names'] = $conn->result2array((new CategoryRepository($conn))->findAll(), 'id');
        }

        $output = '';
        if ($single_link) {
            $single_url = \Phyxo\Functions\URL::get_root_url() . $url . array_pop(explode(',', $uppercats));
            $output .= '<a href="' . $single_url . '"';
            if (isset($link_class)) {
                $output .= ' class="' . $link_class . '"';
            }
            $output .= '>';
        }

        // @TODO: refactoring with get_cat_display_name
        $is_first = true;
        foreach (explode(',', $uppercats) as $category_id) {
            $cat = $cache['cat_names'][$category_id];

            $cat['name'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_category_name',
                $cat['name'],
                'get_cat_display_name_cache'
            );

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $conf['level_separator'];
            }

            if (!isset($url) or $single_link) {
                $output .= $cat['name'];
            } elseif ($url == '') {
                $output .= '<a href="' . \Phyxo\Functions\URL::make_index_url(['category' => $cat]) . '">' . $cat['name'] . '</a>';
            } else {
                $output .= '<a href="' . $url . $category_id . '">' . $cat['name'] . '</a>';
            }
        }

        if ($single_link and isset($single_url)) {
            $output .= '</a>';
        }

        return $output;
    }

    /**
     * Assign a template var useable with {html_options} from a list of categories
     *
     * @param array[] $categories (at least id,name,global_rank,uppercats for each)
     * @param int[] $selected ids of selected items
     * @param string $blockname variable name in template
     * @param bool $fullname full breadcrumb or not
     */
    public static function display_select_categories($categories, $selecteds, $blockname, $fullname = true)
    {
        global $template;

        $tpl_cats = [];
        foreach ($categories as $category) {
            if ($fullname) {
                $option = strip_tags(
                    self::get_cat_display_name_cache(
                        $category['uppercats'],
                        null
                    )
                );
            } else {
                $option = str_repeat('&nbsp;', (3 * substr_count($category['global_rank'], '.')));
                $option .= '- ';
                $option .= strip_tags(
                    \Phyxo\Functions\Plugin::trigger_change(
                        'render_category_name',
                        $category['name'],
                        'display_select_categories'
                    )
                );
            }
            $tpl_cats[$category['id']] = $option;
        }

        $template->assign($blockname, $tpl_cats);
        $template->assign($blockname . '_selected', $selecteds);
    }

    /**
     * Same as display_select_categories but categories are ordered by rank
     * @see display_select_categories()
     */
    public static function display_select_cat_wrapper(array $categories, $selecteds, $blockname, $fullname = true)
    {
        usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');
        self::display_select_categories($categories, $selecteds, $blockname, $fullname);
    }

    /**
     * Recursively deletes one or more categories.
     * It also deletes :
     *    - all the elements physically linked to the category (with delete_elements)
     *    - all the links between elements and this category
     *    - all the restrictions linked to the category
     *
     * @param int[] $ids
     * @param string $photo_deletion_mode
     *    - no_delete : delete no photo, may create orphans
     *    - delete_orphans : delete photos that are no longer linked to any category
     *    - force_delete : delete photos even if they are linked to another category
     */
    public static function delete_categories($ids, $photo_deletion_mode = 'no_delete')
    {
        global $conn;

        if (count($ids) == 0) {
            return;
        }

        // add sub-category ids to the given ids : if a category is deleted, all
        // sub-categories must be so
        $ids = (new CategoryRepository($conn))->getSubcatIds($ids);

        // destruction of all photos physically linked to the category
        $element_ids = $conn->result2array((new ImageRepository($conn))->findByFields('storage_category_id', $ids), null, 'id');
        \Phyxo\Functions\Utils::delete_elements($element_ids);

        // now, should we delete photos that are virtually linked to the category?
        if ('delete_orphans' == $photo_deletion_mode or 'force_delete' == $photo_deletion_mode) {
            $image_ids_linked = $conn->result2array((new ImageCategoryRepository($conn))->getImageIdsLinked($ids), null, 'image_id');

            if (count($image_ids_linked) > 0) {
                if ('delete_orphans' == $photo_deletion_mode) {
                    $image_ids_not_orphans = $conn->result2array(
                        (new ImageCategoryRepository($conn))->getImageIdsNotOrphans($image_ids_linked, $ids),
                        null,
                        'image_id'
                    );
                    $image_ids_to_delete = array_diff($image_ids_linked, $image_ids_not_orphans);
                }

                if ('force_delete' == $photo_deletion_mode) {
                    $image_ids_to_delete = $image_ids_linked;
                }

                \Phyxo\Functions\Utils::delete_elements($image_ids_to_delete, true);
            }
        }

        // destruction of the links between images and this category
        (new ImageCategoryRepository($conn))->deleteByCategory($ids);

        // destruction of the access linked to the category
        (new UserAccessRepository($conn))->deleteByCatIds($ids);
        (new GroupAccessRepository($conn))->deleteByCatIds($ids);

        // destruction of the category
        (new CategoryRepository($conn))->deleteByIds($ids);

        (new OldPermalinkRepository($conn))->deleteByCatIds($ids);
        (new UserCacheCategoriesRepository($conn))->deleteByUserCatIds($ids);

        \Phyxo\Functions\Plugin::trigger_notify('delete_categories', $ids);
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
     * Get computed array of categories, that means cache data of all categories
     * available for the current user (count_categories, count_images, etc.).
     *
     * @param array &$userdata
     * @param int $filter_days number of recent days to filter on or null
     * @return array
     */
    public static function get_computed_categories(&$userdata, $filter_days = null)
    {
        global $conn;

        $result = (new CategoryRepository($conn))->getComputedCategories($userdata, $filter_days);
        $userdata['last_photo_date'] = null;
        $cats = [];
        while ($row = $conn->db_fetch_assoc($result)) {
            $row['user_id'] = $userdata['id'];
            $row['nb_categories'] = 0;
            $row['count_categories'] = 0;
            $row['count_images'] = (int)$row['nb_images'];
            $row['max_date_last'] = $row['date_last'];
            if ($row['date_last'] > $userdata['last_photo_date']) {
                $userdata['last_photo_date'] = $row['date_last'];
            }

            $cats[$row['cat_id']] = $row;
        }

        foreach ($cats as $cat) {
            if (!isset($cat['id_uppercat'])) {
                continue;
            }

            // Piwigo before 2.5.3 may have generated inconsistent permissions, ie
            // private album A1/A2 permitted to user U1 but private album A1 not
            // permitted to U1.
            //
            // TODO 2.7: add an upgrade script to repair permissions and remove this test
            if (!isset($cats[$cat['id_uppercat']])) {
                continue;
            }

            $parent = &$cats[$cat['id_uppercat']];
            $parent['nb_categories']++;

            do {
                $parent['count_images'] += $cat['nb_images'];
                $parent['count_categories']++;

                if ((empty($parent['max_date_last'])) or ($parent['max_date_last'] < $cat['date_last'])) {
                    $parent['max_date_last'] = $cat['date_last'];
                }

                if (!isset($parent['id_uppercat'])) {
                    break;
                }
                $parent = &$cats[$parent['id_uppercat']];
            } while (true);
            unset($parent);
        }

        if (isset($filter_days)) {
            foreach ($cats as $category) {
                if (empty($category['max_date_last'])) {
                    self::remove_computed_category($cats, $category);
                }
            }
        }

        return $cats;
    }

    /**
     * Removes a category from computed array of categories and updates counters.
     *
     * @param array &$cats
     * @param array $cat category to remove
     */
    public static function remove_computed_category(&$cats, $cat)
    {
        if (isset($cats[$cat['id_uppercat']])) {
            $parent = &$cats[$cat['id_uppercat']];
            $parent['nb_categories']--;

            do {
                $parent['count_images'] -= $cat['nb_images'];
                $parent['count_categories'] -= 1 + $cat['count_categories'];

                if (!isset($cats[$parent['id_uppercat']])) {
                    break;
                }
                $parent = &$cats[$parent['id_uppercat']];
            } while (true);
        }

        unset($cats[$cat['cat_id']]);
    }

    /**
     * Change the **visible** property on a set of categories.
     *
     * @param int[] $categories
     * @param boolean $unlock_child optional   default false
     */
    public static function set_cat_visible($categories, bool $value, $unlock_child = false)
    {
        global $conn;

        // unlocking a category => all its parent categories become unlocked
        if ($value) {
            $cats = self::get_uppercat_ids($categories);
            if ($unlock_child) {
                $cats = array_merge($cats, (new CategoryRepository($conn))->getSubcatIds($categories));
            }

            (new CategoryRepository($conn))->updateCategories(['visible' => true], $cats);
        } else { // locking a category   => all its child categories become locked
            $subcats = (new CategoryRepository($conn))->getSubcatIds($categories);
            (new CategoryRepository($conn))->updateCategories(['visible' => false], $subcats);
        }
    }

    /**
     * Change the **status** property on a set of categories : private or public.
     *
     * @param int[] $categories
     * @param string $value
     */
    public static function set_cat_status($categories, $value)
    {
        global $conn;

        if (!in_array($value, ['public', 'private'])) {
            trigger_error("set_cat_status invalid param $value", E_USER_WARNING);
            return false;
        }

        // make public a category => all its parent categories become public
        if ($value == 'public') {
            $uppercats = self::get_uppercat_ids($categories);
            (new CategoryRepository($conn))->updateCategories(
                ['status' => 'public'],
                $uppercats
            );
        }

        // make a category private => all its child categories become private
        if ($value == 'private') {
            $subcats = (new CategoryRepository($conn))->getSubcatIds($categories);
            (new CategoryRepository($conn))->updateCategories(['status' => 'private'], $subcats);

            // @TODO: add unit tests for that
            // We have to keep permissions consistant: a sub-album can't be
            // permitted to a user or group if its parent album is not permitted to
            // the same user or group. Let's remove all permissions on sub-albums if
            // it is not consistant. Let's take the following example:
            //
            // A1        permitted to U1,G1
            // A1/A2     permitted to U1,U2,G1,G2
            // A1/A2/A3  permitted to U3,G1
            // A1/A2/A4  permitted to U2
            // A1/A5     permitted to U4
            // A6        permitted to U4
            // A6/A7     permitted to G1
            //
            // (we consider that it can be possible to start with inconsistant
            // permission, given that public albums can have hidden permissions,
            // revealed once the album returns to private status)
            //
            // The admin selects A2,A3,A4,A5,A6,A7 to become private (all but A1,
            // which is private, which can be true if we're moving A2 into A1). The
            // result must be:
            //
            // A2 permission removed to U2,G2
            // A3 permission removed to U3
            // A4 permission removed to U2
            // A5 permission removed to U2
            // A6 permission removed to U4
            // A7 no permission removed
            //
            // 1) we must extract "top albums": A2, A5 and A6
            // 2) for each top album, decide which album is the reference for permissions
            // 3) remove all inconsistant permissions from sub-albums of each top-album

            // step 1, search top albums
            $top_categories = [];
            $parent_ids = [];

            $all_categories = $conn->result2array((new CategoryRepository($conn))->findByIds($categories));
            usort($all_categories, '\Phyxo\Functions\Utils::global_rank_compare');

            foreach ($all_categories as $cat) {
                $is_top = true;

                if (!empty($cat['id_uppercat'])) {
                    foreach (explode(',', $cat['uppercats']) as $id_uppercat) {
                        if (isset($top_categories[$id_uppercat])) {
                            $is_top = false;
                            break;
                        }
                    }
                }

                if ($is_top) {
                    $top_categories[$cat['id']] = $cat;

                    if (!empty($cat['id_uppercat'])) {
                        $parent_ids[] = $cat['id_uppercat'];
                    }
                }
            }

            // step 2, search the reference album for permissions
            //
            // to find the reference of each top album, we will need the parent albums
            $parent_cats = [];

            if (count($parent_ids) > 0) {
                $parent_cats = $conn->result2array((new CategoryRepository($conn))->findByIds($parent_ids), 'id');
            }

            $repositories = [
                '\App\Repository\UserAccessRepository' => 'user_id',
                '\App\Repository\GroupAccessRepository' => 'group_id'
            ];

            foreach ($top_categories as $top_category) {
                // what is the "reference" for list of permissions? The parent album
                // if it is private, else the album itself
                $ref_cat_id = $top_category['id'];

                if (!empty($top_category['id_uppercat']) && isset($parent_cats[$top_category['id_uppercat']])
                    && 'private' == $parent_cats[$top_category['id_uppercat']]['status']) {
                    $ref_cat_id = $top_category['id_uppercat'];
                }

                $subcats = (new CategoryRepository($conn))->getSubcatIds([$top_category['id']]);

                foreach ($repositories as $repository => $field) {
                    // what are the permissions user/group of the reference album
                    $ref_access = $conn->result2array((new $repository($conn))->findFieldByCatId($ref_cat_id, $field), null, $field);

                    if (count($ref_access) == 0) {
                        $ref_access[] = -1;
                    }

                    // step 3, remove the inconsistant permissions from sub-albums
                    (new $repository($conn))->deleteByCatIds($subcats, $field . ' NOT ' . $conn->in($ref_access));
                }
            }
        }
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
     * Returns all uppercats category ids of the given category ids.
     *
     * @param int[] $cat_ids
     * @return int[]
     */
    public static function get_uppercat_ids($cat_ids)
    {
        global $conn;

        if (!is_array($cat_ids) or count($cat_ids) < 1) {
            return [];
        }

        $uppercats = [];
        $result = (new CategoryRepository($conn))->findByIds($cat_ids);
        while ($row = $conn->db_fetch_assoc($result)) {
            $uppercats = array_merge($uppercats, explode(',', $row['uppercats']));
        }

        return array_unique($uppercats);
    }

    /**
     * Grant access to a list of categories for a list of users.
     *
     * @param int[] $category_ids
     * @param int[] $user_ids
     */
    public static function add_permission_on_category($category_ids, $user_ids)
    {
        global $conn;

        if (!is_array($category_ids)) {
            $category_ids = [$category_ids];
        }
        if (!is_array($user_ids)) {
            $user_ids = [$user_ids];
        }

        // check for emptiness
        if (count($category_ids) == 0 or count($user_ids) == 0) {
            return;
        }

        // make sure categories are private and select uppercats or subcats
        $cat_ids = self::get_uppercat_ids($category_ids);
        if (isset($_POST['apply_on_sub'])) {
            $cat_ids = array_merge($cat_ids, (new CategoryRepository($conn))->getSubcatIds($category_ids));
        }

        $private_cats = $conn->result2array((new CategoryRepository($conn))->findByIdsAndStatus($cat_ids, 'private'), null, 'id');

        if (count($private_cats) == 0) {
            return;
        }

        $inserts = [];
        foreach ($private_cats as $cat_id) {
            foreach ($user_ids as $user_id) {
                $inserts[] = [
                    'user_id' => $user_id,
                    'cat_id' => $cat_id
                ];
            }
        }

        (new UserAccessRepository($conn))->insertUserAccess(
            ['user_id', 'cat_id'],
            $inserts
        );
    }

    /**
     * Create a virtual category.
     *
     * @param string $category_name
     * @param int $parent_id
     * @param array $options
     *    - boolean commentable
     *    - boolean visible
     *    - string status
     *    - string comment
     *    - boolean inherit
     * @return array ('info', 'id') or ('error')
     */
    public static function create_virtual_category($category_name, $parent_id = null, $options = [])
    {
        global $conf, $user, $conn;

        // is the given category name only containing blank spaces ?
        if (preg_match('/^\s*$/', $category_name)) {
            return ['error' => \Phyxo\Functions\Language::l10n('The name of an album must not be empty')];
        }

        $insert = [
            'name' => $category_name,
            'rank' => 0,
            'global_rank' => 0,
        ];

        // is the album commentable?
        if (isset($options['commentable']) and is_bool($options['commentable'])) {
            $insert['commentable'] = $options['commentable'];
        } else {
            $insert['commentable'] = $conf['newcat_default_commentable'];
        }
        $insert['commentable'] = $conn->boolean_to_string($insert['commentable']);

        // is the album temporarily locked? (only visible by administrators,
        // whatever permissions) (may be overwritten if parent album is not visible)
        if (isset($options['visible']) and is_bool($options['visible'])) {
            $insert['visible'] = $options['visible'];
        } else {
            $insert['visible'] = $conf['newcat_default_visible'];
        }
        $insert['visible'] = $conn->boolean_to_string($insert['visible']);

        // is the album private? (may be overwritten if parent album is private)
        if (isset($options['status']) and 'private' == $options['status']) {
            $insert['status'] = 'private';
        } else {
            $insert['status'] = $conf['newcat_default_status'];
        }

        // any description for this album?
        if (isset($options['comment'])) {
            $insert['comment'] = $conf['allow_html_descriptions'] ? $options['comment'] : strip_tags($options['comment']);
        }

        if (!empty($parent_id) and is_numeric($parent_id)) {
            $parent = (new CategoryRepository($conn))->findById($parent_id);

            $insert['id_uppercat'] = (int)$parent['id'];
            $insert['global_rank'] = $parent['global_rank'] . '.' . $insert['rank'];

            // at creation, must a category be visible or not ? Warning : if the
            // parent category is invisible, the category is automatically create
            // invisible. (invisible = locked)
            if ($conn->get_boolean($parent['visible']) === false) {
                $insert['visible'] = 'false';
            }

            // at creation, must a category be public or private ? Warning : if the
            // parent category is private, the category is automatically create private.
            if ('private' == $parent['status']) {
                $insert['status'] = 'private';
            }

            $uppercats_prefix = $parent['uppercats'] . ',';
        } else {
            $uppercats_prefix = '';
        }

        // we have then to add the virtual category
        $inserted_id = (new CategoryRepository($conn))->insertCategory($insert);
        (new CategoryRepository($conn))->updateCategory(
            ['uppercats' => $uppercats_prefix . $inserted_id],
            $inserted_id
        );

        \Phyxo\Functions\Utils::update_global_rank();

        if ('private' == $insert['status'] && !empty($insert['id_uppercat']) && ((isset($options['inherit']) && $options['inherit']) || $conf['inheritance_by_default'])) {
            $result = (new GroupAccessRepository($conn))->findFieldByCatId($insert['id_uppercat'], 'group_id');
            $granted_grps = $conn->result2array($result, null, 'group_id');
            $inserts = [];
            foreach ($granted_grps as $granted_grp) {
                $inserts[] = ['group_id' => $granted_grp, 'cat_id' => $inserted_id];
            }
            (new GroupAccessRepository($conn))->massInserts(['group_id', 'cat_id'], $inserts);

            $result = (new UserAccessRepository($conn))->findByCatId($insert['id_uppercat']);
            $granted_users = $conn->result2array($result, null, 'user_id');
            self::add_permission_on_category(
                $inserted_id,
                array_unique(array_merge(\Phyxo\Functions\Utils::get_admins(), [$user['id']], $granted_users))
            );
        } elseif ('private' == $insert['status']) {
            self::add_permission_on_category(
                $inserted_id,
                array_unique(array_merge(\Phyxo\Functions\Utils::get_admins(), [$user['id']]))
            );
        }

        return [
            'info' => \Phyxo\Functions\Language::l10n('Virtual album added'),
            'id' => $inserted_id,
        ];
    }

    /**
     * Returns the fulldir for each given category id.
     *
     * @param int[] intcat_ids
     * @return string[]
     */
    public static function get_fulldirs($cat_ids)
    {
        global $cat_dirs, $conn;

        if (count($cat_ids) == 0) {
            return [];
        }

        // caching directories of existing categories
        $cat_dirs = $conn->result2array((new CategoryRepository($conn))->findByDir('IS NOT NULL'), 'id', 'dir');

        // caching galleries_url
        $galleries_url = $conn->result2array((new SiteRepository($conn))->findAll(), 'id', 'galleries_url');

        // categories : id, site_id, uppercats
        $categories = $conn->result2array((new CategoryRepository($conn))->findByIdsAndDir($cat_ids, 'IS NOT NULL'));

        // filling $cat_fulldirs
        $cat_dirs_callback = function ($m) use ($cat_dirs) {
            return $cat_dirs[$m[1]];
        };

        $cat_fulldirs = [];
        foreach ($categories as $category) {
            $uppercats = str_replace(',', '/', $category['uppercats']);
            $cat_fulldirs[$category['id']] = $galleries_url[$category['site_id']];
            $cat_fulldirs[$category['id']] .= preg_replace_callback(
                '/(\d+)/',
                $cat_dirs_callback,
                $uppercats
            );
        }

        unset($cat_dirs);

        return $cat_fulldirs;
    }

    /**
     * Updates categories.uppercats field based on categories.id + categories.id_uppercat
     */
    public static function update_uppercats()
    {
        global $conn;

        $cat_map = $conn->result2array((new CategoryRepository($conn))->findAll(), 'id');
        $datas = [];
        foreach ($cat_map as $id => $cat) {
            $upper_list = [];

            $uppercat = $id;
            while ($uppercat) {
                $upper_list[] = $uppercat;
                $uppercat = $cat_map[$uppercat]['id_uppercat'];
            }

            $new_uppercats = implode(',', array_reverse($upper_list));
            if ($new_uppercats != $cat['uppercats']) {
                $datas[] = [
                    'id' => $id,
                    'uppercats' => $new_uppercats
                ];
            }
        }
        $fields = ['primary' => ['id'], 'update' => ['uppercats']];
        (new CategoryRepository($conn))->massUpdatesCategories($fields, $datas);
    }

    /**
     * Change the parent category of the given categories. The categories are
     * supposed virtual.
     *
     * @param int[] $category_ids
     * @param int $new_parent (-1 for root)
     */
    public static function move_categories($category_ids, $new_parent = -1)
    {
        global $page, $conn;

        if (count($category_ids) == 0) {
            return;
        }

        $new_parent = $new_parent < 1 ? null : $new_parent;
        $categories = [];

        $result = (new CategoryRepository($conn))->findByIds($category_ids);
        while ($row = $conn->db_fetch_assoc($result)) {
            $categories[$row['id']] = [
                'parent' => empty($row['id_uppercat']) ? null : $row['id_uppercat'],
                'status' => $row['status'],
                'uppercats' => $row['uppercats']
            ];
        }

        // is the movement possible? The movement is impossible if you try to move
        // a category in a sub-category or itself
        if ($new_parent !== null) {
            $new_parent_uppercats = (new CategoryRepository($conn))->findById($new_parent)['uppercats'];

            foreach ($categories as $category) {
            // technically, you can't move a category with uppercats 12,125,13,14
            // into a new parent category with uppercats 12,125,13,14,24
                if (preg_match('/^' . $category['uppercats'] . '(,|$)/', $new_parent_uppercats)) {
                    $page['errors'][] = \Phyxo\Functions\Language::l10n('You cannot move an album in its own sub album');
                    return;
                }
            }
        }

        (new CategoryRepository($conn))->updateCategories(['id_uppercat' => $new_parent], $category_ids);
        self::update_uppercats();
        \Phyxo\Functions\Utils::update_global_rank();

        // status and related permissions management
        if ($new_parent === null) {
            $parent_status = 'public';
        } else {
            $parent_status = (new CategoryRepository($conn))->findById($new_parent)['status'];
        }

        if ('private' == $parent_status) {
            self::set_cat_status(array_keys($categories), 'private');
        }

        $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
            '%d album moved',
            '%d albums moved',
            count($categories)
        );
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
     * Dissociate images from all old categories except their storage category and
     * associate to new categories.
     * This function will preserve ranks.
     *
     * @param int[] $images
     * @param int[] $categories
     */
    public static function move_images_to_categories($images, $categories)
    {
        global $conn;

        if (count($images) == 0) {
            return false;
        }

        $cat_ids = $conn->result2array((new ImageRepository($conn))->findWithNoStorageOrStorageCategoryId($categories), null, 'id');

        // let's first break links with all old albums but their "storage album"
        (new ImageCategoryRepository($conn))->deleteByCategory($cat_ids, $images);

        if (is_array($categories) and count($categories) > 0) {
            self::associate_images_to_categories($images, $categories);
        }
    }

    /**
     * Associate images associated to a list of source categories to a list of
     * destination categories.
     *
     * @param int[] $sources
     * @param int[] $destinations
     */
    public static function associate_categories_to_categories($sources, $destinations)
    {
        global $conn;

        if (count($sources) == 0) {
            return false;
        }

        $images = $conn->result2array((new ImageCategoryRepository($conn))->findAll($sources), null, 'image_id');

        self::associate_images_to_categories($images, $destinations);
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
