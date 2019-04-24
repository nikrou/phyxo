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

use App\Repository\CategoryRepository;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\BaseRepository;

/**
 * This file is included by the main page to show subcategories of a category
 * or to show recent categories or main page categories list
 *
 */

// $user['forbidden_categories'] including with USER_CACHE_CATEGORIES_TABLE
$order = '';
$where = [];
if ('recent_cats' == $page['section']) {
    $where[] = \Phyxo\Functions\SQL::get_recent_photos('date_last');
} else {
    $where[] = 'id_uppercat ' . (!isset($page['category']) ? 'is NULL' : '=' . $page['category']['id']);
}

$where[] = (new BaseRepository($conn))->getSQLConditionFandF($app_user, $filter, ['visible_categories' => 'id'], '', $force_on_condition = true);

if ('recent_cats' != $page['section']) {
    $order = 'rank';
}

$result = (new CategoryRepository($conn))->findWithUserAndCondition($user['id'], $where, $order);
$categories = [];
$category_ids = [];
$image_ids = [];
$user_representative_updates_for = [];

while ($row = $conn->db_fetch_assoc($result)) {
    // TODO remove arobases ; need tests ?
    $row['is_child_date_last'] = @$row['max_date_last'] > @$row['date_last'];

    if (!empty($row['user_representative_picture_id'])) {
        $image_id = $row['user_representative_picture_id'];
    } elseif (!empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
        $image_id = $row['representative_picture_id'];
    } elseif ($conf['allow_random_representative']) { // searching a random representant among elements in sub-categories
        $image_id = (new CategoryRepository($conn))->getRandomImageInCategory($app_user, $filter, $row);
    } elseif ($row['count_categories'] > 0 and $row['count_images'] > 0) { // searching a random representant among representant of sub-categories
        $result = (new CategoryRepository($conn))->findRandomRepresentantAmongSubCategories($app_user, $filter, $row['uppercats']);
        if ($conn->db_num_rows($result) > 0) {
            list($image_id) = $conn->db_fetch_row($result);
        }
    }

    if (isset($image_id)) {
        if ($conf['representative_cache_on_subcats'] and $row['user_representative_picture_id'] != $image_id) {
            $user_representative_updates_for[$row['id']] = $image_id;
        }

        $row['representative_picture_id'] = $image_id;
        $image_ids[] = $image_id;
        $categories[] = $row;
        $category_ids[] = $row['id'];
    }
    unset($image_id);
}

if ($conf['display_fromto']) {
    if (count($category_ids) > 0) {
        $result = (new ImageCategoryRepository($conn))->dateOfCategories($app_user, $filter, $category_ids);
        $dates_of_category = $conn->result2array($result, 'category_id');
    }
}

if ($page['section'] == 'recent_cats') {
    usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');
}

if (count($categories) > 0) {
    $infos_of_image = [];
    $new_image_ids = [];

    $result = (new ImageRepository($conn))->findByIds($image_ids);
    while ($row = $conn->db_fetch_assoc($result)) {
        if ($row['level'] <= $user['level']) {
            $infos_of_image[$row['id']] = $row;
        } else {
            // problem: we must not display the thumbnail of a photo which has a
            // higher privacy level than user privacy level
            //
            // * what is the represented category?
            // * find a random photo matching user permissions
            // * register it at user_representative_picture_id
            // * set it as the representative_picture_id for the category

            foreach ($categories as &$category) {
                if ($row['id'] == $category['representative_picture_id']) {
                    // searching a random representant among elements in sub-categories
                    $image_id = (new CategoryRepository($conn))->getRandomImageInCategory($app_user, $filter, $category);

                    if (isset($image_id) and !in_array($image_id, $image_ids)) {
                        $new_image_ids[] = $image_id;
                    }

                    if ($conf['representative_cache_on_level']) {
                        $user_representative_updates_for[$category['id']] = $image_id;
                    }

                    $category['representative_picture_id'] = $image_id;
                }
            }
            unset($category);
        }
    }

    if (count($new_image_ids) > 0) {
        $result = (new ImageRepository($conn))->findByIds($new_image_ids);
        while ($row = $conn->db_fetch_assoc($result)) {
            $infos_of_image[$row['id']] = $row;
        }
    }

    foreach ($infos_of_image as &$info) {
        $info['src_image'] = new \Phyxo\Image\SrcImage($info);
    }
    unset($info);
}

if (count($user_representative_updates_for)) {
    $updates = [];

    foreach ($user_representative_updates_for as $cat_id => $image_id) {
        $updates[] = [
            'user_id' => $user['id'],
            'cat_id' => $cat_id,
            'user_representative_picture_id' => $image_id,
        ];
    }

    (new UserCacheCategoriesRepository($conn))->massUpdatesUserCacheCategories(
        [
            'primary' => ['user_id', 'cat_id'],
            'update' => ['user_representative_picture_id']
        ],
        $updates
    );
}

if (count($categories) > 0) {
    // Update filtered data
    \Phyxo\Functions\Category::update_cats_with_filtered_data($categories);

    $template->set_filename('index_category_thumbnails', 'mainpage_categories.tpl');

    \Phyxo\Functions\Plugin::trigger_notify('loc_begin_index_category_thumbnails', $categories);

    $tpl_thumbnails_var = [];

    foreach ($categories as $category) {
        if (0 == $category['count_images']) {
            continue;
        }

        $category['name'] = \Phyxo\Functions\Plugin::trigger_change(
            'render_category_name',
            $category['name'],
            'subcatify_category_name'
        );

        if ($page['section'] == 'recent_cats') {
            $name = \Phyxo\Functions\Category::get_cat_display_name_cache($category['uppercats'], null);
        } else {
            $name = $category['name'];
        }

        $representative_infos = $infos_of_image[$category['representative_picture_id']];

        $tpl_var = array_merge($category, [
            'ID' => $category['id'] /*obsolete*/,
            'representative' => $representative_infos,
            'TN_ALT' => strip_tags($category['name']),
            'TN_TITLE' => \Phyxo\Functions\Utils::get_thumbnail_title($category, $category['name'], $category['comment']),
            'URL' => \Phyxo\Functions\URL::make_index_url(['category' => $category]),
            'CAPTION_NB_IMAGES' => \Phyxo\Functions\Category::get_display_images_count(
                $category['nb_images'],
                $category['count_images'],
                $category['count_categories'],
                true,
                '<br>'
            ),
            'DESCRIPTION' => \Phyxo\Functions\Plugin::trigger_change(
                'render_category_literal_description',
                \Phyxo\Functions\Plugin::trigger_change('render_category_description', @$category['comment'], 'subcatify_category_description')
            ),
            'NAME' => $name,
        ]);
        if ($conf['index_new_icon']) {
            $tpl_var['icon_ts'] = \Phyxo\Functions\Utils::get_icon($category['max_date_last'], $category['is_child_date_last']);
        }

        if ($conf['display_fromto']) {
            if (isset($dates_of_category[$category['id']])) {
                $from = $dates_of_category[$category['id']]['_from'];
                $to = $dates_of_category[$category['id']]['_to'];

                if (!empty($from)) {
                    $tpl_var['INFO_DATES'] = \Phyxo\Functions\DateTime::format_fromto($from, $to);
                }
            }
        }

        $tpl_thumbnails_var[] = $tpl_var;
    }

    // pagination
    $page['total_categories'] = count($tpl_thumbnails_var);

    $tpl_thumbnails_var_selection = array_slice(
        $tpl_thumbnails_var,
        $page['startcat'],
        $conf['nb_categories_page']
    );

    $derivative_params = \Phyxo\Functions\Plugin::trigger_change('get_index_album_derivative_params', \Phyxo\Image\ImageStdParams::get_by_type(IMG_THUMB));
    $tpl_thumbnails_var_selection = \Phyxo\Functions\Plugin::trigger_change('loc_end_index_category_thumbnails', $tpl_thumbnails_var_selection);
    $template->assign([
        'maxRequests' => $conf['max_requests'],
        'category_thumbnails' => $tpl_thumbnails_var_selection,
        'derivative_album_params' => $derivative_params,
        'derivative_params' => $derivative_params,
    ]);

    // navigation bar
    $page['cats_navigation_bar'] = [];
    if ($page['total_categories'] > $conf['nb_categories_page']) {
        $page['cats_navigation_bar'] = \Phyxo\Functions\Utils::create_navigation_bar(
            \Phyxo\Functions\URL::duplicate_index_url([], ['startcat']),
            $page['total_categories'],
            $page['startcat'],
            $conf['nb_categories_page'],
            true,
            'startcat'
        );
    }

    $template->assign('cats_navbar', $page['cats_navigation_bar']);
}
