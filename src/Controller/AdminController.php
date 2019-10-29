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

use App\Repository\UpgradeRepository;
use Phyxo\DBLayer\iDBLayer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\DataMapper\TagMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\UserMapper;
use App\DataMapper\CategoryMapper;
use App\DataMapper\RateMapper;
use Phyxo\EntityManager;
use Symfony\Component\Routing\RouterInterface;
use Phyxo\Image\ImageStandardParams;
use App\DataMapper\SearchMapper;
use App\Metadata;

class AdminController extends Controller
{
    protected $csrfTokenManager, $passwordEncoder, $tagMapper, $commentMapper, $userMapper, $categoryMapper, $rateMapper, $searchMapper, $em, $router, $image_std_params;
    protected $pemURL, $metadata;

    public function upgrade(iDBLayer $conn)
    {
        $tables = $conn->db_get_tables($conn->getPrefix());
        $columns_of = $conn->db_get_columns_of($tables);

        $result = (new UpgradeRepository($conn))->findAll();
        $applied_upgrades = $conn->result2array($result, null, 'id');

        if (!in_array(142, $applied_upgrades)) {
            $current_release = '1.0.0';
        } elseif (!in_array(144, $applied_upgrades)) {
            $current_release = '1.1.0';
        } elseif (!in_array(145, $applied_upgrades)) {
            $current_release = '1.2.0';
        } elseif (in_array('validated', $columns_of[$conn->getPrefix() . 'tags'])) {
            $current_release = '1.3.0';
        } elseif (!in_array(146, $applied_upgrades)) {
            $current_release = '1.5.0';
        } elseif (!in_array(147, $applied_upgrades)) {
            $current_release = '1.6.0';
        } elseif (!is_dir(__DIR__ . '/../src/LegacyPages')) {
            $current_release = '1.8.0';
        } else {
            $current_release = '1.9.0';
        }

        if (is_readable($upgrade_file = __DIR__ . '/../install/upgrade_' . $current_release . '.php')) {
            ob_start();
            include($upgrade_file);
            ob_end_clean();
        }

        return $this->redirectToRoute('admin_home');
    }

    public function index(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder,
                        TagMapper $tagMapper, CommentMapper $commentMapper, UserMapper $userMapper, CategoryMapper $categoryMapper, RateMapper $rateMapper, EntityManager $em,
                        RouterInterface $router, ImageStandardParams $image_std_params, SearchMapper $searchMapper, string $pemURL, Metadata $metadata)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->tagMapper = $tagMapper;
        $this->commentMapper = $commentMapper;
        $this->userMapper = $userMapper;
        $this->categoryMapper = $categoryMapper;
        $this->rateMapper = $rateMapper;
        $this->searchMapper = $searchMapper;
        $this->em = $em;
        $this->router = $router;
        $this->image_std_params = $image_std_params;
        $this->pemURL = $pemURL;
        $this->metadata = $metadata;

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
        $em = $this->em;
        $passwordEncoder = $this->passwordEncoder;
        $csrf_token = $this->csrfTokenManager->getToken('authenticate');
        $tagMapper = $this->tagMapper;
        $commentMapper = $this->commentMapper;
        $userMapper = $this->userMapper;
        $categoryMapper = $this->categoryMapper;
        $rateMapper = $this->rateMapper;
        $searchMapper = $this->searchMapper;

        $pemURL = $this->pemURL;
        $metadata = $this->metadata;
        $em = $this->em;
        $router = $this->router;
        $image_std_params = $this->image_std_params;

        try {
            global $pwg_loaded_plugins, $header_notes, $env_nbm, $prefixeTable, $conf, $conn, $filter, $template, $user, $page,
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
