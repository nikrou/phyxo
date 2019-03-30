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

class CalendarController extends BaseController
{
    public function categoriesMonthly(string $legacyBaseDir, Request $request, string $date_type, string $view_type, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/categories/%s-monthly-%s', $date_type, $view_type);

        if ($year = $request->get('year')) {
            $_SERVER['PATH_INFO'] .= '-' . $year;
        }

        if ($month = $request->get('month')) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }

        if ($day = $request->get('day')) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'month_calendar.tpl', $tpl_params);
    }

    public function categoriesWeekly(string $legacyBaseDir, Request $request, string $date_type, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/categories/%s-weekly-list', $date_type);

        if ($year = $request->get('year')) {
            $_SERVER['PATH_INFO'] .= '-' . $year;
        }

        if ($week = $request->get('week')) {
            $_SERVER['PATH_INFO'] .= '-' . $week;
        }

        if ($wday = $request->get('wday')) {
            $_SERVER['PATH_INFO'] .= '-' . $wday;
        }

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'month_calendar.tpl', $tpl_params);
    }

    public function categoryMonthly(string $legacyBaseDir, Request $request, int $category_id, string $date_type, string $view_type, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;

        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);
        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/category/%d/%s-monthly-%s', $category_id, $date_type, $view_type);

        if ($year = $request->get('year')) {
            $_SERVER['PATH_INFO'] .= '-' . $year;
        }

        if ($month = $request->get('month')) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }

        if ($day = $request->get('day')) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'month_calendar.tpl', $tpl_params);
    }

    public function categoryWeekly(string $legacyBaseDir, Request $request, int $category_id, string $date_type, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        
        $tpl_params = [];
        $legacy_file = sprintf('%s/index.php', $legacyBaseDir);
        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/category/%d/%s-weekly-list', $category_id, $date_type);
        
        if ($year = $request->get('year')) {
            $_SERVER['PATH_INFO'] .= '-' . $year;
        }

        if ($week = $request->get('week')) {
            $_SERVER['PATH_INFO'] .= '-' . $week;
        }

        if ($wday = $request->get('wday')) {
            $_SERVER['PATH_INFO'] .= '-' . $wday;
        }

        if ($start_id = $request->get('start_id')) {
            $_SERVER['PATH_INFO'] .= '/' . $start_id;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        return $this->doResponse($legacy_file, 'month_calendar.tpl', $tpl_params);
    }
}
