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
use App\Enum\PictureSectionType;
use App\Security\AppUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IndexController extends AbstractController
{
    use ThumbnailsControllerTrait;

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/most_visited/{start}', name: 'most_visited', defaults: ['start' => 0], requirements: ['start' => '\d+'])]
    public function mostVisited(
        Request $request,
        Conf $conf,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        RouterInterface $router,
        AppUserService $appUserService,
        array $publicTemplates,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $tpl_params['PAGE_TITLE'] = $translator->trans('Most visited');
        $tpl_params['items'] = [];
        $order_by = [['id', 'DESC']];
        foreach ($imageMapper->getRepository()->findMostVisited($appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $order_by, $conf['top_number']) as $image) {
            $tpl_params['items'][] = $image->getId();
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params['thumb_navbar'] = $this->defineNavigation(
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
                    PictureSectionType::MOST_VISITED,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render(sprintf('%s.html.twig', $publicTemplates['albums']), $tpl_params);
    }

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/recent_pics/{start}', name: 'recent_pics', defaults: ['start' => 0], requirements: ['start' => '\d+'])]
    public function recentPics(
        Request $request,
        Conf $conf,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        RouterInterface $router,
        AppUserService $appUserService,
        array $publicTemplates,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
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

            $tpl_params['thumb_navbar'] = $this->defineNavigation(
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
                    PictureSectionType::RECENT_PICS,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render(sprintf('%s.html.twig', $publicTemplates['albums']), $tpl_params);
    }

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/best_rated/{start}', name: 'best_rated', defaults: ['start' => 0], requirements: ['start' => '\d+'])]
    public function bestRated(
        Request $request,
        Conf $conf,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        RouterInterface $router,
        AppUserService $appUserService,
        array $publicTemplates,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
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
                $tpl_params['thumb_navbar'] = $this->defineNavigation(
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
                    PictureSectionType::BEST_RATED,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render(sprintf('%s.html.twig', $publicTemplates['albums']), $tpl_params);
    }

    #[Route('/random', name: 'random')]
    public function random(ImageMapper $imageMapper, Conf $conf, AppUserService $appUserService): Response
    {
        $list = $imageMapper->getRepository()->findRandomImages(
            min(50, $conf['top_number'], $appUserService->getUser()->getUserInfos()->getNbImagePage()),
            $appUserService->getUser()->getUserInfos()->getForbiddenAlbums()
        );

        if ($list === []) {
            return $this->redirectToRoute('homepage');
        } else {
            return $this->redirectToRoute('random_list', ['list' => implode(',', $list)]);
        }
    }

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/list/{list}', name: 'random_list')]
    public function randomList(
        Request $request,
        string $list,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        AppUserService $appUserService,
        array $publicTemplates,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $tpl_params['TITLE'] = $translator->trans('Random photos');
        $tpl_params['items'] = [];
        $listIds = array_map(fn ($s): int => intval($s), explode(',', $list));
        foreach ($imageMapper->getRepository()->getList($listIds, $appUserService->getUser()->getUserInfos()->getForbiddenAlbums()) as $image) {
            $tpl_params['items'][] = $image->getId();
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    $list,
                    PictureSectionType::LIST,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );
        }

        $tpl_params['START_ID'] = $start;

        return $this->render(sprintf('%s.html.twig', $publicTemplates['albums']), $tpl_params);
    }
}
