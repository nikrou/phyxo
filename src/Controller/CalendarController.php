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

class CalendarController extends BaseController
{
    public function index(string $legacyBaseDir, Request $request, $date_type, $type, $year, $month = null, $day = null)
    {
        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/%s-monthly-%s-%d', $date_type, $type, $year);
        if (!is_null($month)) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }

        if (!is_null($day)) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }

    public function categories(string $legacyBaseDir, Request $request, $date_type, $type, $year, $month = null, $day = null)
    {
        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/categories/%s-monthly-%s-%d', $date_type, $type, $year);
        if (!is_null($month)) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }

        if (!is_null($day)) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'month_calendar.tpl', $tpl_params);
    }

    public function details(string $legacyBaseDir, Request $request, $date_type, $time_params = null, $extra_params = null)
    {
        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $date_type . '-monthly-calendar';
        if (!is_null($time_params)) {
            $_SERVER['PATH_INFO'] .= "/$time_params";
        }
        if (!is_null($extra_params)) {
            $_SERVER['PATH_INFO'] .= "/$extra_params";
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl', $tpl_params);
    }
}
