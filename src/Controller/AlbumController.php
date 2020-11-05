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

use App\DataMapper\AlbumMapper;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\EntityManager;
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Image\ImageStandardParams;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use Phyxo\Image\SrcImage;
use App\DataMapper\CategoryMapper;
use App\Repository\BaseRepository;
use App\DataMapper\ImageMapper;
use App\Entity\Album;
use App\Repository\ImageAlbumRepository;
use App\Repository\UserCacheAlbumRepository;
use Phyxo\Functions\Utils;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumController extends CommonController
{
    public function album(Request $request, Conf $conf, ImageStandardParams $image_std_params, MenuBar $menuBar, UserCacheAlbumRepository $userCacheAlbumRepository,
                            EntityManager $em, ImageMapper $imageMapper, CategoryMapper $categoryMapper, int $start = 0, int $category_id = 0, TranslatorInterface $translator,
                            AlbumMapper $albumMapper)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $album = $albumMapper->getRepository()->find($category_id);

        if (in_array($category_id, $this->getUser()->getForbiddenCategories())) {
            throw new AccessDeniedHttpException("Access denied to that album");
        }

        $tpl_params['TITLE'] = $albumMapper->getBreadcrumb($album);
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
                    'URL' => $this->generateUrl('album', ['category_id' => $album->getId(), 'start' => $start, 'order' => $order_id]),
                    'SELECTED' => false
                ];
            }
        }
        $tpl_params['image_orders'][$order_index]['SELECTED'] = true;
        if ($preferred_image_orders[$order_index][1] !== '') {
            $order_by = str_replace('ORDER BY ', 'ORDER BY ' . $preferred_image_orders[$order_index][1] . ',', $order_by);
        }

        $albums = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        list($is_child_date_last, $albums, $image_ids) = $albumMapper->getAlbumThumbnails($this->getUser(), $albumMapper->getRepository()->findByParentId($category_id, $this->getUser()->getId()));

        if (count($albums) > 0) {
            $infos_of_images = $albumMapper->getInfosOfImages($this->getUser(), $albums, $image_ids, $imageMapper);
        }

        if (count($user_representative_updates_for) > 0) {
            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $userCacheAlbumRepository->updateUserRepresentativePicture($this->getUser()->getId(), $cat_id, $image_id);
            }
        }

        if (count($albums) > 0) {
            $tpl_thumbnails_var = [];

            foreach ($albums as $album) {
                if ($album->getUserCacheAlbum()->getCountImages() === 0) {
                    continue;
                }

                $name = $albumMapper->getAlbumsDisplayNameCache($album->getUppercats());

                $representative_infos = $infos_of_images[$album->getRepresentativePictureId()];

                $tpl_var = [
                    'id' => $album->getId(),
                    'representative' => $representative_infos,
                    'TN_ALT' => $album->getName(),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle(['rating_score' => '', 'nb_comments' => ''], $album->getName(), $album->getComment()),
                    'URL' => $this->generateUrl('album', ['category_id' => $album->getId(), 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $albumMapper->getDisplayImagesCount(
                        $album->getUserCacheAlbum()->getNbImages(), $album->getUserCacheAlbum()->getCountImages(),
                        $album->getUserCacheAlbum()->getCountAlbums(), true, '<br>'
                    ),
                    'DESCRIPTION' => $album->getComment() ?? '',
                    'NAME' => $name,
                    'name' => $album->getName(),
                ];

                if ($conf['index_new_icon']) {
                    $tpl_var['icon_ts'] = $em->getRepository(BaseRepository::class)->getIcon(
                        $album->getUserCacheAlbum()->getMaxDateLast()->format('Y-m-d H:m:i'),
                        $this->getUser(),
                        $is_child_date_last
                    );
                }

                if ($conf['display_fromto']) {
                    if (isset($dates_of_category[$album->getId()])) {
                        $from = $dates_of_category[$album->getId()]['_from'];
                        $to = $dates_of_category[$album->getId()]['_to'];

                        if (!empty($from)) {
                            $tpl_var['INFO_DATES'] = \Phyxo\Functions\DateTime::format_fromto($from, $to);
                        }
                    }
                }

                $tpl_thumbnails_var[] = $tpl_var;
            }

            // pagination
            $total_albums = count($tpl_thumbnails_var);

            // @TODO : a category can contain more than $conf['nb_categories_page']
            $tpl_thumbnails_var_selection = array_slice($tpl_thumbnails_var, 0, $conf['nb_categories_page']);

            $derivative_params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);

            $tpl_params['maxRequests'] = $conf['max_requests'];
            $tpl_params['category_thumbnails'] = $tpl_thumbnails_var_selection;
            $tpl_params['derivative_album_params'] = $derivative_params;
            $tpl_params['derivative_params'] = $derivative_params;
            $tpl_params['image_std_params'] = $image_std_params;

            // navigation bar
            if ($total_albums > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'albums',
                    [],
                    $total_albums,
                    $start,
                    $conf['nb_categories_page'],
                    $conf['paginate_pages_around']
                );
            }
        }

        $forbidden = $em->getRepository(BaseRepository::class)->getSQLConditionFandF(
            $this->getUser(),
            [],
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

    public function albums(Request $request, EntityManager $em, Conf $conf, MenuBar $menuBar, UserCacheAlbumRepository $userCacheAlbumRepository,
                            ImageStandardParams $image_std_params, ImageMapper $imageMapper, int $start = 0, TranslatorInterface $translator,
                            AlbumMapper $albumMapper, ImageAlbumRepository $imageAlbumRepository)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

        $albums = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        list($is_child_date_last, $albums, $image_ids) = $albumMapper->getAlbumThumbnails(
            $this->getUser(),
            $albumMapper->getRepository()->findParentAlbums($this->getUser()->getId())
        );

        if ($conf['display_fromto']) {
            if (count($albums) > 0) {
                $dates_of_category = [];
                foreach ($imageAlbumRepository->dateOfAlbums(array_keys($albums)) as $image) {
                    $dates_of_category[] = $image;
                }
            }
        }

        if (count($albums) > 0) {
            $infos_of_images = $albumMapper->getInfosOfImages($this->getUser(), $albums, $image_ids, $imageMapper);
        }

        if (count($user_representative_updates_for) > 0) {
            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $userCacheAlbumRepository->updateUserRepresentativePicture($this->getUser()->getId(), $cat_id, $image_id);
            }
        }

        if (count($albums) > 0) {
            $tpl_thumbnails_var = [];
            foreach ($albums as $album) {
                if (!$album->getUserCacheAlbum() || $album->getUserCacheAlbum()->getCountImages() === 0) {
                    continue;
                }

                $name = $albumMapper->getAlbumsDisplayNameCache($album->getUppercats());
                $representative_infos = null;
                if (isset($infos_of_images[$album->getRepresentativePictureId()])) {
                    $representative_infos = $infos_of_images[$album->getRepresentativePictureId()];
                }

                $tpl_var = [
                    'id' => $album->getId(),
                    'representative' => $representative_infos,
                    'TN_ALT' => $album->getName(),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle(['rating_score' => '', 'nb_comments' => ''], $album->getName(), $album->getComment()),
                    'URL' => $this->generateUrl('album', ['category_id' => $album->getId(), 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $albumMapper->getDisplayImagesCount(
                        $album->getUserCacheAlbum()->getNbImages(), $album->getUserCacheAlbum()->getCountImages(),
                        $album->getUserCacheAlbum()->getCountAlbums(), true, '<br>'
                    ),
                    'DESCRIPTION' => $album->getComment() ?? '',
                    'NAME' => $name,
                    'name' => $album->getName(),
                ];

                if ($conf['index_new_icon']) {
                    $tpl_var['icon_ts'] = $em->getRepository(BaseRepository::class)->getIcon(
                        $album->getUserCacheAlbum()->getMaxDateLast()->format('Y-m-d H:m:i'),
                        $this->getUser(), $is_child_date_last
                    );
                }

                if ($conf['display_fromto']) {
                    if (isset($dates_of_category[$album->getId()])) {
                        $from = $dates_of_category[$album->getId()]['_from'];
                        $to = $dates_of_category[$album->getId()]['_to'];

                        if (!empty($from)) {
                            $tpl_var['INFO_DATES'] = \Phyxo\Functions\DateTime::format_fromto($from, $to);
                        }
                    }
                }

                $tpl_thumbnails_var[] = $tpl_var;
            }

            // pagination
            $total_albums = count($tpl_thumbnails_var);

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
            if ($total_albums > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'albums',
                    [],
                    $total_albums,
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

    public function recentCats(Request $request, EntityManager $em, Conf $conf, MenuBar $menuBar, UserCacheAlbumRepository $userCacheAlbumRepository,
                                ImageStandardParams $image_std_params, ImageMapper $imageMapper, CategoryMapper $categoryMapper, int $start = 0, TranslatorInterface $translator,
                                AlbumMapper $albumMapper)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Recent albums');

        $albums = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        $recent_date = new \DateTime();
        $recent_date->sub(new \DateInterval(sprintf('P%dD', $this->getUser()->getRecentPeriod())));

        list($is_child_date_last, $albums, $image_ids) = $albumMapper->getAlbumThumbnails($this->getUser(), $albumMapper->getRepository()->findRecentAlbums($recent_date));

        if ($conf['display_fromto']) {
            if (count($albums) > 0) {
                $dates_of_category = [];
                foreach ($imageMapper->getRepository()->dateOfCategories(array_keys($albums)) as $image) {
                    $dates_of_category[] = $image;
                }
            }
        }

        if (count($albums) > 0) {
            $infos_of_images = $albumMapper->getInfosOfImages($this->getUser, $albums, $image_ids, $imageMapper);
        }

        if (count($user_representative_updates_for) > 0) {
            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $userCacheAlbumRepository->updateUserRepresentativePicture($this->getUser()->getId(), $cat_id, $image_id);
            }
        }

        if (count($albums) > 0) {
            $tpl_thumbnails_var = [];
            foreach ($albums as $album) {
                if (!$album->getUserCacheAlbum() || $album->getUserCacheAlbum()->getCountImages() === 0) {
                    continue;
                }

                $name = $albumMapper->getAlbumsDisplayNameCache($album->getUppercats());

                $representative_infos = $infos_of_images[$album->getRepresentativePictureId()];

                $tpl_var = [
                    'id' => $album->getId(),
                    'representative' => $representative_infos,
                    'TN_ALT' => $album->getName(),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle(['rating_score' => '', 'nb_comments' => ''], $album->getName(), $album->getComment()),
                    'URL' => $this->generateUrl('album', ['category_id' => $album->getId(), 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $albumMapper->getDisplayImagesCount(
                        $album->getUserCacheAlbum()->getNbImages(), $album->getUserCacheAlbum()->getCountImages(),
                        $album->getUserCacheAlbum()->getCountAlbums(), true, '<br>'
                    ),
                    'DESCRIPTION' => $album->getComment() ?? '',
                    'NAME' => $name,
                    'name' => $album->getName(),
                ];

                if ($conf['index_new_icon']) {
                    $tpl_var['icon_ts'] = $em->getRepository(BaseRepository::class)->getIcon(
                        $album->getUserCacheAlbum()->getMaxDateLast()->format('Y-m-d H:m:i'),
                        $this->getUser(), $is_child_date_last
                    );
                }

                if ($conf['display_fromto']) {
                    if (isset($dates_of_category[$album->getId()])) {
                        $from = $dates_of_category[$album->getId()]['_from'];
                        $to = $dates_of_category[$album->getId()]['_to'];

                        if (!empty($from)) {
                            $tpl_var['INFO_DATES'] = \Phyxo\Functions\DateTime::format_fromto($from, $to);
                        }
                    }
                }

                $tpl_thumbnails_var[] = $tpl_var;
            }

            // pagination
            $total_albums = count($tpl_thumbnails_var);

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
            if ($total_albums > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'recent_cats',
                    [],
                    $total_albums,
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
