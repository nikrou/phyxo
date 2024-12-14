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
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Conf;
use App\DataMapper\ImageMapper;
use App\Security\AppUserService;
use Phyxo\Functions\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IndexController extends AbstractController
{
    public function mostVisited(
        Request $request,
        Conf $conf,
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

        $tpl_params['PAGE_TITLE'] = $translator->trans('Most visited');
        $tpl_params['items'] = [];
        $order_by = [['id', 'DESC']];
        foreach ($imageMapper->getRepository()->findMostVisited($appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $order_by, $conf['top_number']) as $image) {
            $tpl_params['items'][] = $image->getId();
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $router,
                'most_visited',
                [],
                count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    '',
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    'most_visited',
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function recentPics(
        Request $request,
        Conf $conf,
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

        $tpl_params['PAGE_TITLE'] = $translator->trans('Recent photos');
        $tpl_params['items'] = [];

        $recent_date = new DateTime();
        $recent_date->sub(new DateInterval(sprintf('P%dD', $appUserService->getUser()->getUserInfos()->getRecentPeriod())));

        $order_by = [['id', 'DESC']];
        foreach ($imageMapper->getRepository()->findRecentImages($recent_date, $appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $order_by) as $image) {
            $tpl_params['items'] = $image->getId();
        }

        if (($tpl_params['items'] === null ? 0 : count($tpl_params['items'])) > 0) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $router,
                'recent_pics',
                [],
                $tpl_params['items'] === null ? 0 : count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    '',
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    'recent_pics',
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function bestRated(
        Request $request,
        Conf $conf,
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

        $tpl_params['PAGE_TITLE'] = $translator->trans('Best rated');
        $order_by = [['rating_score'], ['id',  'DESC']];

        $tpl_params['items'] = [];
        foreach ($imageMapper->getRepository()->findBestRated($conf['top_number'], $appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $order_by) as $image) {
            $tpl_params['items'][] = $image->getId();
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            if (count($tpl_params['items']) > $nb_image_page) {
                $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                    $router,
                    'best_rated',
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
                    '',
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    'best_rated',
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function random(ImageMapper $imageMapper, Conf $conf, AppUserService $appUserService): Response
    {
        $list = $imageMapper->getRepository()->findRandomImages(
            min(50, $conf['top_number'], $appUserService->getUser()->getUserInfos()->getNbImagePage()),
            $appUserService->getUser()->getUserInfos()->getForbiddenAlbums()
        );

        if (count($list) === 0) {
            return $this->redirectToRoute('homepage');
        } else {
            return $this->redirectToRoute('random_list', ['list' => implode(',', $list)]);
        }
    }

    public function randomList(
        Request $request,
        string $list,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        AppUserService $appUserService,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['TITLE'] = $translator->trans('Random photos');
        $tpl_params['items'] = [];
        foreach ($imageMapper->getRepository()->getList(explode(',', $list), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums()) as $image) {
            $tpl_params['items'][] = $image->getId();
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    $list,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    'list',
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render('thumbnails.html.twig', $tpl_params);
    }
}
