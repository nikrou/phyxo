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

class SearchController extends BaseController
{
    public function qsearch(string $legacyBaseDir, Request $request)
    {
        $legacy_file = sprintf('%s/qsearch.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/qsearch";

        return $this->doResponse($legacy_file, 'qsearch.tpl');
    }

    public function search(string $legacyBaseDir, Request $request)
    {
        $legacy_file = sprintf('%s/search.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/search";

        return $this->doResponse($legacy_file, 'search.tpl');
    }

    public function searchResults(string $legacyBaseDir, Request $request, $search_id, $start_id = null)
    {
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/search/$search_id";

        if (!is_null($start_id)) {
            $_SERVER['PATH_INFO'] .= "/$start_id";
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function searchRules(string $legacyBaseDir, Request $request, $search_id)
    {
        $legacy_file = sprintf('%s/search_rules.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = "/search/$search_id";

        return $this->doResponse($legacy_file, 'search_rules.tpl');
    }

    public function imagesBySearch(string $legacyBaseDir, Request $request, $image_id, $search_id)
    {
        $legacy_file = sprintf('%s/picture.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $image_id . '/search' . $search_id;

        return $this->doResponse($legacy_file, 'picture.tpl');
    }
}
