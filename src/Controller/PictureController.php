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

class PictureController extends BaseController
{
    public function picture(string $legacyBaseDir, Request $request, $image_id, $type, $element_id)
    {
        $legacy_file = sprintf('%s/picture.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/' . $type . '/' . $element_id;

        return $this->doResponse($legacy_file, 'picture.tpl');
    }

    public function imagesByTypes(string $legacyBaseDir, Request $request, $image_id, $type, $time_params = null, $extra_params = null)
    {
        $legacy_file = sprintf('%s/picture.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/' . $type;
        if (!is_null($extra_params)) {
            $_SERVER['PATH_INFO'] .= "/$extra_params";
        }

        return $this->doResponse($legacy_file, 'picture.tpl');
    }
}
