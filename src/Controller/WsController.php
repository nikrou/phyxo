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
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Security\UserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\DataMapper\TagMapper;

class WsController extends Controller
{
    public function index(string $legacyBaseDir, Request $request, TagMapper $tagMapper, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder, UserProvider $userProvider)
    {
        $legacy_file = sprintf('%s/ws.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/ws';

        $container = $this->container; // allow accessing container as global variable
        $csrf_token = $csrfTokenManager->getToken('authenticate');

        if (!$app_user = $this->getUser()) {
            $app_user = $userProvider->loadUserByUsername('guest');
        }

        try {
            global
                $conf, $conn, $title, $t2, $pwg_loaded_plugins, $prefixeTable, $header_notes, $services, $filter, $template, $user,
                $page, $lang, $lang_info;

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
