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

class DefaultController extends BaseController
{
    public function home(Request $request)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/";

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function about(Request $request)
    {
        $legacy_file = sprintf('%s/about.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/about";

        return $this->doResponse($legacy_file, 'about.tpl');
    }

    public function feed(Request $request)
    {
        $legacy_file = sprintf('%s/feed.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/feed";

        return $this->doResponse($legacy_file, 'feed.tpl');
    }

    public function notification(Request $request)
    {
        $legacy_file = sprintf('%s/notification.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/notification";

        return $this->doResponse($legacy_file, 'notification.tpl');
    }

    public function comments(Request $request)
    {
        $legacy_file = sprintf('%s/comments.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/comments";

        return $this->doResponse($legacy_file, 'comments.tpl');
    }

    public function action(Request $request, $image_id, $part, $download = false)
    {
        global $conf, $conn, $services, $filter, $template, $user, $page, $persistent_cache, $lang, $lang_info;

        $container = $this->container;
        define('PHPWG_ROOT_PATH', '../');
        $legacy_file = __DIR__ . '/../../include/common.inc.php';

        ob_start();
        chdir(dirname($legacy_file));
        require $legacy_file;

        $query = 'SELECT * FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id=' . $conn->db_real_escape_string($image_id);

        $element_info = $conn->db_fetch_assoc($conn->db_query($query));

        /* $filter['visible_categories'] and $filter['visible_images']
        /* are not used because it's not necessary (filter <> restriction)
         */
        $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON category_id = id';
        $query .= ' WHERE image_id = ' . $conn->db_real_escape_string($image_id);
        $query .= \Phyxo\Functions\SQL::get_sql_condition_FandF(['forbidden_categories' => 'category_id', 'forbidden_images' => 'image_id'], ' AND ');
        $query .= ' LIMIT 1';

        $file = '';
        switch ($part) {
            case 'e':
                if (!$user['enabled_high']) {
                    $deriv = new \Phyxo\Image\DerivativeImage(IMG_XXLARGE, new \Phyxo\Image\SrcImage($element_info));
                    if (!$deriv->same_as_source()) {
                        throw new AccessDeniedException();
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
