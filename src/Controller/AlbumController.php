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
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class AlbumController extends BaseController
{
    public function album(Request $request, $category_id, $start_id = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/category/$category_id";

        if (!is_null($start_id)) {
            $_SERVER['PATH_INFO'] .= "/$start_id";
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function albumByParams(Request $request, $time_params = null, $extra_params = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/categories';

        if (!is_null($time_params)) {
            $_SERVER['PATH_INFO'] .= "/$time_params";
        }

        if (!is_null($extra_params)) {
            $_SERVER['PATH_INFO'] .= "/$extra_params";
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }
}
