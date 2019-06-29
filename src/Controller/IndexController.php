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

class IndexController extends BaseController
{
    public function mostVisited(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager, MenuBar $menuBar)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/most_visited";

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        // menuBar : inject items

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }

    public function recentPics(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager, MenuBar $menuBar)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/recent_pics";

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        // menuBar : inject items

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }

    public function recentCats(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager, MenuBar $menuBar)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/recent_cats";

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        // menuBar : inject items

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }

    public function bestRated(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager, MenuBar $menuBar)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/best_rated";

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        // menuBar : inject items

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }

    public function random(EntityManager $em, Conf $conf)
    {
        $filter = [];
        $where_sql = ' ' . (new BaseRepository($em->getConnection()))->getSQLConditionFandF(
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

    public function randomList(string $legacyBaseDir, Request $request, $list, CsrfTokenManagerInterface $csrfTokenManager, MenuBar $menuBar)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/list/$list";

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        // menuBar : inject items

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }
}
