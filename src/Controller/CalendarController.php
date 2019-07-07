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
use Phyxo\Template\Template;
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Functions\Language;
use Phyxo\Calendar\CalendarMonthly;
use Phyxo\EntityManager;
use Phyxo\Image\ImageStandardParams;
use App\Repository\BaseRepository;
use App\DataMapper\ImageMapper;
use Phyxo\Calendar\CalendarWeekly;
use Phyxo\Functions\Utils;

class CalendarController extends CommonController
{
    public function categoriesMonthly(Request $request, string $date_type, string $view_type, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite,
                                        EntityManager $em, MenuBar $menuBar, ImageMapper $imageMapper, ImageStandardParams $image_std_params, int $start = 0)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $this->loadLanguage($this->getUser());
        $this->image_std_params = $image_std_params;

        $tpl_params['PAGE_TITLE'] = 'Calendar';

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['chronology_views'] = [
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'list']),
                'CONTENT' => Language::l10n('chronology_monthly_list'),
                'SELECTED' => $view_type === 'list',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'calendar']),
                'CONTENT' => Language::l10n('chronology_monthly_calendar'),
                'SELECTED' => $view_type === 'calendar',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_weekly', ['date_type' => $date_type]),
                'CONTENT' => Language::l10n('chronology_weekly_list'),
                'SELECTED' => false,
            ],
        ];

        $filter = [];
        $calendar = new CalendarMonthly($em->getConnection(), $date_type);
        $chronology_date = [];
        if ($year = $request->get('year')) {
            $chronology_date[] = $year;
        }

        if ($month = $request->get('month')) {
            $chronology_date[] = $month;
        }

        if ($day = $request->get('day')) {
            $chronology_date[] = $day;
        }
        $calendar->setChronologyDate($chronology_date);

        $calendar->setRouter($this->get('router'));
        $calendar->setConf($conf);
        $calendar->setTemplate($template);
        $calendar->setViewType($view_type);
        $calendar->setLang($this->language_load['lang']);
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByCondition(
            $em->getRepository(BaseRepository::class)->getSQLConditionFandF(
                $this->getUser(),
                $filter,
                [

                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id'
                ],
                '',
                true
            )
        );

        if ($calendar->generateCategoryContent()) {
            $tpl_params['items'] = [];
        } else {
            $order_by = $conf['order_by'];
            $tpl_params['items'] = $calendar->getItems($order_by);

            if (count($tpl_params['items']) > 0) {
                $nb_image_page = $this->getUser()->getNbImagePage();

                $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'calendar_categories_monthly',
                    ['date_type' => $date_type, 'view_type' => $view_type],
                    count($tpl_params['items']),
                    $start,
                    $nb_image_page,
                    $conf['paginate_pages_around']
                );

                $tpl_params = array_merge(
                    $tpl_params,
                    $imageMapper->getPicturesFromSelection(
                        array_slice($tpl_params['items'], $start, $nb_image_page),
                        '',
                        'calendar_categories',
                        $start
                    )
                );
            }
        }

        $tpl_params['TITLE'] = $calendar->getBreadcrumb('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => $view_type]);

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('month_calendar.tpl', $tpl_params);
    }

    public function categoriesWeekly(Request $request, string $date_type, int $week = 0, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar,
                                    ImageStandardParams $image_std_params, EntityManager $em, ImageMapper $imageMapper, int $start = 0)
    {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;
        $this->loadLanguage($this->getUser());

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['chronology_views'] = [
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'list']),
                'CONTENT' => Language::l10n('chronology_monthly_list'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'calendar']),
                'CONTENT' => Language::l10n('chronology_monthly_calendar'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_weekly', ['date_type' => $date_type]),
                'CONTENT' => Language::l10n('chronology_weekly_list'),
                'SELECTED' => true,
            ],
        ];

        $filter = [];
        $calendar = new CalendarWeekly($em->getConnection(), $date_type);
        $chronology_date = [];
        if ($year = $request->get('year')) {
            $chronology_date[] = $year;
        }

        if ($week = $request->get('week')) {
            $chronology_date[] = $week;
        }

        if ($wday = $request->get('wday')) {
            $chronology_date[] = $wday;
        }
        $calendar->setChronologyDate($chronology_date);
        if ($week) {
            $calendar->setWeek($week);
        }

        $calendar->setRouter($this->get('router'));
        $calendar->setConf($conf);
        $calendar->setTemplate($template);
        $calendar->setViewType('list');
        $calendar->setLang($this->language_load['lang']);
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByCondition(
            $em->getRepository(BaseRepository::class)->getSQLConditionFandF(
                $this->getUser(),
                $filter,
                [

                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id'
                ],
                '',
                true
            )
        );

        if ($calendar->generateCategoryContent()) {
            $tpl_params['items'] = [];
        } else {
            $order_by = $conf['order_by'];
            $tpl_params['items'] = $calendar->getItems($order_by);

            if (count($tpl_params['items']) > 0) {
                $nb_image_page = $this->getUser()->getNbImagePage();

                $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'calendar_categories_weekly',
                    ['date_type' => $date_type],
                    count($tpl_params['items']),
                    $start,
                    $nb_image_page,
                    $conf['paginate_pages_around']
                );

                $tpl_params = array_merge(
                    $tpl_params,
                    $imageMapper->getPicturesFromSelection(
                        array_slice($tpl_params['items'], $start, $nb_image_page),
                        3,
                        'calendar_categories',
                        $start
                    )
                );
            }
        }

        $tpl_params['TITLE'] = $calendar->getBreadcrumb('calendar_categories_weekly', ['date_type' => $date_type]);

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('month_calendar.tpl', $tpl_params);
    }

    public function categoryMonthly(Request $request, int $category_id, string $date_type, string $view_type, Template $template, Conf $conf, string $themesDir, string $phyxoVersion,
                                    string $phyxoWebsite, MenuBar $menuBar, ImageStandardParams $image_std_params, EntityManager $em, ImageMapper $imageMapper, int $start = 0)
    {
        $tpl_params = [];

        $this->loadLanguage($this->getUser());
        $this->image_std_params = $image_std_params;

        $tpl_params['PAGE_TITLE'] = 'Calendar';

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['chronology_views'] = [
            [
                'VALUE' => $this->generateUrl('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => 'list', 'category_id' => $category_id]),
                'CONTENT' => Language::l10n('chronology_monthly_list'),
                'SELECTED' => $view_type === 'list',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => 'calendar', 'category_id' => $category_id]),
                'CONTENT' => Language::l10n('chronology_monthly_calendar'),
                'SELECTED' => $view_type === 'calendar',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_weekly', ['date_type' => $date_type, 'category_id' => $category_id]),
                'CONTENT' => Language::l10n('chronology_weekly_list'),
                'SELECTED' => false,
            ],
        ];

        $filter = [];
        $calendar = new CalendarMonthly($em->getConnection(), $date_type);
        $chronology_date = [];
        if ($year = $request->get('year')) {
            $chronology_date[] = $year;
        }

        if ($month = $request->get('month')) {
            $chronology_date[] = $month;
        }

        if ($day = $request->get('day')) {
            $chronology_date[] = $day;
        }
        $calendar->setChronologyDate($chronology_date);

        $calendar->setRouter($this->get('router'));
        $calendar->setConf($conf);
        $calendar->setTemplate($template);
        $calendar->setViewType($view_type);
        $calendar->setLang($this->language_load['lang']);
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByConditionAndCategory(
            $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_images' => 'id'], 'AND', false),
            $category_id,
            $this->getUser()->getForbiddenCategories()
        );

        if ($calendar->generateCategoryContent()) {
            $tpl_params['items'] = [];
        } else {
            $order_by = $conf['order_by'];
            $tpl_params['items'] = $calendar->getItems($order_by);

            if (count($tpl_params['items']) > 0) {
                $nb_image_page = $this->getUser()->getNbImagePage();

                $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'calendar_category_monthly',
                    ['date_type' => $date_type, 'view_type' => $view_type, 'category_id' => $category_id],
                    count($tpl_params['items']),
                    $start,
                    $nb_image_page,
                    $conf['paginate_pages_around']
                );

                $tpl_params = array_merge(
                    $tpl_params,
                    $imageMapper->getPicturesFromSelection(
                        array_slice($tpl_params['items'], $start, $nb_image_page),
                        '',
                        'calendar_category',
                        $start
                    )
                );
            }
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['TITLE'] = $calendar->getBreadcrumb('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => $view_type, 'category_id' => $category_id]);

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('month_calendar.tpl', $tpl_params);
    }

    public function categoryWeekly(Request $request, int $category_id, string $date_type, int $week, Template $template, Conf $conf, string $themesDir, string $phyxoVersion, string $phyxoWebsite,
                                    MenuBar $menuBar, ImageStandardParams $image_std_params, ImageMapper $imageMapper, EntityManager $em, int $start = 0)
    {
        $tpl_params = [];
        $this->loadLanguage($this->getUser());
        $this->image_std_params = $image_std_params;

        $tpl_params['PAGE_TITLE'] = 'Calendar';

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $chronology_date = [];
        if ($year = $request->get('year')) {
            $chronology_date[] = $year;
        }

        if ($month = $request->get('month')) {
            $chronology_date[] = $month;
        }

        if ($day = $request->get('day')) {
            $chronology_date[] = $day;
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['chronology_views'] = [
            [
                'VALUE' => $this->generateUrl('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => 'list', 'category_id' => $category_id]),
                'CONTENT' => Language::l10n('chronology_monthly_list'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => 'calendar', 'category_id' => $category_id]),
                'CONTENT' => Language::l10n('chronology_monthly_calendar'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_weekly', ['date_type' => $date_type, 'category_id' => $category_id]),
                'CONTENT' => Language::l10n('chronology_weekly_list'),
                'SELECTED' => true,
            ],
        ];

        $filter = [];
        $calendar = new CalendarWeekly($em->getConnection(), $date_type);
        $chronology_date = [];
        if ($year = $request->get('year')) {
            $chronology_date[] = $year;
        }

        if ($week = $request->get('week')) {
            $chronology_date[] = $week;
        }

        if ($wday = $request->get('wday')) {
            $chronology_date[] = $wday;
        }
        $calendar->setChronologyDate($chronology_date);
        if ($week) {
            $calendar->setWeek($week);
        }
        $calendar->setRouter($this->get('router'));
        $calendar->setConf($conf);
        $calendar->setTemplate($template);
        $calendar->setViewType('list');
        $calendar->setLang($this->language_load['lang']);
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByConditionAndCategory(
            $em->getRepository(BaseRepository::class)->getSQLConditionFandF($this->getUser(), $filter, ['visible_images' => 'id'], 'AND', false),
            $category_id,
            $this->getUser()->getForbiddenCategories()
        );

        if ($calendar->generateCategoryContent()) {
            $tpl_params['items'] = [];
        } else {
            $order_by = $conf['order_by'];
            $tpl_params['items'] = $calendar->getItems($order_by);

            if (count($tpl_params['items']) > 0) {
                $nb_image_page = $this->getUser()->getNbImagePage();

                $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                    $this->get('router'),
                    'calendar_category_monthly',
                    ['date_type' => $date_type, 'view_type' => 'list', 'category_id' => $category_id],
                    count($tpl_params['items']),
                    $start,
                    $nb_image_page,
                    $conf['paginate_pages_around']
                );

                $tpl_params = array_merge(
                    $tpl_params,
                    $imageMapper->getPicturesFromSelection(
                        array_slice($tpl_params['items'], $start, $nb_image_page),
                        3,
                        'calendar_category',
                        $start
                    )
                );
            }
        }

        $tpl_params['TITLE'] = $calendar->getBreadcrumb('calendar_category_weekly', ['date_type' => $date_type, 'category_id' => $category_id]);

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('month_calendar.tpl', $tpl_params);
    }
}
