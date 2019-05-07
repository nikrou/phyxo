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
use Phyxo\MenuBar;

class TagController extends BaseController
{
    public function list(string $legacyBaseDir, Request $request, CsrfTokenManagerInterface $csrfTokenManager, MenuBar $menuBar)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/tags.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/tags";

        $menuBar->setRoute('tags');

        return $this->doResponse($legacy_file, 'tags.tpl', $tpl_params);
    }

    public function imagesByTags(string $legacyBaseDir, Request $request, $tag_id, CsrfTokenManagerInterface $csrfTokenManager, MenuBar $menuBar)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/tags/' . $tag_id;

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        // tag is made with tag-id minus and tag-url : example 1-house
        $tag_ids = [substr($tag_id, 0, strpos($tag_id, '-'))];
        // $tag_url_names = [];

        // $result = $em->getRepository(TagRepository::class)->findTags($tag_ids, $tag_url_names);
        // $tpl_params['tags'] = $em->getConnection()->result2array($result);

        // $filter = [];
        // $result = $em->getRepository(TagRepository::class)->getImageIdsForTags($this->getUser(), $filter, $tag_ids);
        // $tpl_params['items'] = $em->getConnection()->result2array($result, null, 'id');

        $menuBar->setRoute('images_by_tags');
        $menuBar->setCurrentTags($tag_ids);

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }
}
