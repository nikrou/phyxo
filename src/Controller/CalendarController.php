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
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Calendar\CalendarMonthly;
use Phyxo\Image\ImageStandardParams;
use App\DataMapper\ImageMapper;
use App\Repository\AlbumRepository;
use App\Repository\ImageRepository;
use Phyxo\Calendar\CalendarWeekly;
use Phyxo\Functions\Utils;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarController extends CommonController
{
    public function categoriesMonthly(Request $request, string $date_type, string $view_type, Conf $conf, ImageRepository $imageRepository, AlbumRepository $albumRepository,
                                    MenuBar $menuBar, ImageMapper $imageMapper, ImageStandardParams $image_std_params, int $start = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $tpl_params['START_ID'] = $start;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $this->image_std_params = $image_std_params;

        $tpl_params['PAGE_TITLE'] = 'Calendar';

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['chronology_views'] = [
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'list']),
                'CONTENT' => $translator->trans('chronology_monthly_list'),
                'SELECTED' => $view_type === 'list',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'calendar']),
                'CONTENT' => $translator->trans('chronology_monthly_calendar'),
                'SELECTED' => $view_type === 'calendar',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_weekly', ['date_type' => $date_type]),
                'CONTENT' => $translator->trans('chronology_weekly_list'),
                'SELECTED' => false,
            ],
        ];

        $calendar = new CalendarMonthly($imageRepository, $albumRepository, $date_type);
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
        $calendar->setViewType($view_type);
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByCondition($this->getUser()->getForbiddenCategories());

        $category_content = $calendar->generateCategoryContent();
        if (!empty($category_content)) {
            $tpl_params['items'] = [];
            $tpl_params = array_merge($tpl_params, $category_content);
        }

        if (empty($category_content['chronology_calendar'])) {
            $tpl_params['items'] = $calendar->getItems($this->orderByToSorts($conf['order_by']));

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

        // @TODO : better display in template
        $tmp = '<a href="' . $this->generateUrl('homepage') . '">' . $translator->trans('Home') . '</a>';

        foreach ($calendar->getBreadcrumb('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => $view_type]) as $part) {
            $tmp .= ' / ';
            if (!empty($part['url'])) {
                $tmp .= '<a href="' . $part['url'] . '">' . $part['label'] . '</a>';
            } else {
                $tmp .= $translator->trans($part['label']);
            }
        }
        $tpl_params['TITLE'] = $tmp;

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('month_calendar.html.twig', $tpl_params);
    }

    public function categoriesWeekly(Request $request, string $date_type, int $week = 0, Conf $conf, MenuBar $menuBar, ImageRepository $imageRepository, AlbumRepository $albumRepository,
                                    ImageStandardParams $image_std_params, ImageMapper $imageMapper, int $start = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $tpl_params['START_ID'] = $start;

        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['chronology_views'] = [
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'list']),
                'CONTENT' => $translator->trans('chronology_monthly_list'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_monthly', ['date_type' => $date_type, 'view_type' => 'calendar']),
                'CONTENT' => $translator->trans('chronology_monthly_calendar'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_categories_weekly', ['date_type' => $date_type]),
                'CONTENT' => $translator->trans('chronology_weekly_list'),
                'SELECTED' => true,
            ],
        ];

        $filter = [];
        $calendar = new CalendarWeekly($imageRepository, $albumRepository, $date_type);
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
        $calendar->setViewType('list');
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByCondition($this->getUser()->getForbiddenCategories());

        $category_content = $calendar->generateCategoryContent();
        if (!empty($category_content)) {
            $tpl_params['items'] = [];
            $tpl_params = array_merge($tpl_params, $category_content);
        }

        if (empty($category_content['chronology_calendar'])) {
            $tpl_params['items'] = $calendar->getItems($this->orderByToSorts($conf['order_by']));

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

        $tpl_params['TITLE'] = $calendar->getBreadcrumb('calendar_categories_weekly', ['date_type' => $date_type]); // @TODO: label is not translated

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('month_calendar.html.twig', $tpl_params);
    }

    public function categoryMonthly(Request $request, int $category_id, string $date_type, string $view_type, Conf $conf, MenuBar $menuBar, ImageRepository $imageRepository,
                            AlbumRepository $albumRepository, ImageStandardParams $image_std_params, ImageMapper $imageMapper, int $start = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $tpl_params['START_ID'] = $start;

        $this->image_std_params = $image_std_params;

        $tpl_params['PAGE_TITLE'] = 'Calendar';

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['chronology_views'] = [
            [
                'VALUE' => $this->generateUrl('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => 'list', 'category_id' => $category_id]),
                'CONTENT' => $translator->trans('chronology_monthly_list'),
                'SELECTED' => $view_type === 'list',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => 'calendar', 'category_id' => $category_id]),
                'CONTENT' => $translator->trans('chronology_monthly_calendar'),
                'SELECTED' => $view_type === 'calendar',
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_weekly', ['date_type' => $date_type, 'category_id' => $category_id]),
                'CONTENT' => $translator->trans('chronology_weekly_list'),
                'SELECTED' => false,
            ],
        ];

        $calendar = new CalendarMonthly($imageRepository, $albumRepository, $date_type);
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
        $calendar->setViewType($view_type);
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByConditionAndCategory($category_id, $this->getUser()->getForbiddenCategories());


        $category_content = $calendar->generateCategoryContent();
        if (!empty($category_content)) {
            $tpl_params['items'] = [];
            $tpl_params = array_merge($tpl_params, $category_content);
        }

        if (empty($category_content['chronology_calendar'])) {
            $tpl_params['items'] = $calendar->getItems($this->orderByToSorts($conf['order_by']));

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

        $tpl_params['TITLE'] = $calendar->getBreadcrumb('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => $view_type, 'category_id' => $category_id]); // @TODO: label is not translated

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('month_calendar.html.twig', $tpl_params);
    }

    public function categoryWeekly(Request $request, int $category_id, string $date_type, int $week, Conf $conf, ImageRepository $imageRepository, AlbumRepository $albumRepository,
                                    MenuBar $menuBar, ImageStandardParams $image_std_params, ImageMapper $imageMapper, int $start = 0, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $tpl_params['START_ID'] = $start;

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
                'CONTENT' => $translator->trans('chronology_monthly_list'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_monthly', ['date_type' => $date_type, 'view_type' => 'calendar', 'category_id' => $category_id]),
                'CONTENT' => $translator->trans('chronology_monthly_calendar'),
                'SELECTED' => false,
            ],
            [
                'VALUE' => $this->generateUrl('calendar_category_weekly', ['date_type' => $date_type, 'category_id' => $category_id]),
                'CONTENT' => $translator->trans('chronology_weekly_list'),
                'SELECTED' => true,
            ],
        ];

        $filter = [];
        $calendar = new CalendarWeekly($imageRepository, $albumRepository, $date_type);
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
        $calendar->setViewType('list');
        $calendar->setImageStandardParams($image_std_params);
        $calendar->findByConditionAndCategory($category_id, $this->getUser()->getForbiddenCategories());

        if ($chronology_params = $calendar->generateCategoryContent()) {
            $tpl_params['items'] = [];
            $tpl_params = array_merge($tpl_params, $chronology_params);
        } else {
            $tpl_params['items'] = $calendar->getItems($this->orderByToSorts($conf['order_by']));

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

        $tpl_params['TITLE'] = $calendar->getBreadcrumb('calendar_category_weekly', ['date_type' => $date_type, 'category_id' => $category_id]); // @TODO: label is not translated

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('month_calendar.html.twig', $tpl_params);
    }

    protected function orderByToSorts(string $order_by): array
    {
        $sorts = explode(',', str_ireplace('order by', '', $order_by));
        $sorts = array_map(
            function($sort) {
                return explode(' ', $sort);
            },
            $sorts
        );

        return $sorts;
    }
}
