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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class PictureController extends BaseController
{
    public function picture(string $legacyBaseDir, Request $request, $image_id, $type, $element_id, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/picture.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/' . $type . '/' . $element_id;

        return $this->doResponse($legacy_file, 'picture.tpl', $tpl_params);
    }

    public function picturesByTypes(string $legacyBaseDir, Request $request, $image_id, $type, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/picture.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/' . $type;

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        return $this->doResponse($legacy_file, 'picture.tpl', $tpl_params);
    }

    public function pictureBySearch(string $legacyBaseDir, Request $request, $image_id, $search_id, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/picture.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/search' . $search_id;

        // menuBar : inject items

        return $this->doResponse($legacy_file, 'picture.tpl', $tpl_params);
    }

    public function pictureFromCalendar(string $legacyBaseDir, Request $request, int $image_id, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/picture.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id;

        if ($extra = $request->get('extra')) {
            $_SERVER['PATH_INFO'] .= '/' . $extra;
        }

        return $this->doResponse($legacy_file, 'picture.tpl', $tpl_params);
    }
}
