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

class TagController extends BaseController
{
    public function list(Request $request)
    {
        $legacy_file = sprintf('%s/tags.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/tags";

        return $this->doResponse($legacy_file, 'tags.tpl');
    }

    public function imagesByTags(Request $request, $tag_id)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/tags/' . $tag_id;

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }
}
