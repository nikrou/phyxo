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
use Phyxo\DBLayer\iDBLayer;
use Phyxo\EntityManager;
use Phyxo\Conf;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\RouterInterface;

class CategoryMapper
{
    private $conn, $em, $conf, $user;

    public function __construct(iDBLayer $conn, Conf $conf, EntityManager $em, TokenStorageInterface $tokenStorage, RouterInterface $router)
    {
        $this->conn = $conn;
        $this->conf = $conf;
        $this->em = $em;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->router = $router;
    }

    /**
     * Returns template vars for main categories menu.
     *
     */
    public function getRecursiveCategoriesMenu(array $selected_category = []): array
    {
        $flat_categories = $this->getCategoriesMenu($selected_category);

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
    protected function getCategoriesMenu(array $selected_category = []): array
    {
        $result = $this->em->getRepository(CategoryRepository::class)->getCategoriesForMenu(
            $this->user,
            false, //$filter['enabled'], ?? is it usefull ?
            isset($selected_category['uppercats']) ? explode(',', $selected_category['uppercats']) : []
        );

        $cats = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
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
}
