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
use App\Security\UserProvider;
use App\DataMapper\TagMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\UserMapper;
use App\DataMapper\CategoryMapper;
use Phyxo\MenuBar;
use App\DataMapper\RateMapper;
use Phyxo\EntityManager;


abstract class BaseController extends Controller
{
    protected $tagMapper, $commentMapper, $userMapper, $categroyMapper, $rateMapper;
    protected $csrfTokenManager, $userProvider, $passwordEncoder;
    protected $phyxoVersion, $phyxoWebsite;
    protected $menuBar, $em;

    public function __construct(UserProvider $userProvider, TagMapper $tagMapper, CommentMapper $commentMapper, UserMapper $userMapper, CategoryMapper $categoryMapper,
                                RateMapper $rateMapper, MenuBar $menuBar, EntityManager $em)
    {
        $this->userProvider = $userProvider;
        $this->tagMapper = $tagMapper;
        $this->commentMapper = $commentMapper;
        $this->userMapper = $userMapper;
        $this->categroyMapper = $categoryMapper;
        $this->rateMapper = $rateMapper;
        $this->menuBar = $menuBar;
        $this->em = $em;
    }

    protected function doResponse($legacy_file, string $template_name, array $extra_params = [])
    {
        $_SERVER['PHP_SELF'] = $legacy_file;
        $_SERVER['SCRIPT_NAME'] = $legacy_file;
        $_SERVER['SCRIPT_FILENAME'] = $legacy_file;

        $container = $this->container; // allow accessing container as global variable
        $tagMapper = $this->tagMapper;
        $commentMapper = $this->commentMapper;
        $categoryMapper = $this->categroyMapper;
        $userMapper = $this->userMapper;
        $em = $this->em;

        if (!$app_user = $this->getUser()) {
            $app_user = $this->userProvider->loadUserByUsername('guest');
        }

        $tpl_params = [];

        try {
            global $conf, $conn, $title, $t2, $pwg_loaded_plugins, $prefixeTable, $header_notes, $filter, $template, $user,
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

            if (!$userMapper->isGuest()) {
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
            if ($this->isGranted('ROLE_NORMAL')) {
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

            if (isset($page['items'], $page['start'], $page['nb_image_page'])) {
                $this->menuBar->setCurrentImages(array_slice($page['items'], $page['start'], $page['nb_image_page']));
            }
            $tpl_params = array_merge($tpl_params, $this->menuBar->getBlocks());

            return $this->render($template_name, $tpl_params);
        } catch (ResourceNotFoundException $e) {
            return new Response('Not Found', 404);
        }
    }
}
