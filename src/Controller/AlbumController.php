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

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Phyxo\EntityManager;
use Phyxo\Template\Template;
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Image\ImageStandardParams;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use Phyxo\Image\SrcImage;
use App\Repository\UserCacheCategoriesRepository;
use App\DataMapper\CategoryMapper;
use Phyxo\Functions\Language;
use App\Repository\BaseRepository;
use App\DataMapper\ImageMapper;
use Phyxo\Functions\Category;
use Phyxo\Functions\Utils;

class AlbumController extends CommonController
{
    public function album(Request $request, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, ImageStandardParams $image_std_params, MenuBar $menuBar,
                            EntityManager $em, ImageMapper $imageMapper, CategoryMapper $categoryMapper, int $start = 0, int $category_id = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $category = $categoryMapper->getCatInfo($category_id);

        $tpl_params['PAGE_TITLE'] = Language::l10n('Albums');
        $tpl_params['TITLE'] = $categoryMapper->getCatDisplayName($category['upper_names'], '', false);

        $order = 'rank';
        $filter = [];
        $where[] = 'id_uppercat = ' . $category_id;
        $where[] = $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_categories' => 'id'], '', $force_on_condition = true);

        $result = $em->getRepository(CategoryRepository::class)->findWithUserAndCondition($this->getUser()->getId(), $where, $order);
        $categories = [];
        $category_ids = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            // TODO remove arobases ; need tests ?
            $row['is_child_date_last'] = @$row['max_date_last'] > @$row['date_last'];

            if (!empty($row['user_representative_picture_id'])) {
                $image_id = $row['user_representative_picture_id'];
            } elseif (!empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
                $image_id = $row['representative_picture_id'];
            } elseif ($conf['allow_random_representative']) { // searching a random representant among elements in sub-categories
                $image_id = $em->getRepository(CategoryRepository::class)->getRandomImageInCategory($this->getUser(), $filter, $row);
            } elseif ($row['count_categories'] > 0 and $row['count_images'] > 0) { // searching a random representant among representant of sub-categories
                $result = $em->getRepository(CategoryRepository::class)->findRandomRepresentantAmongSubCategories($this->getUser(), $filter, $row['uppercats']);
                if ($em->getConnection()->db_num_rows($result) > 0) {
                    list($image_id) = $em->getConnection()->db_fetch_row($result);
                }
            }

            if (isset($image_id)) {
                if ($conf['representative_cache_on_subcats'] && $row['user_representative_picture_id'] != $image_id) {
                    $user_representative_updates_for[$row['id']] = $image_id;
                }

                $row['representative_picture_id'] = $image_id;
                $image_ids[] = $image_id;
                $categories[] = $row;
                $category_ids[] = $row['id'];
            }
            unset($image_id);
        }

        usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');

        if (count($categories) > 0) {
            $infos_of_image = [];
            $new_image_ids = [];

            $result = $em->getRepository(ImageRepository::class)->findByIds($image_ids);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                if ($row['level'] <= $this->getUser()->getLevel()) {
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
                            $image_id = $em->getRepository(CategoryRepository::class)->getRandomImageInCategory($this->getUser(), $filter, $category);

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
                $result = $em->getRepository(ImageRepository::class)->findByIds($new_image_ids);
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $infos_of_image[$row['id']] = $row;
                }
            }

            foreach ($infos_of_image as &$info) {
                $info['src_image'] = new SrcImage($info, $conf['picture_ext']);
            }
            unset($info);
        }

        if (count($user_representative_updates_for) > 0) {
            $updates = [];

            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $updates[] = [
                    'user_id' => $this->getUser()->getId(),
                    'cat_id' => $cat_id,
                    'user_representative_picture_id' => $image_id,
                ];
            }

            $em->getRepository(UserCacheCategoriesRepository::class)->massUpdatesUserCacheCategories(
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

            \Phyxo\Functions\Plugin::trigger_notify('loc_begin_index_category_thumbnails', $categories);

            $tpl_thumbnails_var = [];
            foreach ($categories as $category) {
                if ($category['count_images'] === 0) {
                    continue;
                }

                $category['name'] = \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $category['name'],
                    'subcatify_category_name'
                );

                $name = $categoryMapper->getCatDisplayNameCache($category['uppercats']);

                $representative_infos = $infos_of_image[$category['representative_picture_id']];

                $tpl_var = array_merge($category, [
                    'ID' => $category['id'] /*obsolete*/,
                    'representative' => $representative_infos,
                    'TN_ALT' => strip_tags($category['name']),
                    'TN_TITLE' => Utils::get_thumbnail_title($category, $category['name'], $category['comment']),
                    'URL' => $this->generateUrl('album', ['category_id' => $category['id'], 'start' => $start]),
                    'CAPTION_NB_IMAGES' => Category::get_display_images_count(
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
                    $tpl_var['icon_ts'] = $imageMapper->getIcon($category['max_date_last'], $category['is_child_date_last']);
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
            $total_categories = count($tpl_thumbnails_var);

            // @TODO : a category can contain more than $conf['nb_categories_page']
            $tpl_thumbnails_var_selection = array_slice($tpl_thumbnails_var, 0, $conf['nb_categories_page']);

            $derivative_params = \Phyxo\Functions\Plugin::trigger_change('get_index_album_derivative_params', $image_std_params->getByType(ImageStandardParams::IMG_THUMB));
            $tpl_thumbnails_var_selection = \Phyxo\Functions\Plugin::trigger_change('loc_end_index_category_thumbnails', $tpl_thumbnails_var_selection);

            $tpl_params['maxRequests'] = $conf['max_requests'];
            $tpl_params['category_thumbnails'] = $tpl_thumbnails_var_selection;
            $tpl_params['derivative_album_params'] = $derivative_params;
            $tpl_params['derivative_params'] = $derivative_params;
            $tpl_params['image_std_params'] = $image_std_params;

            // navigation bar
            if ($total_categories > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'albums',
                    [],
                    $total_categories,
                    $start,
                    $conf['nb_categories_page'],
                    $conf['paginate_pages_around']
                );
            }
        }

        $forbidden = $em->getRepository(BaseRepository::class)->getSQLConditionFandF(
            $this->getUser(),
            $filter,
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'id'
            ],
            'AND'
        );

        $where_sql = 'category_id = ' . $category_id;
        $result = $em->getRepository(ImageRepository::class)->searchDistinctId('image_id', [$where_sql . ' ' . $forbidden], true, $conf['order_by']);
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'image_id');

        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'album',
                ['category_id' => $category_id],
                count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    array_slice($tpl_params['items'], 0, $nb_image_page),
                    $category_id,
                    'category',
                    $start
                )
            );
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params['U_MODE_POSTED'] = $this->generateUrl('calendar_category_monthly', ['date_type' => 'posted', 'view_type' => 'calendar', 'category_id' => $category_id]);
        $tpl_params['U_MODE_CREATED'] = $this->generateUrl('calendar_category_monthly', ['date_type' => 'created', 'view_type' => 'calendar', 'category_id' => $category_id]);
        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.tpl', $tpl_params);
    }

    public function albumFlat(Request $request, EntityManager $em, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
                                ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, ImageMapper $imageMapper, int $category_id, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $category = $categoryMapper->getCatInfo($category_id);

        $filter = [];
        $result = $em->getRepository(CategoryRepository::class)->findAllowedSubCategories($this->getUser(), $filter, $category['uppercats']);
        $subcat_ids = $em->getConnection()->result2array($result, null, 'id');
        $subcat_ids[] = $category['id'];
        $where_sql = 'category_id ' . $em->getConnection()->in($subcat_ids);
        // remove categories from forbidden because just checked above
        $forbidden = $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_images' => 'id'], 'AND');

        $result = $em->getRepository(ImageRepository::class)->searchDistinctId('image_id', [$where_sql . ' ' . $forbidden], true, $conf['order_by']);
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'image_id');

        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'album_flat',
                ['category_id' => $category_id],
                count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $category_id,
                    'category',
                    $start
                )
            );
        }

        $tpl_params['PAGE_TITLE'] = Language::l10n('Albums');

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());
        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.tpl', $tpl_params);
    }

    public function albumsFlat(Request $request, EntityManager $em, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
        ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, ImageMapper $imageMapper, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = Language::l10n('Albums');

        $filter = [];

        $where[] = 'id_uppercat IS NULL';
        $where[] = $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_categories' => 'id'], '', $force_on_condition = true);

        // remove categories from forbidden because just checked above
        $forbidden = $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_images' => 'id'], 'AND');

        $result = $em->getRepository(ImageRepository::class)->searchDistinctId('image_id', [$forbidden], true, $conf['order_by']);
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'image_id');


        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'albums_flat',
                [],
                count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    'flat',
                    'categories',
                    $start
                )
            );
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());
        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.tpl', $tpl_params);
    }

    public function albums(Request $request, EntityManager $em, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
                            ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, ImageMapper $imageMapper, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = Language::l10n('Albums');

        $order = 'rank';
        $filter = [];
        $where[] = $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_categories' => 'id'], '', $force_on_condition = true);

        $result = $em->getRepository(CategoryRepository::class)->findWithUserAndCondition($this->getUser()->getId(), $where, $order);
        $categories = [];
        $category_ids = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            // TODO remove arobases ; need tests ?
            $row['is_child_date_last'] = @$row['max_date_last'] > @$row['date_last'];

            if (!empty($row['user_representative_picture_id'])) {
                $image_id = $row['user_representative_picture_id'];
            } elseif (!empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
                $image_id = $row['representative_picture_id'];
            } elseif ($conf['allow_random_representative']) { // searching a random representant among elements in sub-categories
                $image_id = $em->getRepository(CategoryRepository::class)->getRandomImageInCategory($this->getUser(), $filter, $row);
            } elseif ($row['count_categories'] > 0 and $row['count_images'] > 0) { // searching a random representant among representant of sub-categories
                $result = $em->getRepository(CategoryRepository::class)->findRandomRepresentantAmongSubCategories($this->getUser(), $filter, $row['uppercats']);
                if ($em->getConnection()->db_num_rows($result) > 0) {
                    list($image_id) = $em->getConnection()->db_fetch_row($result);
                }
            }

            if (isset($image_id)) {
                if ($conf['representative_cache_on_subcats'] && $row['user_representative_picture_id'] != $image_id) {
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
                $result = $em->getRepository(ImageCategoryRepository::class)->dateOfCategories($this->getUser(), $filter, $category_ids);
                $dates_of_category = $em->getConnection()->result2array($result, 'category_id');
            }
        }

        usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');

        if (count($categories) > 0) {
            $infos_of_image = [];
            $new_image_ids = [];

            $result = $em->getRepository(ImageRepository::class)->findByIds($image_ids);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                if ($row['level'] <= $this->getUser()->getLevel()) {
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
                            $image_id = $em->getRepository(CategoryRepository::class)->getRandomImageInCategory($this->getUser(), $filter, $category);

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
                $result = $em->getRepository(ImageRepository::class)->findByIds($new_image_ids);
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $infos_of_image[$row['id']] = $row;
                }
            }

            foreach ($infos_of_image as &$info) {
                $info['src_image'] = new SrcImage($info, $conf['picture_ext']);
            }
            unset($info);
        }

        if (count($user_representative_updates_for)) {
            $updates = [];

            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $updates[] = [
                    'user_id' => $this->getUser()->getId(),
                    'cat_id' => $cat_id,
                    'user_representative_picture_id' => $image_id,
                ];
            }

            $em->getRepository(UserCacheCategoriesRepository::class)->massUpdatesUserCacheCategories(
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

            \Phyxo\Functions\Plugin::trigger_notify('loc_begin_index_category_thumbnails', $categories);

            $tpl_thumbnails_var = [];
            foreach ($categories as $category) {
                if ($category['count_images'] === 0) {
                    continue;
                }

                $category['name'] = \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $category['name'],
                    'subcatify_category_name'
                );

                $name = $categoryMapper->getCatDisplayNameCache($category['uppercats']);

                $representative_infos = $infos_of_image[$category['representative_picture_id']];

                $tpl_var = array_merge($category, [
                    'ID' => $category['id'] /*obsolete*/,
                    'representative' => $representative_infos,
                    'TN_ALT' => strip_tags($category['name']),
                    'TN_TITLE' => Utils::get_thumbnail_title($category, $category['name'], $category['comment']),
                    'URL' => $this->generateUrl($start > 0 ? 'album__start' : 'album', ['category_id' => $category['id'], 'start' => $start]),
                    'CAPTION_NB_IMAGES' => Category::get_display_images_count(
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
                    $tpl_var['icon_ts'] = $imageMapper->getIcon($category['max_date_last'], $category['is_child_date_last']);
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
            $total_categories = count($tpl_thumbnails_var);

            $tpl_thumbnails_var_selection = array_slice(
                $tpl_thumbnails_var,
                $start,
                $conf['nb_categories_page']
            );

            $derivative_params = \Phyxo\Functions\Plugin::trigger_change('get_index_album_derivative_params', $image_std_params->getByType(ImageStandardParams::IMG_THUMB));
            $tpl_thumbnails_var_selection = \Phyxo\Functions\Plugin::trigger_change('loc_end_index_category_thumbnails', $tpl_thumbnails_var_selection);

            $tpl_params['maxRequests'] = $conf['max_requests'];
            $tpl_params['category_thumbnails'] = $tpl_thumbnails_var_selection;
            $tpl_params['derivative_album_params'] = $derivative_params;
            $tpl_params['derivative_params'] = $derivative_params;
            $tpl_params['image_std_params'] = $image_std_params;

            // navigation bar
            if ($total_categories > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'albums',
                    [],
                    $total_categories,
                    $start,
                    $conf['nb_categories_page'],
                    $conf['paginate_pages_around']
                );

            }
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('mainpage_categories.tpl', $tpl_params);
    }

    public function recentCats(Request $request, EntityManager $em, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
                                ImageStandardParams $image_std_params, ImageMapper $imageMapper, CategoryMapper $categoryMapper, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = Language::l10n('Recent albums');

        $order = '';
        $filter = [];
        $where[] = $em->getRepository(BaseRepository::class)->getRecentPhotos($this->getUser(), 'date_last');
        $where[] = $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_categories' => 'id'], '', $force_on_condition = true);

        $result = $em->getRepository(CategoryRepository::class)->findWithUserAndCondition($this->getUser()->getId(), $where, $order);
        $categories = [];
        $category_ids = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            // TODO remove arobases ; need tests ?
            $row['is_child_date_last'] = @$row['max_date_last'] > @$row['date_last'];

            if (!empty($row['user_representative_picture_id'])) {
                $image_id = $row['user_representative_picture_id'];
            } elseif (!empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
                $image_id = $row['representative_picture_id'];
            } elseif ($conf['allow_random_representative']) { // searching a random representant among elements in sub-categories
                $image_id = $em->getRepository(CategoryRepository::class)->getRandomImageInCategory($this->getUser(), $filter, $row);
            } elseif ($row['count_categories'] > 0 and $row['count_images'] > 0) { // searching a random representant among representant of sub-categories
                $result = $em->getRepository(CategoryRepository::class)->findRandomRepresentantAmongSubCategories($this->getUser(), $filter, $row['uppercats']);
                if ($em->getConnection()->db_num_rows($result) > 0) {
                    list($image_id) = $em->getConnection()->db_fetch_row($result);
                }
            }

            if (isset($image_id)) {
                if ($conf['representative_cache_on_subcats'] && $row['user_representative_picture_id'] != $image_id) {
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
                $result = $em->getRepository(ImageCategoryRepository::class)->dateOfCategories($this->getUser(), $filter, $category_ids);
                $dates_of_category = $em->getConnection()->result2array($result, 'category_id');
            }
        }

        usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');

        if (count($categories) > 0) {
            $infos_of_image = [];
            $new_image_ids = [];

            $result = $em->getRepository(ImageRepository::class)->findByIds($image_ids);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                if ($row['level'] <= $this->getUser()->getLevel()) {
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
                            $image_id = $em->getRepository(CategoryRepository::class)->getRandomImageInCategory($this->getUser(), $filter, $category);

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
                $result = $em->getRepository(ImageRepository::class)->findByIds($new_image_ids);
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $infos_of_image[$row['id']] = $row;
                }
            }

            foreach ($infos_of_image as &$info) {
                $info['src_image'] = new SrcImage($info, $conf['picture_ext']);
            }
            unset($info);
        }

        if (count($user_representative_updates_for)) {
            $updates = [];

            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $updates[] = [
                    'user_id' => $this->getUser()->getId(),
                    'cat_id' => $cat_id,
                    'user_representative_picture_id' => $image_id,
                ];
            }

            $em->getRepository(UserCacheCategoriesRepository::class)->massUpdatesUserCacheCategories(
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

            \Phyxo\Functions\Plugin::trigger_notify('loc_begin_index_category_thumbnails', $categories);

            $tpl_thumbnails_var = [];
            foreach ($categories as $category) {
                if ($category['count_images'] === 0) {
                    continue;
                }

                $category['name'] = \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $category['name'],
                    'subcatify_category_name'
                );

                $name = $categoryMapper->getCatDisplayNameCache($category['uppercats']);

                $representative_infos = $infos_of_image[$category['representative_picture_id']];

                $tpl_var = array_merge($category, [
                    'ID' => $category['id'] /*obsolete*/,
                    'representative' => $representative_infos,
                    'TN_ALT' => strip_tags($category['name']),
                    'TN_TITLE' => Utils::get_thumbnail_title($category, $category['name'], $category['comment']),
                    'URL' => $this->generateUrl('album', ['category_id' => $category['id'], 'start' => $start]),
                    'CAPTION_NB_IMAGES' => Category::get_display_images_count(
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
                    $tpl_var['icon_ts'] = $imageMapper->getIcon($category['max_date_last'], $category['is_child_date_last']);
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
            $total_categories = count($tpl_thumbnails_var);

            $tpl_thumbnails_var_selection = array_slice(
                $tpl_thumbnails_var,
                $start,
                $conf['nb_categories_page']
            );

            $derivative_params = \Phyxo\Functions\Plugin::trigger_change('get_index_album_derivative_params', $image_std_params->getByType(ImageStandardParams::IMG_THUMB));
            $tpl_thumbnails_var_selection = \Phyxo\Functions\Plugin::trigger_change('loc_end_index_category_thumbnails', $tpl_thumbnails_var_selection);

            $tpl_params['maxRequests'] = $conf['max_requests'];
            $tpl_params['category_thumbnails'] = $tpl_thumbnails_var_selection;
            $tpl_params['derivative_album_params'] = $derivative_params;
            $tpl_params['derivative_params'] = $derivative_params;
            $tpl_params['image_std_params'] = $image_std_params;

            // navigation bar
            if ($total_categories > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'recent_cats',
                    [],
                    $total_categories,
                    $start,
                    $conf['nb_categories_page'],
                    $conf['paginate_pages_around']
                );

            }
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('mainpage_categories.tpl', $tpl_params);
    }
}
