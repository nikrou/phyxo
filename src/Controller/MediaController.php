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
use Symfony\Component\HttpFoundation\Response;

class MediaController extends BaseController
{
    public function index(string $legacyBaseDir, Request $request, string $path, string $derivative, ? string $sizes = null, string $image_extension)
    {
        $legacy_file = sprintf('%s/i.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = $path;
        $_SERVER['PATH_INFO'] .= "-$derivative";

        if (!is_null($sizes)) {
            $_SERVER['PATH_INFO'] .= $sizes;
        }

        $_SERVER['PATH_INFO'] .= ".$image_extension";

        $container = $this->container; // allow accessing container as global variable

        try {
            global $conf, $conn, $page, $user, $services, $template;

            ob_start();
            chdir(dirname($legacy_file));
            require $legacy_file;

            $content = ob_get_clean();

            return new Response($content);
        } catch (ResourceNotFoundException $e) {
            return new Response('Not Found', 404);
        }
    }
}
