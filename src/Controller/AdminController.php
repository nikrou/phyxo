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
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AdminController extends Controller
{
    protected $csrfTokenManager, $passwordEncoder;

    public function index(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $legacy_file = sprintf('%s/admin.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/index.php';

        return $this->doResponse($legacy_file);
    }

    private function doResponse($legacy_file)
    {
        $_SERVER['PHP_SELF'] = $legacy_file;
        $_SERVER['SCRIPT_NAME'] = $legacy_file;
        $_SERVER['SCRIPT_FILENAME'] = $legacy_file;

        $container = $this->container; // allow accessing container as global variable
        $app_user = $this->getUser();
        $passwordEncoder = $this->passwordEncoder;
        $csrf_token = $this->csrfTokenManager->getToken('authenticate');

        try {
            global $pwg_loaded_plugins, $header_notes, $env_nbm, $prefixeTable, $conf, $conn, $services, $filter, $template, $user, $page,
                $lang, $lang_info;

            ob_start();
            chdir(dirname($legacy_file));
            require $legacy_file;

            return new Response($template->flush(true));
        } catch (ResourceNotFoundException $e) {
            return new Response('Not Found', 404);
        }
    }
}
