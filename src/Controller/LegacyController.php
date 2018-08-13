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

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyController extends Controller
{
    public function fallback(Request $request, $path_info)
    {
        $legacy_file = sprintf('%s/%s.php', $this->container->getParameter('legacy_base_dir'), $path_info);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/$path_info";

        return $this->doResponse($legacy_file);
    }

    public function indexSearch(Request $request, $search_id, $start_id = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/search/$search_id";

        if (!is_null($start_id)) {
            $_SERVER['PATH_INFO'] .= "/$start_id";
        }

        return $this->doResponse($legacy_file);
    }

    public function searchRules(Request $request, $search_id)
    {
        $legacy_file = sprintf('%s/search_rules.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/search/$search_id";

        return $this->doResponse($legacy_file);
    }

    public function album(Request $request, $category_id, $start_id = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/category/$category_id";

        if (!is_null($start_id)) {
            $_SERVER['PATH_INFO'] .= "/$start_id";
        }

        return $this->doResponse($legacy_file);
    }

    public function picture(Request $request, $image_id, $type, $element_id)
    {
        $legacy_file = sprintf('%s/picture.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/' . $type . '/' . $element_id;

        return $this->doResponse($legacy_file);
    }

    public function imagesBySearch(Request $request, $image_id, $search_id)
    {
        $legacy_file = sprintf('%s/picture.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/search' . $search_id;

        return $this->doResponse($legacy_file);
    }

    public function imagesByTypes(Request $request, $image_id, $type, $time_params = null, $extra_params = null)
    {
        $legacy_file = sprintf('%s/picture.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/' . $type;
        if (!is_null($extra_params)) {
            $_SERVER['PATH_INFO'] .= "/$extra_params";
        }

        return $this->doResponse($legacy_file);
    }

    public function index(Request $request, $type, $time_params = null, $extra_params = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $type;
        if (!is_null($time_params)) {
            $_SERVER['PATH_INFO'] .= "/$time_params";
        }
        if (!is_null($extra_params)) {
            $_SERVER['PATH_INFO'] .= "/$extra_params";
        }

        return $this->doResponse($legacy_file);
    }

    public function imagesByTags(Request $request, $tag_id)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/tags/' . $tag_id;

        return $this->doResponse($legacy_file);
    }

    public function calendar(Request $request, $date_type, $type, $year, $month = null, $day = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/%s-monthly-%s-%d', $date_type, $type, $year);
        if (!is_null($month)) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }
        if (!is_null($day)) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        return $this->doResponse($legacy_file);
    }

    public function categoriesCalendar(Request $request, $date_type, $type, $year, $month = null, $day = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/categories/%s-monthly-%s-%d', $date_type, $type, $year);
        if (!is_null($month)) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }
        if (!is_null($day)) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        return $this->doResponse($legacy_file);
    }

    public function indexRandom(Request $request, $list)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/list/$list";

        return $this->doResponse($legacy_file);
    }

    public function indexLegacy(Request $request, $path_info)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/$path_info";

        return $this->doResponse($legacy_file);
    }

    private function doResponse($legacy_file)
    {
        $_SERVER['PHP_SELF'] = $legacy_file;
        $_SERVER['SCRIPT_NAME'] = $legacy_file;
        $_SERVER['SCRIPT_FILENAME'] = $legacy_file;
        $_SERVER['CONTAINER'] = $this->container;

        try {
            global $conf, $conn, $services, $template, $user, $page, $persistent_cache, $lang, $lang_info;

            ob_start();
            chdir(dirname($legacy_file));
            require $legacy_file;
            return new Response(ob_get_clean());
        } catch (Routing\Exception\ResourceNotFoundException $e) {
            return new Response('Not Found', 404);
        } catch (Exception $e) {
            return new Response('An error occurred', 500);
        }
    }
}