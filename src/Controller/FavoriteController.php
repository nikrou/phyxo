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
use Phyxo\MenuBar;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Functions\Utils;
use App\Repository\ImageRepository;
use App\Repository\FavoriteRepository;
use App\DataMapper\ImageMapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class FavoriteController extends CommonController
{
    public function index(Request $request, int $start = 0, EntityManager $em, Template $template, MenuBar $menuBar, Conf $conf, $themesDir, $phyxoVersion, $phyxoWebsite,
                            ImageMapper $imageMapper, ImageStandardParams $image_std_params, TranslatorInterface $translator)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['TITLE'] = $translator->trans('Favorites');

        // @TODO: retrieve current sort order: user, category or default
        $filter = [];
        $result = $em->getRepository(ImageRepository::class)->getFavorites($this->getUser(), $filter, $conf['order_by']);
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'image_id');

        if (count($tpl_params['items']) > 0) {
            $tpl_params['favorite'] = ['U_FAVORITE' => $this->generateUrl('remove_all_favorites')];

            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    '',
                    'favorites',
                    $start
                )
            );

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'favorites',
                [],
                count($tpl_params['items']),
                $start,
                $this->getUser()->getNbImagePage(),
                $conf['paginate_pages_around']
            );
        }

        $template->setImageStandardParams($image_std_params);
        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('thumbnails.tpl', $tpl_params);
    }

    public function add(int $image_id, EntityManager $em, Request $request, TranslatorInterface $translator)
    {
        $em->getRepository(FavoriteRepository::class)->addFavorite($this->getUser()->getId(), $image_id);

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

    public function remove(int $image_id, EntityManager $em, Request $request, TranslatorInterface $translator)
    {
        $em->getRepository(FavoriteRepository::class)->deleteFavorite($this->getUser()->getId(), $image_id);

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

    public function removeAll(EntityManager $em)
    {
        $em->getRepository(FavoriteRepository::class)->removeAllFavorites($this->getUser()->getId());

        return $this->redirectToRoute('favorites');
    }
}
