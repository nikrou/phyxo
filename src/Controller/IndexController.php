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
use App\Repository\ImageRepository;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Repository\BaseRepository;
use Phyxo\MenuBar;
use Phyxo\EntityManager;
use Phyxo\Conf;
use Phyxo\Functions\Language;
use App\DataMapper\ImageMapper;
use Phyxo\Template\Template;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Functions\Utils;

class IndexController extends CommonController
{
    public function mostVisited(Request $request, EntityManager $em, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
                                ImageMapper $imageMapper, ImageStandardParams $image_std_params, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $filter = [];
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

        $tpl_params['PAGE_TITLE'] = Language::l10n('Most visited');

        $result = $em->getRepository(ImageRepository::class)->searchDistinctId('id', ['hit > 0 ' . $forbidden], true, $conf['order_by'], $conf['top_number']);
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'id');
        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
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
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    '',
                    'most_visited',
                    $start
                )
            );
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('thumbnails.tpl', $tpl_params);
    }

    public function recentPics(Request $request, EntityManager $em, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
                                ImageMapper $imageMapper, ImageStandardParams $image_std_params, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
        $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $filter = [];
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

        $tpl_params['PAGE_TITLE'] = Language::l10n('Recent photos');
        $result = $em->getRepository(ImageRepository::class)->searchDistinctId(
            'id',
            [$em->getRepository(BaseRepository::class)->getRecentPhotos($this->getUser(), 'date_available') . ' ' . $forbidden], true, $conf['order_by']
        );
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'id');

        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'recent_pics',
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
                '',
                'recent_pics',
                $start
                )
            );
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('thumbnails.tpl', $tpl_params);
    }

    public function bestRated(Request $request, EntityManager $em, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
                            ImageMapper $imageMapper, ImageStandardParams $image_std_params, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $filter = [];
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

        $tpl_params['PAGE_TITLE'] = Language::l10n('Best rated');

        $super_order_by = true;
        $order_by = ' ORDER BY rating_score DESC, id DESC';

        $result = $em->getRepository(ImageRepository::class)->searchDistinctId('id', ['rating_score IS NOT NULL ' . $forbidden], true, $order_by, $conf['top_number']);
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'id');

        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'best_rated',
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
                '',
                'best_rated',
                $start
                )
            );
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('thumbnails.tpl', $tpl_params);
    }

    public function random(EntityManager $em, Conf $conf)
    {
        $filter = [];
        $where_sql = ' ' . $em->getRepository(BaseRepository::class)->getSQLConditionFandF(
            $this->getUser(),
            $filter,
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'id'
            ],
            'WHERE'
        );
        $result = $em->getRepository(ImageRepository::class)->findRandomImages($where_sql, '', min(50, $conf['top_number'], $this->getUser()->getNbImagePage()));
        $list = $em->getConnection()->result2array($result, null, 'id');

        if (empty($list)) {
            return $this->redirectToRoute('homepage');
        } else {
            return $this->redirectToRoute('random_list', ['list' => implode(',', $list)]);
        }
    }

    public function randomList(Request $request, EntityManager $em, string $list, Conf $conf, ImageMapper $imageMapper, MenuBar $menuBar, int $start = 0,
                                Template $template, string $themesDir, string $phyxoVersion, string $phyxoWebsite, ImageStandardParams $image_std_params)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $filter = [];
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

        $tpl_params['PAGE_TITLE'] = Language::l10n('Random photos');
        $result = $em->getRepository(ImageRepository::class)->findList(explode(',', $list), $forbidden, $conf['order_by']);
        $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'id');

        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $list,
                    'list',
                    $start
                )
            );
        }

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('thumbnails.tpl', $tpl_params);
    }
}
