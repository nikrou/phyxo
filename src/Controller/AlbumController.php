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

use DateTime;
use DateInterval;
use App\DataMapper\AlbumMapper;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use App\DataMapper\ImageMapper;
use App\Enum\PictureSectionType;
use App\Repository\UserCacheAlbumRepository;
use App\Security\AppUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumController extends AbstractController
{
    use ThumbnailsControllerTrait;

    public function album(
        Request $request,
        Conf $conf,
        UserCacheAlbumRepository $userCacheAlbumRepository,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        AlbumMapper $albumMapper,
        Security $security,
        AppUserService $appUserService,
        RouterInterface $router,
        int $start = 0,
        int $album_id = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $album = $albumMapper->getRepository()->find($album_id);

        if (in_array($album_id, $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
            throw new AccessDeniedHttpException("Access denied to that album");
        }

        $tpl_params['TITLE'] = $albumMapper->getBreadcrumb($album);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

        $preferred_image_orders = [
            [$translator->trans('Default'), '[]', true],
            [$translator->trans('Photo title, A &rarr; Z'), '[["name", "ASC"]]', true],
            [$translator->trans('Photo title, Z &rarr; A'), '["name", "DESC"]]', true],
            [$translator->trans('Date created, new &rarr; old'), '[["date_creation", "DESC"]]', true],
            [$translator->trans('Date created, old &rarr; new'), '[["date_creation", "ASC"]]', true],
            [$translator->trans('Date posted, new &rarr; old'), '[["date_available", "DESC"]]', true],
            [$translator->trans('Date posted, old &rarr; new'), '[["date_available", "ASC"]]', true],
            [$translator->trans('Rating score, high &rarr; low'), '[["rating_score", "DESC"]]', $conf['rate']],
            [$translator->trans('Rating score, low &rarr; high'), '[["rating_score", "ASC"]]', $conf['rate']],
            [$translator->trans('Visits, high &rarr; low'), '[["hit", "DESC"]]', true],
            [$translator->trans('Visits, low &rarr; high'), '[["hit", "ASC"]]', true],
            [$translator->trans('Permissions'), '[["level", "DESC"]]', $security->isGranted('ROLE_ADMIN'), true],
        ];

        $order_index = 0;
        if ($request->getSession()->has('image_order_index')) {
            $order_index = $request->getSession()->get('image_order_index');
        }

        if ($request->get('order')) {
            $order_index = (int) $request->get('order');
            $request->getSession()->set('image_order_index', $order_index);
        }

        $order_by = $conf['order_by'];
        $tpl_params['image_orders'] = [];
        foreach ($preferred_image_orders as $order_id => $order) {
            if ($order[2] === true) {
                $tpl_params['image_orders'][] = [
                    'DISPLAY' => $order[0],
                    'URL' => $this->generateUrl('album', ['album_id' => $album->getId(), 'start' => $start, 'order' => $order_id]),
                    'SELECTED' => false
                ];
            }
        }

        $tpl_params['image_orders'][$order_index]['SELECTED'] = true;
        if ($preferred_image_orders[$order_index][1] !== '[]') {
            $order_by = array_merge(json_decode($preferred_image_orders[$order_index][1], true, 512, JSON_THROW_ON_ERROR), $order_by);
        }

        $albums = [];
        $image_ids = [];
        $user_representative_updates_for = [];
        $infos_of_images = [];

        [$albums, $image_ids, $user_representative_updates_for] = $albumMapper->getAlbumThumbnails(
            $appUserService->getUser(),
            $albumMapper->getRepository()->findByParentId($appUserService->getUser()->getId(), $album_id)
        );

        if (count($albums) > 0) {
            $infos_of_images = $albumMapper->getInfosOfImages($appUserService->getUser(), $albums, $image_ids, $imageMapper);
        }

        if (count($user_representative_updates_for) > 0) {
            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $userCacheAlbumRepository->updateUserRepresentativePicture($appUserService->getUser()->getId(), $cat_id, $image_id);
            }
        }

        if (count($albums) > 0) {
            $tpl_thumbnails_var = [];

            foreach ($albums as $currentAlbum) {
                $userCacheAlbum = $currentAlbum->getUserCacheAlbum();
                $name = $albumMapper->getAlbumsDisplayNameCache($currentAlbum->getUppercats());

                $representative_infos = $infos_of_images[$currentAlbum->getRepresentativePictureId()];

                $tpl_var = [
                    'id' => $currentAlbum->getId(),
                    'representative' => $representative_infos,
                    'TN_ALT' => $currentAlbum->getName(),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle(['rating_score' => '', 'nb_comments' => ''], $currentAlbum->getName(), $currentAlbum->getComment()),
                    'URL' => $this->generateUrl('album', ['album_id' => $currentAlbum->getId(), 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $albumMapper->getDisplayImagesCount(
                        $userCacheAlbum->getNbImages(),
                        $userCacheAlbum->getCountImages(),
                        $userCacheAlbum->getCountAlbums(),
                        true,
                        '<br>'
                    ),
                    'DESCRIPTION' => $currentAlbum->getComment() ?? '',
                    'NAME' => $name,
                    'name' => $currentAlbum->getName(),
                    'icon_ts' => '',
                ];

                $tpl_thumbnails_var[] = $tpl_var;
            }

            // pagination
            $total_albums = count($tpl_thumbnails_var);
            $tpl_thumbnails_var_selection = array_slice($tpl_thumbnails_var, 0, $conf['nb_categories_page']);
            $tpl_params['category_thumbnails'] = $tpl_thumbnails_var_selection;

            // navigation bar
            if ($total_albums > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = $this->defineNavigation(
                    $router,
                    'albums',
                    [],
                    $total_albums,
                    $start,
                    $conf['nb_categories_page'],
                    $conf['paginate_pages_around']
                );
            }
        }

        $tpl_params['items'] = [];
        foreach ($imageMapper->getRepository()->searchDistinctIdInAlbum($album->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $order_by) as $image) {
            $tpl_params['items'][] = $image->getId();
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            if (count($tpl_params['items']) > $nb_image_page) {
                $tpl_params['thumb_navbar'] = $this->defineNavigation(
                    $router,
                    'album',
                    ['album_id' => $album_id],
                    count($tpl_params['items']),
                    $start,
                    $nb_image_page,
                    $conf['paginate_pages_around']
                );
            }

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    $album_id,
                    PictureSectionType::ALBUM,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );
        }

        $tpl_params['SHOW_THUMBNAIL_CAPTION'] = $conf['show_thumbnail_caption'];
        $tpl_params['U_MODE_POSTED'] = $this->generateUrl('calendar', ['date_type' => 'posted', 'album_id' => $album_id]);
        $tpl_params['U_MODE_CREATED'] = $this->generateUrl('calendar', ['date_type' => 'created', 'album_id' => $album_id]);
        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function albumFlat(
        Request $request,
        Conf $conf,
        AlbumMapper $albumMapper,
        ImageMapper $imageMapper,
        int $album_id,
        TranslatorInterface $translator,
        RouterInterface $router,
        AppUserService $appUserService,
        int $start = 0
    ): Response {
        $subcat_ids = [];
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $album = $albumMapper->getRepository()->find($album_id);
        $subcat_ids[] = $album->getId();
        foreach ($albumMapper->getRepository()->findAllowedSubAlbums($album->getUppercats(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums()) as $sub_album_id) {
            $subcat_ids[] = $sub_album_id;
        }

        $tpl_params['items'] = [];
        foreach ($imageMapper->getRepository()->searchDistinctIdInAlbum($album->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $conf['order_by']) as $image) {
            $tpl_params['items'][] = $image['id'];
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            if (count($tpl_params['items']) > $nb_image_page) {
                $tpl_params['thumb_navbar'] = $this->$this->defineNavigation(
                    $router,
                    'album_flat',
                    ['album_id' => $album_id],
                    count($tpl_params['items']),
                    $start,
                    $nb_image_page,
                    $conf['paginate_pages_around']
                );
            }

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    $album_id,
                    PictureSectionType::ALBUM,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');
        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function albumsFlat(
        Request $request,
        Conf $conf,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        RouterInterface $router,
        AppUserService $appUserService,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

        $tpl_params['items'] = [];
        foreach ($imageMapper->getRepository()->searchDistinctId($appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $conf['order_by']) as $image) {
            $tpl_params['items'][] = $image['id'];
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            if (count($tpl_params['items']) > $nb_image_page) {
                $tpl_params['thumb_navbar'] = $this->defineNavigation(
                    $router,
                    'albums_flat',
                    [],
                    count($tpl_params['items']),
                    $start,
                    $nb_image_page,
                    $conf['paginate_pages_around']
                );
            }

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    'flat',
                    PictureSectionType::ALBUMS,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function albums(
        Request $request,
        Conf $conf,
        UserCacheAlbumRepository $userCacheAlbumRepository,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        AlbumMapper $albumMapper,
        RouterInterface $router,
        AppUserService $appUserService,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Albums');

        $albums = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        [$albums, $image_ids, $user_representative_updates_for] = $albumMapper->getAlbumThumbnails(
            $appUserService->getUser(),
            $albumMapper->getRepository()->findParentAlbums($appUserService->getUser()->getId())
        );

        if (count($albums) > 0) {
            $infos_of_images = $albumMapper->getInfosOfImages($appUserService->getUser(), $albums, $image_ids, $imageMapper);
        }

        if (count($user_representative_updates_for) > 0) {
            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $userCacheAlbumRepository->updateUserRepresentativePicture($appUserService->getUser()->getId(), $cat_id, $image_id);
            }
        }

        if (count($albums) > 0) {
            $tpl_thumbnails_var = [];
            foreach ($albums as $album) {
                $userCacheAlbum = $album->getUserCacheAlbum();
                $name = $albumMapper->getAlbumsDisplayNameCache($album->getUppercats());

                $representative_infos = $infos_of_images[$album->getRepresentativePictureId()];

                $tpl_var = array_merge(
                    $album->toArray(),
                    [
                        'representative' => $representative_infos,
                        'TN_ALT' => $album->getName(),
                        'TN_TITLE' => $imageMapper->getThumbnailTitle(['rating_score' => '', 'nb_comments' => ''], $album->getName(), $album->getComment()),
                        'URL' => $this->generateUrl('album', ['album_id' => $album->getId(), 'start' => $start]),
                        'CAPTION_NB_IMAGES' => $albumMapper->getDisplayImagesCount(
                            $userCacheAlbum->getNbImages() ?? 0,
                            $userCacheAlbum->getCountImages() ?? 0,
                            $userCacheAlbum->getCountAlbums() ?? 0,
                            true,
                            '<br>'
                        ),
                        'DESCRIPTION' => $album->getComment() ?? '',
                        'NAME' => $name,
                        'name' => $album->getName(),
                        'icon_ts' => '',
                    ]
                );

                $tpl_thumbnails_var[] = $tpl_var;
            }

            // pagination
            $total_albums = count($tpl_thumbnails_var);

            $tpl_thumbnails_var_selection = array_slice(
                $tpl_thumbnails_var,
                $start,
                $conf['nb_categories_page']
            );

            $tpl_params['category_thumbnails'] = $tpl_thumbnails_var_selection;

            // navigation bar
            if ($total_albums > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = $this->defineNavigation(
                    $router,
                    'albums',
                    [],
                    $total_albums,
                    $start,
                    $conf['nb_categories_page'],
                    $conf['paginate_pages_around']
                );
            }
        }

        return $this->render('mainpage_categories.html.twig', $tpl_params);
    }

    public function recentCats(
        Request $request,
        Conf $conf,
        UserCacheAlbumRepository $userCacheAlbumRepository,
        ImageStandardParams $image_std_params,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        AlbumMapper $albumMapper,
        RouterInterface $router,
        AppUserService $appUserService,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Recent albums');

        $albums = [];
        $image_ids = [];
        $user_representative_updates_for = [];

        $recent_date = new DateTime();
        $recent_date->sub(new DateInterval(sprintf('P%dD', $appUserService->getUser()->getUserInfos()->getRecentPeriod())));

        $infos_of_images = [];

        [$albums, $image_ids, $user_representative_updates_for] = $albumMapper->getAlbumThumbnails($appUserService->getUser(), $albumMapper->getRepository()->findRecentAlbums($recent_date));

        if (count($albums) > 0) {
            $infos_of_images = $albumMapper->getInfosOfImages($appUserService->getUser(), $albums, $image_ids, $imageMapper);
        }

        if (count($user_representative_updates_for) > 0) {
            foreach ($user_representative_updates_for as $cat_id => $image_id) {
                $userCacheAlbumRepository->updateUserRepresentativePicture($appUserService->getUser()->getId(), $cat_id, $image_id);
            }
        }

        if (count($albums) > 0) {
            $tpl_thumbnails_var = [];
            foreach ($albums as $album) {
                $userCacheAlbum = $album->getUserCacheAlbum();
                $name = $albumMapper->getAlbumsDisplayNameCache($album->getUppercats());

                $representative_infos = $infos_of_images[$album->getRepresentativePictureId()];

                $tpl_var = [
                    'id' => $album->getId(),
                    'representative' => $representative_infos,
                    'TN_ALT' => $album->getName(),
                    'TN_TITLE' => $imageMapper->getThumbnailTitle(['rating_score' => '', 'nb_comments' => ''], $album->getName(), $album->getComment()),
                    'URL' => $this->generateUrl('album', ['album_id' => $album->getId(), 'start' => $start]),
                    'CAPTION_NB_IMAGES' => $albumMapper->getDisplayImagesCount(
                        $userCacheAlbum->getNbImages(),
                        $userCacheAlbum->getCountImages(),
                        $userCacheAlbum->getCountAlbums(),
                        true,
                        '<br>'
                    ),
                    'DESCRIPTION' => $album->getComment() ?? '',
                    'NAME' => $name,
                    'name' => $album->getName(),
                    'icon_ts' => '',
                ];

                $tpl_thumbnails_var[] = $tpl_var;
            }

            // pagination
            $total_albums = count($tpl_thumbnails_var);

            $tpl_thumbnails_var_selection = array_slice(
                $tpl_thumbnails_var,
                $start,
                $conf['nb_categories_page']
            );

            $tpl_params['category_thumbnails'] = $tpl_thumbnails_var_selection;

            // navigation bar
            if ($total_albums > $conf['nb_categories_page']) {
                $tpl_params['cats_navbar'] = $this->defineNavigation(
                    $router,
                    'recent_cats',
                    [],
                    $total_albums,
                    $start,
                    $conf['nb_categories_page'],
                    $conf['paginate_pages_around']
                );
            }
        }

        return $this->render('mainpage_categories.html.twig', $tpl_params);
    }
}
