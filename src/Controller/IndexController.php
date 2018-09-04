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
use Phyxo\DBLayer\iDBLayer;

class IndexController extends BaseController
{
    public function favorites(Request $request)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/favorites";

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function mostVisited(Request $request)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/most_visited";

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function recentPics(Request $request)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/recent_pics";

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function recentCats(Request $request)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/recent_cats";

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function bestRated(Request $request)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/best_rated";

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function random(Request $request)
    {
        global $conf, $conn, $services, $filter, $template, $user, $page, $persistent_cache, $lang, $lang_info;

        $container = $this->container;
        define('PHPWG_ROOT_PATH', '../');
        $legacy_file = __DIR__ . '/../../include/common.inc.php';

        ob_start();
        chdir(dirname($legacy_file));
        require $legacy_file;

        $query = 'SELECT id FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'id'
            ],
            'WHERE'
        );
        $query .= ' ORDER BY ' . $this->container->get('phyxo.conn')::RANDOM_FUNCTION . '()';
        $query .= ' LIMIT ' . min(50, $conf['top_number'], $user['nb_image_page']);
        $list = $this->container->get('phyxo.conn')->query2array($query, null, 'id');

        return $this->redirectToRoute('random_list', ['list' => implode(',', $list)]);
    }

    public function randomList(Request $request, $list)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/list/$list";

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }
}
