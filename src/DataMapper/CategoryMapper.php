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

class CategoryMapper
{
    private $em, $conf;

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
            if ($this->conf['index_new_icon']) {
                $row['icon_ts'] = \Phyxo\Functions\Utils::get_icon($row['max_date_last'], $child_date_last);
            }
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
}
