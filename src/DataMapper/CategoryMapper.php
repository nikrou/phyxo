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

use App\Repository\CategoryRepository;
use Phyxo\Functions\Category;
use Phyxo\EntityManager;
use Phyxo\Conf;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\UserAccessRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\OldPermalinkRepository;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\SiteRepository;

class CategoryMapper
{
    private $em, $conf, $router;

    public function __construct(Conf $conf, EntityManager $em, RouterInterface $router)
    {
        $this->conf = $conf;
        $this->em = $em;
        $this->router = $router;
    }

    /**
     * Returns template vars for main categories menu.
     *
     */
    public function getRecursiveCategoriesMenu(UserInterface $user, array $filter = [], array $selected_category = []): array
    {
        $flat_categories = $this->getCategoriesMenu($user, $filter, $selected_category);

        $categories = [];
        foreach ($flat_categories as $category) {
            if ($category['uppercats'] === $category['id']) {
                $categories[$category['id']] = $category;
            } else {
                $this->insertCategoryInTree($categories, $category, $category['uppercats']);
            }
        }

        return $categories;
    }

    protected function insertCategoryInTree(&$categories, $category, $uppercats)
    {
        if ($category['id'] != $uppercats) {
            $cats = explode(',', $uppercats);
            $cat = $cats[0];
            $new_uppercats = array_slice($cats, 1);
            if (count($new_uppercats) === 1) {
                $categories[$cat]['children'][$category['id']] = $category;
            } else {
                $this->insertCategoryInTree($categories[$cat]['children'], $category, implode(',', $new_uppercats));
            }
        }
    }

    /**
     * Returns template vars for main categories menu.
     *
     */
    protected function getCategoriesMenu(UserInterface $user, array $filter = [], array $selected_category = []): array
    {
        $result = $this->em->getRepository(CategoryRepository::class)->getCategoriesForMenu(
            $user,
            $filter,
            isset($selected_category['uppercats']) ? explode(',', $selected_category['uppercats']) : []
        );

        $cats = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $child_date_last = (isset($row['max_date_last'], $row['date_last']) && ($row['max_date_last'] > $row['date_last']));

            $row = array_merge(
                $row,
                [
                    'NAME' => \Phyxo\Functions\Plugin::trigger_change(
                        'render_category_name',
                        $row['name'],
                        'get_categories_menu'
                    ),
                    'TITLE' => Category::get_display_images_count(
                        $row['nb_images'],
                        $row['count_images'],
                        $row['count_categories'],
                        false,
                        ' / '
                    ),
                    'URL' => $this->router->generate('album', ['category_id' => $row['id']]),
                    'LEVEL' => substr_count($row['global_rank'], '.') + 1,
                    'SELECTED' => isset($selected_category['id']) && $selected_category['id'] === $row['id'] ? true : false,
                    'IS_UPPERCAT' => isset($selected_category['id_uppercat']) && $selected_category['id_uppercat'] === $row['id'] ? true : false,
                ]
            );
            // if ($this->conf['index_new_icon']) {
            //     $row['icon_ts'] = \Phyxo\Functions\Utils::get_icon($row['max_date_last'], $child_date_last);
            // }
            $cats[$row['id']] = $row;
        }
        uasort($cats, '\Phyxo\Functions\Utils::global_rank_compare');

        // Update filtered data
        Category::update_cats_with_filtered_data($cats);

        return $cats;
    }

    /**
     * Get computed array of categories, that means cache data of all categories
     * available for the current user (count_categories, count_images, etc.).
     */
    public function getComputedCategories(&$userdata, $filter_days = null)
    {
        $result = $this->em->getRepository(CategoryRepository::class)->getComputedCategories($userdata, $filter_days);
        $userdata['last_photo_date'] = null;
        $cats = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
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

            if (!isset($cats[ $cat['id_uppercat'] ])) {
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
                    $this->removeComputedCategory($cats, $category);
                }
            }
        }

        return $cats;
    }

    /**
     * Removes a category from computed array of categories and updates counters.
     */
    public function removeComputedCategory(&$cats, $cat)
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
     * Retrieves informations about a category.
     */
    public function getCatInfo(int $id): array
    {
        $cat = $this->em->getRepository(CategoryRepository::class)->findById($id);
        if (empty($cat)) {
            return [];
        }

        foreach ($cat as $k => $v) {
            // If the field is true or false, the variable is transformed into a boolean value.
            if (!is_null($v) && $this->em->getConnection()->is_boolean($v)) {
                $cat[$k] = $this->em->getConnection()->get_boolean($v);
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
            $result = $this->em->getRepository(CategoryRepository::class)->findByIds($upper_ids);
            $names = $this->em->getConnection()->result2array($result, 'id');
            // category names must be in the same order than uppercats list
            $cat['upper_names'] = [];
            foreach ($upper_ids as $cat_id) {
                $cat['upper_names'][] = $names[$cat_id];
            }
        }

        return $cat;
    }

    /**
     * Generates breadcrumb for a category.
     * @see getCatDisplayName()
     */
    public function getCatDisplayNameFromId(int $cat_id, string $url = ''): string
    {
        $cat_info = $this->getCatInfo($cat_id);

        return $this->getCatDisplayName($cat_info['upper_names'], $url);
    }

    /**
     * Generates breadcrumb from categories list.
     * Categories string returned contains categories as given in the input
     * array $cat_informations. $cat_informations array must be an array
     * of array( id=>?, name=>?, permalink=>?). If url input parameter is null,
     * returns only the categories name without links.
     */
    public function getCatDisplayName(array $cat_informations, string $url = ''): string
    {
        $output = '';
        $is_first = true;

        foreach ($cat_informations as $cat) {
            $cat['name'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_category_name',
                $cat['name'],
                'CategoryMapper::getCatDisplayName'
            );

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $this->conf['level_separator'];
            }

            if (!isset($url)) {
                $output .= $cat['name'];
            } elseif ($url == '') {
                $output .= '<a href="' . $this->router->generate('album', ['category_id' => $cat['id']]) . '">';
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
     * @see getCatDisplayName()
     */
    public function getCatDisplayNameCache(string $uppercats, string $url = '', bool $single_link = false, string $link_class = ''): string
    {
        $cache = [];

        if (!isset($cache['cat_names'])) {
            $result = $this->em->getRepository(CategoryRepository::class)->findAll();
            $cache['cat_names'] = $this->em->getConnection()->result2array($result, 'id');
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

        // @TODO: refactoring with getCatDisplayName
        $is_first = true;
        foreach (explode(',', $uppercats) as $category_id) {
            $cat = $cache['cat_names'][$category_id];

            $cat['name'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_category_name',
                $cat['name'],
                'getCatDisplayNameCache'
            );

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $this->conf['level_separator'];
            }

            if (!isset($url) or $single_link) {
                $output .= $cat['name'];
            } elseif ($url == '') {
                $output .= '<a href="' . $this->router->generate('album', ['category_id' => $cat['id']]) . '">' . $cat['name'] . '</a>';
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
    public function displaySelectCategories(array $categories, array $selecteds, string $blockname, bool $fullname = true)
    {
        $tpl_cats = [];
        foreach ($categories as $category) {
            if ($fullname) {
                $option = strip_tags($this->getCatDisplayNameCache($category['uppercats']));
            } else {
                $option = str_repeat('&nbsp;', (3 * substr_count($category['global_rank'], '.')));
                $option .= '- ';
                $option .= strip_tags(
                    \Phyxo\Functions\Plugin::trigger_change(
                        'render_category_name',
                        $category['name'],
                        'displaySelectCategories'
                    )
                );
            }
            $tpl_cats[$category['id']] = $option;
        }

        return [
            $blockname => $tpl_cats,
            $blockname . '_selected' => $selecteds
        ];
    }

    /**
     * Same as displaySelectCategories but categories are ordered by rank
     * @see displaySelectCategories()
     */
    public function displaySelectCategoriesWrapper(array $categories, array $selecteds, string $blockname, bool $fullname = true): array
    {
        usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');

        return $this->displaySelectCategories($categories, $selecteds, $blockname, $fullname);
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
    public function deleteCategories(array $ids, $photo_deletion_mode = 'no_delete')
    {
        if (count($ids) == 0) {
            return;
        }

        // add sub-category ids to the given ids : if a category is deleted, all
        // sub-categories must be so
        $ids = array_merge($ids, $this->em->getRepository(CategoryRepository::class)->getSubcatIds($ids));

        // destruction of all photos physically linked to the category
        $result = $this->em->getRepository(ImageRepository::class)->findByFields('storage_category_id', $ids);
        $element_ids = $this->em->getConnection()->result2array($result, null, 'id');
        \Phyxo\Functions\Utils::delete_elements($element_ids);

        // now, should we delete photos that are virtually linked to the category?
        if ($photo_deletion_mode === 'delete_orphans' || $photo_deletion_mode === 'force_delete') {
            $result = $this->em->getRepository(ImageCategoryRepository::class)->getImageIdsLinked($ids);
            $image_ids_linked = $this->em->getConnection()->result2array($result, null, 'image_id');

            if (count($image_ids_linked) > 0) {
                if ($photo_deletion_mode === 'delete_orphans') {
                    $result = $this->em->getRepository(ImageCategoryRepository::class)->getImageIdsNotOrphans($image_ids_linked, $ids);

                    $image_ids_not_orphans = $this->em->getConnection()->result2array($result, null, 'image_id');
                    $image_ids_to_delete = array_diff($image_ids_linked, $image_ids_not_orphans);
                }

                if ($photo_deletion_mode === 'force_delete') {
                    $image_ids_to_delete = $image_ids_linked;
                }

                \Phyxo\Functions\Utils::delete_elements($image_ids_to_delete, true);
            }
        }

        // destruction of the links between images and this category
        $this->em->getRepository(ImageCategoryRepository::class)->deleteByCategory($ids);

        // destruction of the access linked to the category
        $this->em->getRepository(UserAccessRepository::class)->deleteByCatIds($ids);
        $this->em->getRepository(GroupAccessRepository::class)->deleteByCatIds($ids);

        // destruction of the category
        $this->em->getRepository(CategoryRepository::class)->deleteByIds($ids);

        $this->em->getRepository(OldPermalinkRepository::class)->deleteByCatIds($ids);
        $this->em->getRepository(UserCacheCategoriesRepository::class)->deleteByUserCatIds($ids);

        \Phyxo\Functions\Plugin::trigger_notify('CategoryMapper::deleteCategories', $ids);
    }

    /**
     * Change the parent category of the given categories. The categories are supposed virtual.
     *
     * @param int[] $category_ids
     * @param int $new_parent (-1 for root)
     */
    public function moveCategories(array $category_ids, int $new_parent = -1)
    {
        if (count($category_ids) == 0) {
            return;
        }

        $new_parent = $new_parent < 1 ? null : $new_parent;
        $categories = [];

        $result = $this->em->getRepository(CategoryRepository::class)->findByIds($category_ids);
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $categories[$row['id']] = [
                'parent' => empty($row['id_uppercat']) ? null : $row['id_uppercat'],
                'status' => $row['status'],
                'uppercats' => $row['uppercats']
            ];
        }

        // is the movement possible? The movement is impossible if you try to move a category in a sub-category or itself
        if ($new_parent !== null) {
            $new_parent_uppercats = $this->em->getRepository(CategoryRepository::class)->findById($new_parent)['uppercats'];

            foreach ($categories as $category) {
            // technically, you can't move a category with uppercats 12,125,13,14
            // into a new parent category with uppercats 12,125,13,14,24
                if (preg_match('/^' . $category['uppercats'] . '(,|$)/', $new_parent_uppercats)) {
                    throw new \Exception(\Phyxo\Functions\Language::l10n('You cannot move an album in its own sub album'));
                }
            }
        }

        $this->em->getRepository(CategoryRepository::class)->updateCategories(['id_uppercat' => $new_parent], $category_ids);
        $this->updateUppercats();
        $this->updateGlobalRank();

        // status and related permissions management
        if ($new_parent === null) {
            $parent_status = 'public';
        } else {
            $parent_status = $this->em->getRepository(CategoryRepository::class)->findById($new_parent)['status'];
        }

        if ($parent_status === 'private') {
            $this->setCatStatus(array_keys($categories), 'private');
        }


        // $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
        //     '%d album moved',
        //     '%d albums moved',
        //     count($categories)
        // );
    }

    /**
     * Updates categories.uppercats field based on categories.id + categories.id_uppercat
     */
    public function updateUppercats()
    {
        $result = $this->em->getRepository(CategoryRepository::class)->findAll();
        $cat_map = $this->em->getConnection()->result2array($result, 'id');
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
        $this->em->getRepository(CategoryRepository::class)->massUpdatesCategories($fields, $datas);
    }

    /**
     * Change the **status** property on a set of categories : private or public.
     *
     * @param int[] $categories
     * @param string $value
     */
    public function setCatStatus(array $categories, string $value)
    {
        if (!in_array($value, ['public', 'private'])) {
            throw new \Exception("CategoryMapper::setCatStatus invalid param $value");
        }

        // make public a category => all its parent categories become public
        if ($value === 'public') {
            $uppercats = $this->getUppercatIds($categories);
            $this->em->getRepository(CategoryRepository::class)->updateCategories(
                ['status' => 'public'],
                $uppercats
            );
        }

        // make a category private => all its child categories become private
        if ($value === 'private') {
            $subcats = $this->em->getRepository(CategoryRepository::class)->getSubcatIds($categories);
            $this->em->getRepository(CategoryRepository::class)->updateCategories(['status' => 'private'], $subcats);

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

            $result = $this->em->getRepository(CategoryRepository::class)->findByIds($categories);
            $all_categories = $this->em->getConnection()->result2array($result);
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
                $result = $this->em->getRepository(CategoryRepository::class)->findByIds($parent_ids);
                $parent_cats = $this->em->getConnection()->result2array($result, 'id');
            }

            $repositories = [
                UserAccessRepository::class => 'user_id',
                GroupAccessRepository::class => 'group_id'
            ];

            foreach ($top_categories as $top_category) {
                // what is the "reference" for list of permissions? The parent album
                // if it is private, else the album itself
                $ref_cat_id = $top_category['id'];

                if (!empty($top_category['id_uppercat']) && isset($parent_cats[$top_category['id_uppercat']])
                    && 'private' == $parent_cats[$top_category['id_uppercat']]['status']) {
                    $ref_cat_id = $top_category['id_uppercat'];
                }

                $subcats = $this->em->getRepository(CategoryRepository::class)->getSubcatIds([$top_category['id']]);

                foreach ($repositories as $repository => $field) {
                    // what are the permissions user/group of the reference album
                    $result = $this->em->getRepository($repository)->findFieldByCatId($ref_cat_id, $field);
                    $ref_access = $this->em->getConnection()->result2array($result, null, $field);

                    if (count($ref_access) == 0) {
                        $ref_access[] = -1;
                    }

                    // step 3, remove the inconsistant permissions from sub-albums
                    $this->em->getRepository($repository)->deleteByCatIds($subcats, $field . ' NOT ' . $this->em->getConnection()->in($ref_access));
                }
            }
        }
    }

    /**
     * Returns all uppercats category ids of the given category ids.
     *
     * @param int[] $cat_ids
     * @return int[]
     */
    public function getUppercatIds(array $cat_ids): array
    {
        if (!is_array($cat_ids) or count($cat_ids) < 1) {
            return [];
        }

        $uppercats = [];
        $result = $this->em->getRepository(CategoryRepository::class)->findByIds($cat_ids);
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
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
    public function addPermissionOnCategory(array $category_ids, array $user_ids)
    {
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
        $cat_ids = $this->getUppercatIds($category_ids);
        if (isset($_POST['apply_on_sub'])) {
            $cat_ids = array_merge($cat_ids, $this->em->getRepository(CategoryRepository::class)->getSubcatIds($category_ids));
        }

        $private_cats = $this->em->getConnection()->result2array($this->em->getRepository(CategoryRepository::class)->findByIdsAndStatus($cat_ids, 'private'), null, 'id');

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

        $this->em->getRepository(UserAccessRepository::class)->insertUserAccess(['user_id', 'cat_id'], $inserts);
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
    public function createVirtualCategory(string $category_name, int $parent_id = null, int $user_id, array $options = []): array
    {
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
        if (isset($options['commentable']) && is_bool($options['commentable'])) {
            $insert['commentable'] = $options['commentable'];
        } else {
            $insert['commentable'] = $this->conf['newcat_default_commentable'];
        }
        $insert['commentable'] = $this->em->getConnection()->boolean_to_string($insert['commentable']);

        // is the album temporarily locked? (only visible by administrators,
        // whatever permissions) (may be overwritten if parent album is not visible)
        if (isset($options['visible']) && is_bool($options['visible'])) {
            $insert['visible'] = $options['visible'];
        } else {
            $insert['visible'] = $this->conf['newcat_default_visible'];
        }
        $insert['visible'] = $this->em->getConnection()->boolean_to_string($insert['visible']);

        // is the album private? (may be overwritten if parent album is private)
        if (isset($options['status']) && $options['status'] === 'private') {
            $insert['status'] = 'private';
        } else {
            $insert['status'] = $this->conf['newcat_default_status'];
        }

        // any description for this album?
        if (isset($options['comment'])) {
            $insert['comment'] = $this->conf['allow_html_descriptions'] ? $options['comment'] : strip_tags($options['comment']);
        }

        if (!empty($parent_id) && is_numeric($parent_id)) {
            $parent = $this->em->getRepository(CategoryRepository::class)->findById($parent_id);

            $insert['id_uppercat'] = (int)$parent['id'];
            $insert['global_rank'] = $parent['global_rank'] . '.' . $insert['rank'];

            // at creation, must a category be visible or not ? Warning : if the
            // parent category is invisible, the category is automatically create
            // invisible. (invisible = locked)
            if ($this->em->getConnection()->get_boolean($parent['visible']) === false) {
                $insert['visible'] = 'false';
            }

            // at creation, must a category be public or private ? Warning : if the
            // parent category is private, the category is automatically create private.
            if ($parent['status'] === 'private') {
                $insert['status'] = 'private';
            }

            $uppercats_prefix = $parent['uppercats'] . ',';
        } else {
            $uppercats_prefix = '';
        }

        // we have then to add the virtual category
        $inserted_id = $this->em->getRepository(CategoryRepository::class)->insertCategory($insert);
        $this->em->getRepository(CategoryRepository::class)->updateCategory(['uppercats' => $uppercats_prefix . $inserted_id], $inserted_id);

        $this->updateGlobalRank();

        if ($insert['status'] === 'private' && !empty($insert['id_uppercat']) && ((isset($options['inherit']) && $options['inherit']) || $this->conf['inheritance_by_default'])) {
            $result = $this->em->getRepository(GroupAccessRepository::class)->findFieldByCatId($insert['id_uppercat'], 'group_id');
            $granted_grps = $this->em->getConnection()->result2array($result, null, 'group_id');
            $inserts = [];
            foreach ($granted_grps as $granted_grp) {
                $inserts[] = ['group_id' => $granted_grp, 'cat_id' => $inserted_id];
            }
            $this->em->getRepository(GroupAccessRepository::class)->massInserts(['group_id', 'cat_id'], $inserts);

            $result = $this->em->getRepository(UserAccessRepository::class)->findByCatId($insert['id_uppercat']);
            $granted_users = $this->em->getConnection()->result2array($result, null, 'user_id');
            $this->addPermissionOnCategory($inserted_id, array_unique(array_merge(\Phyxo\Functions\Utils::get_admins(), [$user_id], $granted_users)));
        } elseif ($insert['status'] === 'private') {
            $this->addPermissionOnCategory($inserted_id, array_unique(array_merge(\Phyxo\Functions\Utils::get_admins(), [$user_id])));
        }

        return [
            'info' => \Phyxo\Functions\Language::l10n('Virtual album added'),
            'id' => $inserted_id,
        ];
    }

    /**
     * Verifies that the representative picture really exists in the db and
     * picks up a random representative if possible and based on config.
     *
     * @param 'all'|int|int[] $ids
     */
    public function updateCategory($ids = 'all')
    {
        if ($ids === 'all') {
            $where_cats = '1 = 1';
        } elseif (!is_array($ids)) {
            $where_cats = '%s=' . $ids;
        } else {
            if (count($ids) === 0) {
                return false;
            }
            $where_cats = '%s ' . $this->em->getConnection()->in($ids);
        }

        // find all categories where the setted representative is not possible : the picture does not exist
        $result = $this->em->getRepository(CategoryRepository::class)->findWrongRepresentant($where_cats);
        $wrong_representant = $this->em->getConnection()->result2array($result, null, 'id');

        if (count($wrong_representant) > 0) {
            $this->em->getRepository(CategoryRepository::class)->updateCategories(['representative_picture_id' => null], $wrong_representant);
        }

        if (!$this->conf['allow_random_representative']) {
            // If the random representant is not allowed, we need to find
            // categories with elements and with no representant. Those categories
            // must be added to the list of categories to set to a random
            // representant.
            $result = $this->em->getRepository(CategoryRepository::class)->findRandomRepresentant($where_cats);
            $to_rand = $this->em->getConnection()->result2array($result, null, 'id');
            if (count($to_rand) > 0) {
                $this->setRandomRepresentant($to_rand);
            }
        }
    }

    /**
     * Associate a list of images to a list of categories.
     * The function will not duplicate links and will preserve ranks.
     *
     * @param int[] $images
     * @param int[] $categories
     */
    public function associateImagesToCategories($images, $categories)
    {
        if (count($images) === 0 || count($categories) === 0) {
            return false;
        }

        // get existing associations
        $result = $this->em->getRepository(ImageCategoryRepository::class)->findAll($images, $categories);

        $existing = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $existing[$row['category_id']][] = $row['image_id'];
        }

        // get max rank of each categories
        $result = $this->em->getRepository(ImageCategoryRepository::class)->findMaxRankForEachCategories($categories);
        $current_rank_of = $this->em->getConnection()->result2array($result, 'category_id', 'max_rank');

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
            $this->em->getRepository(ImageCategoryRepository::class)->insertImageCategories(
                array_keys($inserts[0]),
                $inserts
            );

            $this->updateCategory($categories);
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
    public function moveImagesToCategories(array $images, array $categories)
    {
        if (count($images) === 0) {
            return false;
        }

        $result = $this->em->getRepository(ImageRepository::class)->findWithNoStorageOrStorageCategoryId($categories);
        $cat_ids = $this->em->getConnection()->result2array($result, null, 'id');

        // let's first break links with all old albums but their "storage album"
        $this->em->getRepository(ImageCategoryRepository::class)->deleteByCategory($cat_ids, $images);

        if (is_array($categories) && count($categories) > 0) {
            $this->associateImagesToCategories($images, $categories);
        }
    }

    /**
     * Associate images associated to a list of source categories to a list of
     * destination categories.
     *
     * @param int[] $sources
     * @param int[] $destinations
     */
    public function associateCategoriesToCategories(array $sources, array $destinations)
    {
        if (count($sources) === 0) {
            return false;
        }

        $result = $this->em->getRepository(ImageCategoryRepository::class)->findAll($sources);
        $images = $this->em->getConnection()->result2array($result, null, 'image_id');

        $this->associateImagesToCategories($images, $destinations);
    }

    /**
     * Change the **visible** property on a set of categories.
     *
     * @param int[] $categories
     * @param boolean $unlock_child optional   default false
     */
    public function setCatVisible(array $categories, bool $value, $unlock_child = false)
    {
        // unlocking a category => all its parent categories become unlocked
        if ($value) {
            $cats = $this->getUppercatIds($categories);
            if ($unlock_child) {
                $cats = array_merge($cats, $this->em->getRepository(CategoryRepository::class)->getSubcatIds($categories));
            }

            $this->em->getRepository(CategoryRepository::class)->updateCategories(['visible' => true], $cats);
        } else { // locking a category   => all its child categories become locked
            $subcats = $this->em->getRepository(CategoryRepository::class)->getSubcatIds($categories);
            $this->em->getRepository(CategoryRepository::class)->updateCategories(['visible' => false], $subcats);
        }
    }

    /**
     * Returns the fulldir for each given category id.
     *
     * @param int[] intcat_ids
     * @return string[]
     */
    public function getFulldirs(array $cat_ids): array
    {
        if (count($cat_ids) == 0) {
            return [];
        }

        // caching directories of existing categories
        $result = $this->em->getRepository(CategoryRepository::class)->findByDir('IS NOT NULL');
        $cat_dirs = $this->em->getConnection()->result2array($result, 'id', 'dir');

        // caching galleries_url
        $result = $this->em->getRepository(SiteRepository::class)->findAll();
        $galleries_url = $this->em->getConnection()->result2array($result, 'id', 'galleries_url');

        // categories : id, site_id, uppercats
        $result = $this->em->getRepository(CategoryRepository::class)->findByIdsAndDir($cat_ids, 'IS NOT NULL');
        $categories = $this->em->getConnection()->result2array($result);

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
     * Orders categories (update categories.rank and global_rank database fields)
     * so that rank field are consecutive integers starting at 1 for each child.
     */
    public function updateGlobalRank()
    {
        $cat_map = [];
        $current_rank = 0;
        $current_uppercat = '';

        $result = $this->em->getRepository(CategoryRepository::class)->findAll('id_uppercat, rank, name');
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            if ($row['id_uppercat'] != $current_uppercat) {
                $current_rank = 0;
                $current_uppercat = $row['id_uppercat'];
            }
            ++$current_rank;
            $cat = [
                'rank' => $current_rank,
                'rank_changed' => $current_rank != $row['rank'],
                'global_rank' => $row['global_rank'],
                'uppercats' => $row['uppercats'],
            ];
            $cat_map[$row['id']] = $cat;
        }

        $datas = [];

        $cat_map_callback = function ($m) use ($cat_map) {
            return $cat_map[$m[1]]['rank'];
        };

        foreach ($cat_map as $id => $cat) {
            $new_global_rank = preg_replace_callback(
                '/(\d+)/',
                $cat_map_callback,
                str_replace(',', '.', $cat['uppercats'])
            );

            if ($cat['rank_changed'] || $new_global_rank != $cat['global_rank']) {
                $datas[] = [
                    'id' => $id,
                    'rank' => $cat['rank'],
                    'global_rank' => $new_global_rank,
                ];
            }
        }

        unset($cat_map);

        $this->em->getRepository(CategoryRepository::class)->massUpdatesCategories(
            [
                'primary' => ['id'],
                'update' => ['rank', 'global_rank']
            ],
            $datas
        );

        return count($datas);
    }

    /**
     * save the rank depending on given categories order
     *
     * The list of ordered categories id is supposed to be in the same parent
     * category
     */
    public function saveCategoriesOrder(array $categories)
    {
        $current_rank_for_id_uppercat = [];
        $current_rank = 0;

        $datas = [];
        foreach ($categories as $category) {
            if (is_array($category)) {
                $id = $category['id'];
                $id_uppercat = $category['id_uppercat'];

                if (!isset($current_rank_for_id_uppercat[$id_uppercat])) {
                    $current_rank_for_id_uppercat[$id_uppercat] = 0;
                }
                $current_rank = ++$current_rank_for_id_uppercat[$id_uppercat];
            } else {
                $id = $category;
                $current_rank++;
            }

            $datas[] = ['id' => $id, 'rank' => $current_rank];
        }
        $fields = ['primary' => ['id'], 'update' => ['rank']];
        $this->em->getRepository(CategoryRepository::class)->massUpdatesCategories($fields, $datas);

        $this->updateGlobalRank();
    }

    public function getCategoriesRefDate(array $ids, string $field = 'date_available', string $minmax = 'max')
    {
        // we need to work on the whole tree under each category, even if we don't want to sort sub categories
        $category_ids = $this->em->getRepository(CategoryRepository::class)->getSubcatIds($ids);

        // search for the reference date of each album
        $result = $this->em->getRepository(ImageRepository::class)->getReferenceDateForCategories($field, $minmax, $category_ids);
        $ref_dates = $this->em->getConnection()->result2array($result, 'category_id', 'ref_date');

        // the iterate on all albums (having a ref_date or not) to find the
        // reference_date, with a search on sub-albums
        $result = $this->em->getRepository(CategoryRepository::class)->findByIds($category_ids);
        $uppercats_of = $this->em->getConnection()->result2array($result, 'id', 'uppercats');

        foreach (array_keys($uppercats_of) as $cat_id) {
            // find the subcats
            $subcat_ids = [];

            foreach ($uppercats_of as $id => $uppercats) {
                if (preg_match('/(^|,)' . $cat_id . '(,|$)/', $uppercats)) {
                    $subcat_ids[] = $id;
                }
            }

            $to_compare = [];
            foreach ($subcat_ids as $id) {
                if (isset($ref_dates[$id])) {
                    $to_compare[] = $ref_dates[$id];
                }
            }

            if (count($to_compare) > 0) {
                $ref_dates[$cat_id] = 'max' == $minmax ? max($to_compare) : min($to_compare);
            } else {
                $ref_dates[$cat_id] = null;
            }
        }

        // only return the list of $ids, not the sub-categories
        $return = [];
        foreach ($ids as $id) {
            $return[$id] = $ref_dates[$id];
        }

        return $return;
    }

    /**
     * Set a new random representant to the categories.
     */
    public function setRandomRepresentant(array $categories)
    {
        $datas = [];
        foreach ($categories as $category_id) {
            $result = $this->em->getRepository(ImageCategoryRepository::class)->findRandomRepresentant($category_id);
            list($representative) = $this->em->getConnection()->db_fetch_row($result);

            $datas[] = [
                'id' => $category_id,
                'representative_picture_id' => $representative,
            ];
        }

        $this->em->getRepository(CategoryRepository::class)->massUpdatesCategories(
            [
                'primary' => ['id'],
                'update' => ['representative_picture_id']
            ],
            $datas
        );
    }
}
