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
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use App\Repository\FavoriteRepository;
use App\DataMapper\ImageMapper;
use App\Entity\Favorite;
use App\Repository\ImageRepository;
use App\Security\AppUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FavoriteController extends AbstractController
{
    public function index(
        Request $request,
        Conf $conf,
        FavoriteRepository $favoriteRepository,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        RouterInterface $router,
        AppUserService $appUserService,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['TITLE'] = $translator->trans('Your favorites');

        $tpl_params['items'] = [];
        foreach ($favoriteRepository->findUserFavorites($appUserService->getUser()->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums()) as $favorite) {
            $tpl_params['items'][] = $favorite->getImage()->getId();
        }

        if ($tpl_params['items'] !== []) {
            $tpl_params['favorite'] = ['U_FAVORITE' => $this->generateUrl('remove_all_favorites')];

            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    '',
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    'favorites',
                    $start
                )
            );

            if ((is_countable($tpl_params['items']) ? count($tpl_params['items']) : 0) > $appUserService->getUser()->getUserInfos()->getNbImagePage()) {
                $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                    $router,
                    'favorites',
                    [],
                    is_countable($tpl_params['items']) ? count($tpl_params['items']) : 0,
                    $start,
                    $appUserService->getUser()->getUserInfos()->getNbImagePage(),
                    $conf['paginate_pages_around']
                );
            }
        }

        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function add(
        int $image_id,
        ImageRepository $imageRepository,
        FavoriteRepository $favoriteRepository,
        AppUserService $appUserService,
        Request $request,
        TranslatorInterface $translator
    ): Response {
        $image = $imageRepository->find($image_id);
        $favorite = new Favorite();
        $favorite->setImage($image);
        $favorite->setUser($appUserService->getUser());
        $favoriteRepository->addOrUpdateFavorite($favorite);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'status' => 'ok',
                    'href' => $this->generateUrl('remove_from_favorites', ['image_id' => $image_id]),
                    'title' => $translator->trans('delete this photo from your favorites'),
                ]
            );
        }

        return $this->redirectToRoute('favorites');
    }

    public function remove(int $image_id, FavoriteRepository $favoriteRepository, AppUserService $appUserService, Request $request, TranslatorInterface $translator): Response
    {
        $favoriteRepository->deleteUserFavorite($appUserService->getUser()->getId(), $image_id);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'status' => 'ok',
                    'href' => $this->generateUrl('add_to_favorites', ['image_id' => $image_id]),
                    'title' => $translator->trans('add this photo to your favorites'),
                ]
            );
        }

        return $this->redirectToRoute('favorites');
    }

    public function removeAll(FavoriteRepository $favoriteRepository, AppUserService $appUserService): Response
    {
        $favoriteRepository->deleteAllUserFavorites($appUserService->getUser()->getId());

        return $this->redirectToRoute('favorites');
    }
}
