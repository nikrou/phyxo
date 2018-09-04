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
    public function index(Request $request, $date_type, $type, $year, $month = null, $day = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/%s-monthly-%s-%d', $date_type, $type, $year);
        if (!is_null($month)) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }

        if (!is_null($day)) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }

    public function categories(Request $request, $date_type, $type, $year, $month = null, $day = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = sprintf('/categories/%s-monthly-%s-%d', $date_type, $type, $year);
        if (!is_null($month)) {
            $_SERVER['PATH_INFO'] .= '-' . $month;
        }

        if (!is_null($day)) {
            $_SERVER['PATH_INFO'] .= '-' . $day;
        }

        return $this->doResponse($legacy_file, 'month_calendar.tpl');
    }

    public function details(Request $request, $date_type, $time_params = null, $extra_params = null)
    {
        $legacy_file = sprintf('%s/index.php', $this->container->getParameter('legacy_base_dir'));

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $_SERVER['PATH_INFO'] = '/' . $type . '-monthly-calendar';
        if (!is_null($time_params)) {
            $_SERVER['PATH_INFO'] .= "/$time_params";
        }
        if (!is_null($extra_params)) {
            $_SERVER['PATH_INFO'] .= "/$extra_params";
        }

        return $this->doResponse($legacy_file, 'thumbnails.tpl');
    }
}
