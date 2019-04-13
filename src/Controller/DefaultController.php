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
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class DefaultController extends BaseController
{
    public function home(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/";

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }

    public function about(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $legacy_file = sprintf('%s/about.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/about";

        return $this->doResponse($legacy_file, 'about.tpl');
    }

    public function feed(string $legacyBaseDir, Request $request)
    {
        $legacy_file = sprintf('%s/feed.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/feed";

        return $this->doResponse($legacy_file, 'feed.tpl');
    }

    public function notification(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $legacy_file = sprintf('%s/notification.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/notification";

        return $this->doResponse($legacy_file, 'notification.tpl');
    }

    public function comments(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $legacy_file = sprintf('%s/comments.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/comments";

        return $this->doResponse($legacy_file, 'comments.tpl');
    }

    public function action(Request $request, $image_id, $part, $download = false)
    {
        global $conf, $conn, $filter, $template, $user, $page, $lang, $lang_info;

        $container = $this->container;
        if (!$app_user = $this->getUser()) {
            $app_user = $this->userProvider->loadUserByUsername('guest');
        }

        $legacy_file = __DIR__ . '/../../include/common.inc.php';

        ob_start();
        chdir(dirname($legacy_file));
        require $legacy_file;

        $result = (new ImageRepository($conn))->findById($image_id);
        $element_info = $conn->db_fetch_assoc($result);

        /* $filter['visible_categories'] and $filter['visible_images']
        /* are not used because it's not necessary (filter <> restriction)
         */
        if (!(new CategoryRepository($conn))->hasAccessToImage($image_id)) {
            throw new AccessDeniedException('Access denied');
        }

        $file = '';
        switch ($part) {
            case 'e':
                if (!$user['enabled_high']) {
                    $deriv = new \Phyxo\Image\DerivativeImage(IMG_XXLARGE, new \Phyxo\Image\SrcImage($element_info));
                    if (!$deriv->same_as_source()) {
                        throw new AccessDeniedException('Access denied');
                    }
                }
                $file = \Phyxo\Functions\Utils::get_element_path($element_info);
                break;
            case 'r':
                $file = \Phyxo\Functions\Utils::original_to_representative(\Phyxo\Functions\Utils::get_element_path($element_info), $element_info['representative_ext']);
                break;
        }

        return $this->file($file);
    }
}
