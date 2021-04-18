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
use Phyxo\MenuBar;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use App\Repository\FavoriteRepository;
use App\DataMapper\ImageMapper;
use App\Entity\Favorite;
use App\Repository\ImageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class FavoriteController extends CommonController
{
    public function index(Request $request, int $start = 0, MenuBar $menuBar, Conf $conf,
                          FavoriteRepository $favoriteRepository, ImageMapper $imageMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['TITLE'] = $translator->trans('Your favorites');

        $tpl_params['items'] = [];
        foreach ($favoriteRepository->findUserFavorites($this->getUser()->getId(), $this->getUser()->getUserInfos()->getForbiddenCategories()) as $favorite) {
            $tpl_params['items'][] = $favorite->getImage()->getId();
        }

        if (count($tpl_params['items']) > 0) {
            $tpl_params['favorite'] = ['U_FAVORITE' => $this->generateUrl('remove_all_favorites')];

            $nb_image_page = $this->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    '',
                    'favorites',
                    $start
                )
            );

            if (count($tpl_params['items']) > $this->getUser()->getUserInfos()->getNbImagePage()) {
                $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'favorites',
                    [],
                    count($tpl_params['items']),
                    $start,
                    $this->getUser()->getUserInfos()->getNbImagePage(),
                    $conf['paginate_pages_around']
                );
            }
        }

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));
        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function add(int $image_id, ImageRepository $imageRepository, FavoriteRepository $favoriteRepository, Request $request, TranslatorInterface $translator)
    {
        $image = $imageRepository->find($image_id);
        $favorite = new Favorite();
        $favorite->setImage($image);
        $favorite->setUser($this->getUser());
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

    public function remove(int $image_id, FavoriteRepository $favoriteRepository, Request $request, TranslatorInterface $translator)
    {
        $favoriteRepository->deleteUserFavorite($this->getUser()->getId(), $image_id);

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

    public function removeAll(FavoriteRepository $favoriteRepository)
    {
        $favoriteRepository->deleteAllUserFavorites($this->getUser()->getId());

        return $this->redirectToRoute('favorites');
    }
}
