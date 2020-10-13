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
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Image\ImageStandardParams;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use Phyxo\Image\SrcImage;
use App\Repository\UserCacheCategoriesRepository;
use App\DataMapper\CategoryMapper;
use App\Repository\BaseRepository;
use App\DataMapper\ImageMapper;
use Phyxo\Functions\Utils;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumController extends CommonController
{
    public function album(Request $request, Conf $conf, ImageStandardParams $image_std_params, MenuBar $menuBar,
                            EntityManager $em, ImageMapper $imageMapper, CategoryMapper $categoryMapper, int $start = 0, int $category_id = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        if (in_array($category_id, $this->getUser()->getForbiddenCategories())) {
            throw new AccessDeniedHttpException("Access denied to that album");
        }

        $category = $categoryMapper->getCatInfo($category_id);

        $tpl_params['TITLE'] = $categoryMapper->getBreadcrumb($category['upper_names']);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

        $preferred_image_orders = [
            [$translator->trans('Default'), '', true],
            [$translator->trans('Photo title, A &rarr; Z'), 'name ASC', true],
            [$translator->trans('Photo title, Z &rarr; A'), 'name DESC', true],
            [$translator->trans('Date created, new &rarr; old'), 'date_creation DESC', true],
            [$translator->trans('Date created, old &rarr; new'), 'date_creation ASC', true],
            [$translator->trans('Date posted, new &rarr; old'), 'date_available DESC', true],
            [$translator->trans('Date posted, old &rarr; new'), 'date_available ASC', true],
            [$translator->trans('Rating score, high &rarr; low'), 'rating_score DESC', $conf['rate']],
            [$translator->trans('Rating score, low &rarr; high'), 'rating_score ASC', $conf['rate']],
            [$translator->trans('Visits, high &rarr; low'), 'hit DESC', true],
            [$translator->trans('Visits, low &rarr; high'), 'hit ASC', true],
            [$translator->trans('Permissions'), 'level DESC', $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'), true],
        ];

        $order_index = 0;
        if ($this->get('session')->has('image_order_index')) {
            $order_index = $this->get('session')->get('image_order_index');
        }
        if ($request->get('order')) {
            $order_index = (int) $request->get('order');
            $this->get('session')->set('image_order_index', $order_index);
        }
        $order_by = $conf['order_by'];
        $tpl_params['image_orders'] = [];
        foreach ($preferred_image_orders as $order_id => $order) {
            if ($order[2] === true) {
                $tpl_params['image_orders'][] = [
                    'DISPLAY' => $order[0],
                    'URL' => $this->generateUrl('album', ['category_id' => $category['id'], 'start' => $start, 'order' => $order_id]),
                    'SELECTED' => false
                ];
            }
        }
        $tpl_params['image_orders'][$order_index]['SELECTED'] = true;
        if ($preferred_image_orders[$order_index][1] !== '') {
            $order_by = str_replace('ORDER BY ', 'ORDER BY ' . $preferred_image_orders[$order_index][1] . ',', $order_by);
        }

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
            $tpl_thumbnails_var = [];
            foreach ($categories as $category) {
                if ($category['count_images'] === 0) {
                    continue;
                }

                $name = $categoryMapper->getCatDisplayNameCache($category['uppercats']);

                $representative_infos = $infos_of_image[$category['representative_picture_id']];

                $tpl_var = array_merge($category, [
                    'ID' => $category['id'] /*obsolete*/,
                    'representative' => $representative_infos,
                    'TN_ALT' => strip_tags($category['name']),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle($category, $category['name'], $category['comment']),
                    'URL' => $this->generateUrl('album', ['category_id' => $category['id'], 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $categoryMapper->getDisplayImagesCount($category['nb_images'], $category['count_images'], $category['count_categories'], true, '<br>'),
                    'DESCRIPTION' => isset($category['comment']) ? $category['comment'] : '',
                    'NAME' => $name,
                ]);

                if ($conf['index_new_icon']) {
                    $tpl_var['icon_ts'] = $em->getRepository(BaseRepository::class)->getIcon($category['max_date_last'], $this->getUser(), $category['is_child_date_last']);
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

            $derivative_params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

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
        $result = $em->getRepository(ImageRepository::class)->searchDistinctId('image_id', [$where_sql . ' ' . $forbidden], true, $order_by);
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

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params['U_HOME'] = $this->generateUrl('homepage');
        $tpl_params['SHOW_THUMBNAIL_CAPTION'] = $conf['show_thumbnail_caption'];
        $tpl_params['U_MODE_POSTED'] = $this->generateUrl('calendar_category_monthly', ['date_type' => 'posted', 'view_type' => 'calendar', 'category_id' => $category_id]);
        $tpl_params['U_MODE_CREATED'] = $this->generateUrl('calendar_category_monthly', ['date_type' => 'created', 'view_type' => 'calendar', 'category_id' => $category_id]);
        $tpl_params['START_ID'] = $start;
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function albumFlat(Request $request, EntityManager $em, Conf $conf, MenuBar $menuBar,
                        ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, ImageMapper $imageMapper, int $category_id, int $start = 0, TranslatorInterface $translator)
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

        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());
        $tpl_params['START_ID'] = $start;

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function albumsFlat(Request $request, EntityManager $em, Conf $conf, MenuBar $menuBar,
        ImageStandardParams $image_std_params, ImageMapper $imageMapper, int $start = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

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

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());
        $tpl_params['START_ID'] = $start;

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function albums(Request $request, EntityManager $em, Conf $conf, MenuBar $menuBar,
                            ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, ImageMapper $imageMapper, int $start = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

        $order = 'rank';
        $filter = [];
        $where[] = $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_categories' => 'id'], '', $force_on_condition = true);
        $where[] = 'id_uppercat IS NULL';

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
            $tpl_thumbnails_var = [];
            foreach ($categories as $category) {
                if ($category['count_images'] === 0) {
                    continue;
                }

                $name = $categoryMapper->getCatDisplayNameCache($category['uppercats']);

                $representative_infos = isset($infos_of_image[$category['representative_picture_id']]) ? $infos_of_image[$category['representative_picture_id']] : [];

                $tpl_var = array_merge($category, [
                    'ID' => $category['id'] /*obsolete*/,
                    'representative' => $representative_infos,
                    'TN_ALT' => strip_tags($category['name']),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle($category, $category['name'], $category['comment']),
                    'URL' => $this->generateUrl($start > 0 ? 'album__start' : 'album', ['category_id' => $category['id'], 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $categoryMapper->getDisplayImagesCount($category['nb_images'], $category['count_images'], $category['count_categories'], true, '<br>'),
                    'DESCRIPTION' => isset($category['comment']) ? $category['comment'] : '',
                    'NAME' => $name,
                ]);

                if ($conf['index_new_icon'] && !empty($category['max_date_last'])) { // @FIX : cf BUGS
                    $tpl_var['icon_ts'] = $em->getRepository(BaseRepository::class)->getIcon($category['max_date_last'], $this->getUser(), $category['is_child_date_last']);
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

            $derivative_params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

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

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('mainpage_categories.html.twig', $tpl_params);
    }

    public function recentCats(Request $request, EntityManager $em, Conf $conf, MenuBar $menuBar,
                                ImageStandardParams $image_std_params, ImageMapper $imageMapper, CategoryMapper $categoryMapper, int $start = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Recent albums');

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
            $tpl_thumbnails_var = [];
            foreach ($categories as $category) {
                if ($category['count_images'] === 0) {
                    continue;
                }

                $name = $categoryMapper->getCatDisplayNameCache($category['uppercats']);

                $representative_infos = $infos_of_image[$category['representative_picture_id']];

                $tpl_var = array_merge($category, [
                    'ID' => $category['id'] /*obsolete*/,
                    'representative' => $representative_infos,
                    'TN_ALT' => strip_tags($category['name']),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle($category, $category['name'], $category['comment']),
                    'URL' => $this->generateUrl('album', ['category_id' => $category['id'], 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $categoryMapper->getDisplayImagesCount($category['nb_images'], $category['count_images'], $category['count_categories'], true, '<br>'),
                    'DESCRIPTION' => isset($category['comment']) ? $category['comment'] : '',
                    'NAME' => $name,
                ]);

                if ($conf['index_new_icon']) {
                    $tpl_var['icon_ts'] = $em->getRepository(BaseRepository::class)->getIcon($category['max_date_last'], $this->getUser(), $category['is_child_date_last']);
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

            $derivative_params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

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

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('mainpage_categories.html.twig', $tpl_params);
    }
}
