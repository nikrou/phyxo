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

class TagController extends BaseController
{
    public function list(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $legacy_file = sprintf('%s/tags.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/tags";

        return $this->doResponse($legacy_file, 'tags.tpl');
    }

    public function imagesByTags(string $legacyBaseDir, Request $request, $tag_id, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        
        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/tags/' . $tag_id;

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }
}
