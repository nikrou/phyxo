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
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Security\UserProvider;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

abstract class BaseController extends Controller
{
    protected $csrfTokenManager, $userProvider, $passwordEncoder;
    protected $phyxoVersion, $phyxoWebsite;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, UserProvider $userProvider, UserPasswordEncoderInterface $passwordEncoder, $phyxoVersion, $phyxoWebsite)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->userProvider = $userProvider;
        $this->passwordEncoder = $passwordEncoder;

        $this->phyxoVersion = $phyxoVersion;
        $this->phyxoWebsite = $phyxoWebsite;
    }

    protected function doResponse($legacy_file, string $template_name, array $extra_params = [])
    {
        $_SERVER['PHP_SELF'] = $legacy_file;
        $_SERVER['SCRIPT_NAME'] = $legacy_file;
        $_SERVER['SCRIPT_FILENAME'] = $legacy_file;

        $container = $this->container; // allow accessing container as global variable
        if (!$app_user = $this->getUser()) {
            $app_user = $this->userProvider->loadUserByUsername('guest');
        }
        $passwordEncoder = $this->passwordEncoder;

        $tpl_params = [];

        try {
            global $conf, $conn, $title, $t2, $pwg_loaded_plugins, $prefixeTable, $header_notes, $services, $filter, $template, $user,
                $page, $lang, $lang_info;

            ob_start();
            chdir(dirname($legacy_file));
            require $legacy_file;

            $tpl_params['GALLERY_TITLE'] = isset($page['gallery_title']) ? $page['gallery_title'] : $conf['gallery_title'];
            $tpl_params['PAGE_BANNER'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_page_banner',
                str_replace(
                    '%gallery_title%',
                    $conf['gallery_title'],
                    isset($page['page_banner']) ? $page['page_banner'] : $conf['page_banner']
                )
            );

            $tpl_params['PAGE_TITLE'] = strip_tags($title);
            $tpl_params['U_HOME'] = \Phyxo\Functions\URL::get_root_url();
            $tpl_params['LEVEL_SEPARATOR'] = $conf['level_separator'];
            if (!empty($header_notes)) {
                $tpl_params['header_notes'] = $header_notes;
            }

            if (!$services['users']->isGuest()) {
                $tpl_params['CONTACT_MAIL'] = \Phyxo\Functions\Utils::get_webmaster_mail_address();
            }
            $debug_vars = [];
            if ($conf['show_gt']) {
                if (!isset($page['count_queries'])) {
                    $page['count_queries'] = 0;
                    $page['queries_time'] = 0;
                }
                $time = \Phyxo\Functions\Utils::get_elapsed_time($t2, microtime(true));

                $debug_vars = array_merge(
                    $debug_vars,
                    [
                        'TIME' => $time,
                        'NB_QUERIES' => $conn->getQueriesCount(),
                        'SQL_TIME' => number_format($conn->getQueriesTime() * 1000, 2, '.', ' ') . ' ms'
                    ]
                );
            }

            $tpl_params['debug'] = $debug_vars;

            // user & connection infos
            if ($this->isGranted('ROLE_USER')) {
                $tpl_params['APP_USER'] = $this->getUser();
                $tpl_params['U_LOGOUT'] = $this->generateUrl('logout');
                if ($this->isGranted('ROLE_ADMIN')) {
                    $tpl_params['U_ADMIN'] = $this->generateUrl('admin_home');
                }
            } else {
                $tpl_params['U_LOGIN'] = $this->generateUrl('login');
                $tpl_params['csrf_token'] = $this->csrfTokenManager->getToken('authenticate');
            }

            $tpl_params['CONTENT_ENCODING'] = 'utf-8';
            $tpl_params['PHYXO_URL'] = $this->phyxoWebsite;
            $tpl_params['PHYXO_VERSION'] = $conf['show_version'] ? $this->phyxoVersion : '';

            $tpl_params = array_merge($tpl_params, $extra_params);

            return $this->render($template_name, $tpl_params);
        } catch (ResourceNotFoundException $e) {
            return new Response('Not Found', 404);
        }
    }
}
