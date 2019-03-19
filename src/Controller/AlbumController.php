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

class AlbumController extends BaseController
{
    public function album(string $legacyBaseDir, Request $request, $category_id, $start_id = null)
    {
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/category/$category_id";

        if (!is_null($start_id)) {
            $_SERVER['PATH_INFO'] .= "/$start_id";
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function albumByParams(string $legacyBaseDir, Request $request, $time_params = null, $extra_params = null)
    {
        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);
        
        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/categories';
        
        if (!is_null($time_params)) {
            $_SERVER['PATH_INFO'] .= "/$time_params";
        }
        
        if (!is_null($extra_params)) {
            $_SERVER['PATH_INFO'] .= "/$extra_params";
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }
}
