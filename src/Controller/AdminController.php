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

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $legacy_file = sprintf('%s/admin.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/index.php';

        return $this->doResponse($legacy_file);
    }

    public function user_list_backend(Request $request)
    {
        $legacy_file = sprintf('%s/user_list_backend.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/user_list_backend.php';

        return $this->doResponse($legacy_file);
    }

    private function doResponse($legacy_file)
    {
        $_SERVER['PHP_SELF'] = $legacy_file;
        $_SERVER['SCRIPT_NAME'] = $legacy_file;
        $_SERVER['SCRIPT_FILENAME'] = $legacy_file;

        $container = $this->container; // allow accessing container as global variable

        try {
            global $cache, $env_nbm, $prefixeTable, $conf, $conn, $services, $filter, $template, $user, $page, $persistent_cache, $lang, $lang_info;

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