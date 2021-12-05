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

namespace Phyxo\Calendar;

use Phyxo\Calendar\CalendarBase;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;

/**
 * Monthly calendar style (composed of years/months and days)
 */
class CalendarMonthly extends CalendarBase
{
    const CYEAR = 0, CMONTH = 1, CDAY = 2;

    protected $parts = [self::CYEAR => 'year', self::CMONTH => 'month', self::CDAY => 'day'];

    protected $calendar_type = 'monthly';

    protected function urlFromDateComponents($item, array $date_components = []): string
    {
        $route = '';
        $params = [
            'date_type' => $this->date_type,
            'view_type' => $this->view_type,
        ];

        if (count($date_components) === 2) {
            if (!is_null($this->category_id)) {
                $route = 'calendar_category_monthly_year_month_day';
                $params['category_id'] = $this->category_id;
            } else {
                $route = 'calendar_categories_monthly_year_month_day';
            }

            $params = array_merge($params, [
                'year' => $date_components[0],
                'month' => $date_components[1],
                'day' => $item
            ]);
        } elseif (count($date_components) === 1) {
            if (!is_null($this->category_id)) {
                $route = 'calendar_category_monthly_year_month';
                $params['category_id'] = $this->category_id;
            } else {
                $route = 'calendar_categories_monthly_year_month';
            }

            $params = array_merge($params, [
                'year' => $date_components[0],
                'month' => $item,
            ]);
        } else {
            if (!is_null($this->category_id)) {
                $route = 'calendar_category_monthly_year';
                $params['category_id'] = $this->category_id;
            } else {
                $route = 'calendar_categories_monthly_year';
            }

            $params['year'] = $item;
        }

        return $this->router->generate($route, $params);
    }

    public function getNextPrevUrl(array $date_components = []): string
    {
        $params = [
            'date_type' => $this->date_type,
            'view_type' => $this->view_type,
            'year' => $date_components[0],
            'month' => $date_components[1]
        ];

        if (count($date_components) === 3) {
            if (!is_null($this->category_id)) {
                $route = 'calendar_category_monthly_year_month_day';
                $params['category_id'] = $this->category_id;
            } else {
                $route = 'calendar_categories_monthly_year_month';
            }
            $params['day'] = $date_components[2];

            return $this->router->generate($route, $params);
        } elseif (count($date_components) === 2) {
            if (!is_null($this->category_id)) {
                $route = 'calendar_category_monthly_year_month';
                $params['category_id'] = $this->category_id;
            } else {
                $route = 'calendar_categories_monthly_year_month';
            }

            return $this->router->generate($route, $params);
        }

        return '';
    }

    public function getCalendarLevels(): array
    {
        return  [
            [
                'sql' => $this->date_field, // year
                'labels' => []
            ],
            [
                'sql' => $this->date_field, // month
                'labels' => $this->months
            ],
            [
                'sql' => $this->date_field, // dayofmonth
                'labels' => []
            ],
        ];
    }

    /**
     * Generate navigation bars for category page.
     */
    public function generateCategoryContent(): array
    {
        $tpl_params = [];

        if ($this->view_type === self::CAL_VIEW_CALENDAR) {
            $tpl_var = [];
            if (count($this->chronology_date) === 0) { //case A: no year given - display all years+months
                if (!empty($chronology_calendar = $this->buildGlobalCalendar())) {
                    $tpl_params['chronology_calendar'] = $chronology_calendar;
                    unset($chronology_calendar);

                    return $tpl_params;
                }
            }

            if (count($this->chronology_date) === 1) { //case B: year given - display all days in given year
                if (!empty($chronology_calendar = $this->buildYearCalendar())) {
                    $tpl_params['chronology_calendar'] = $chronology_calendar;
                    unset($chronology_calendar);

                    $tpl_params['chronology_navigation_bars'][] = $this->buildNavigationBar(self::CYEAR); // years

                    return $tpl_params;
                }
            }

            if (count($this->chronology_date) === 2) { //case C: year+month given - display a nice month calendar
                if (!empty($chronology_calendar = $this->buildMonthCalendar())) {
                    $tpl_params['chronology_calendar'] = $chronology_calendar;
                    unset($chronology_calendar);
                }
                $tpl_params = array_merge($tpl_params, $this->buildNextPrev($tpl_params));

                return $tpl_params;
            }
        }

        if ($this->view_type === self::CAL_VIEW_LIST || count($this->chronology_date) === 3) {
            if (count($this->chronology_date) === 0) {
                $tpl_params['chronology_navigation_bars'][] = $this->buildNavigationBar(self::CYEAR); // years
            }

            if (count($this->chronology_date) === 1) {
                $tpl_params['chronology_navigation_bars'][] = $this->buildNavigationBar(self::CMONTH, $this->getCalendarLevels()[self::CMONTH]['labels']); // month
            }

            if (count($this->chronology_date) === 2) {
                $day_labels = range(1, $this->getAllDaysInMonth($this->chronology_date[self::CYEAR], $this->chronology_date[self::CMONTH]));
                array_unshift($day_labels, 0);
                unset($day_labels[0]);
                $tpl_params['chronology_navigation_bars'][] = $this->buildNavigationBar(self::CDAY, $day_labels); // days
            }
            $tpl_params = array_merge($tpl_params, $this->buildNextPrev($tpl_params));
        }

        return $tpl_params;
    }

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     */
    public function getDateWhere($max_levels = 3): string
    {
        $calendar_levels = $this->getCalendarLevels();

        $date = $this->chronology_date;
        while (count($date) > $max_levels) {
            array_pop($date);
        }
        $res = '';
        if (isset($date[self::CYEAR]) && $date[self::CYEAR] !== 'any') {
            $b = $date[self::CYEAR] . '-';
            $e = $date[self::CYEAR] . '-';
            if (isset($date[self::CMONTH]) && $date[self::CMONTH] !== 'any') {
                $b .= sprintf('%02d-', $date[self::CMONTH]);
                $e .= sprintf('%02d-', $date[self::CMONTH]);
                if (isset($date[self::CDAY]) && $date[self::CDAY] !== 'any') {
                    $b .= sprintf('%02d', $date[self::CDAY]);
                    $e .= sprintf('%02d', $date[self::CDAY]);
                } else {
                    $b .= '01';
                    $e .= $this->getAllDaysInMonth($date[self::CYEAR], $date[self::CMONTH]);
                }
            } else {
                $b .= '01-01';
                $e .= '12-31';
                if (isset($date[self::CMONTH]) && $date[self::CMONTH] !== 'any') {
                    $res .= $calendar_levels[self::CMONTH]['sql'] . '=' . $date[self::CMONTH];
                }
                if (isset($date[self::CDAY]) && $date[self::CDAY] !== 'any') {
                    $res .= $calendar_levels[self::CDAY]['sql'] . '=' . $date[self::CDAY];
                }
            }
            $res = 'i.' . $this->date_field . " BETWEEN '$b' AND '$e 23:59:59'" . $res;
        } else {
            $res = 'i.' . $this->date_field . ' IS NOT NULL';
            if (isset($date[self::CMONTH]) && $date[self::CMONTH] !== 'any') {
                $res .= $calendar_levels[self::CMONTH]['sql'] . '=' . $date[self::CMONTH];
            }
            if (isset($date[self::CDAY]) && $date[self::CDAY] !== 'any') {
                $res .= $calendar_levels[self::CDAY]['sql'] . '=' . $date[self::CDAY];
            }
        }

        return $res;
    }

    /**
     * Returns an array with all the days in a given month.
     */
    protected function getAllDaysInMonth(int $year, int $month): int
    {
        // cannot use cal_days_in_month(CAL_GREGORIAN, $month, $year); because of params that can be 'any'

        $days_in_month = [1 => 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if (is_numeric($year) and $month == 2) {
            $nb_days = $days_in_month[2];
            if (($year % 4 == 0) && (($year % 100 != 0) || ($year % 400 != 0))) {
                $nb_days++;
            }
        } else {
            $nb_days = $days_in_month[$month];
        }

        return $nb_days;
    }

    /**
     * Build global calendar and retur the result
     */
    protected function buildGlobalCalendar(): array
    {
        $tpl_params = [];
        if (count($this->chronology_date) !== 0) {
            return [];
        }

        if ($this->find_by_items) {
            $rowImages = $this->imageRepository->findYYYYMMPeriodAndImagesCountByIds($this->items, $this->date_field, $this->getDateWhere());
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    $this->albumRepository->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $rowImages = $this->imageRepository->findYYYYMMPeriodAndImagesCount($this->date_field, $this->getDateWhere(), $this->forbidden_categories, $sub_ids);
        }

        $items = [];
        foreach ($rowImages as $image) {
            $y = $image['period']->format('Y');
            $m = $image['period']->format('m');
            if (!isset($items[$y])) {
                $items[$y] = ['nb_images' => 0, 'children' => []];
            }
            $items[$y]['children'][$m] = $image['count'];
            $items[$y]['nb_images'] += $image['count'];
        }

        if (count($items) == 1) { // only one year exists so bail out to year view
            list($y) = array_keys($items);
            $this->chronology_date[self::CYEAR] = $y;

            return [];
        }

        $params = [
            'date_type' => $this->date_type,
            'view_type' => $this->view_type,
        ];
        if (!is_null($this->category_id)) {
            $route = 'calendar_category_monthly_year';
            $params['category_id'] = $this->category_id;
        } else {
            $route = 'calendar_categories_monthly_year';
        }

        foreach ($items as $year => $year_data) {
            $chronology_date = [$year];
            $params['year'] = $year;
            $url = $this->router->generate($route, $params);

            $nav_bar = $this->getNavigationBarFromItems(
                $chronology_date,
                $year_data['children'],
                false,
                false,
                $this->months
            );

            $tpl_params['calendar_bars'][] = [
                'U_HEAD' => $url,
                'NB_IMAGES' => $year_data['nb_images'],
                'HEAD_LABEL' => $year,
                'items' => $nav_bar,
            ];
        }


        return $tpl_params;
    }

    /**
     * Build year calendar and return the result
     */
    protected function buildYearCalendar(): array
    {
        $tpl_params = [];

        if (count($this->chronology_date) !== 1) {
            return [];
        }

        if ($this->find_by_items) {
            $rowIamges = $this->imageRepository->findMMDDPeriodAndImagesCountByIds($this->items, $this->date_field, $this->getDateWhere());
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    $this->albumRepository->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $rowIamges = $this->imageRepository->findMMDDPeriodAndImagesCount($this->date_field, $this->getDateWhere(), $this->forbidden_categories, $sub_ids);
        }

        $items = [];
        foreach ($rowIamges as $image) {
            $m = $image['period']->format('m');
            $d = $image['period']->format('d');
            if (!isset($items[$m])) {
                $items[$m] = ['nb_images' => 0, 'children' => []];
            }
            $items[$m]['children'][$d] = $image['count'];
            $items[$m]['nb_images'] += $image['count'];
        }
        if (count($items) == 1) { // only one month exists so bail out to month view
            list($m) = array_keys($items);
            $this->chronology_date[self::CMONTH] = $m;

            return [];
        }

        $params = [
            'date_type' => $this->date_type,
            'view_type' => $this->view_type,
            'year' => $this->chronology_date[self::CYEAR],
        ];

        if (!is_null($this->category_id)) {
            $params['category_id'] = $this->category_id;
            $route = 'calendar_category_monthly_year_month';
        } else {
            $route = 'calendar_categories_monthly_year_month';
        }

        foreach ($items as $month => $month_data) {
            $chronology_date = [$this->chronology_date[self::CYEAR], $month];
            $params['month'] = $month;
            $url = $this->router->generate($route, $params);

            $nav_bar = $this->getNavigationBarFromItems(
                $chronology_date,
                $month_data['children'],
                false
            );

            $tpl_params['calendar_bars'][] = [
                'U_HEAD' => $url,
                'NB_IMAGES' => $month_data['nb_images'],
                'HEAD_LABEL' => $this->months[$month],
                'items' => $nav_bar,
            ];
        }

        return $tpl_params;
    }

    /**
     * Build month calendar and return the result
     */
    protected function buildMonthCalendar(): array
    {
        $tpl_params = [];
        if ($this->find_by_items) {
            $rowImages = $this->imageRepository->findDayOfMonthPeriodAndImagesCountByIds($this->items, $this->date_field, $this->getDateWhere());
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    $this->albumRepository->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $rowImages = $this->imageRepository->findDayOfMonthPeriodAndImagesCount($this->date_field, $this->getDateWhere(), $this->forbidden_categories, $sub_ids);
        }

        $items = [];
        foreach ($rowImages as $image) {
            $d = $image['period']->format('d');
            $items[$d] = ['nb_images' => $image['count']];
        }

        foreach ($items as $day => $data) {
            $this->chronology_date[self::CDAY] = $day;

            if ($this->find_by_items) {
                $image = $this->imageRepository->findOneRandomInWeekByIds($this->items, $this->getDateWhere());
            } else {
                if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                    $sub_ids = array_diff(
                        $this->albumRepository->getSubcatIds([$this->category_id]),
                        $this->forbidden_categories
                    );
                } else {
                    $sub_ids = [];
                }
                $image = $this->imageRepository->findOneRandomInWeek($this->getDateWhere(), $this->forbidden_categories, $sub_ids);
            }

            unset($this->chronology_date[self::CDAY]);

            $derivative = new DerivativeImage($image, $this->image_std_params->getByType(ImageStandardParams::IMG_SQUARE), $this->image_std_params);
            $items[$day]['image'] = $this->router->generate(
                'media',
                ['path' => $image->getPathBasename(), 'derivative' => $derivative->getUrlType(), 'image_extension' => $image->getExtension()]
            );
            $items[$day]['file'] = $image->getFile();
            $items[$day]['dow'] = $image->getDateAvailable()->format('N');
        }

        if (!empty($items)) {
            list($known_day) = array_keys($items);
            $known_dow = $items[$known_day]['dow'];
            $first_day_dow = ($known_dow - ($known_day - 1)) % 7;
            if ($first_day_dow < 0) {
                $first_day_dow += 7;
            }
            //first_day_dow = week day corresponding to the first day of this month
            $wday_labels = $this->days;

            if ($this->conf['week_starts_on'] === 'monday') {
                if ($first_day_dow == 0) {
                    $first_day_dow = 6;
                } else {
                    $first_day_dow -= 1;
                }

                $wday_labels[] = array_shift($wday_labels);
            }

            list($cell_width, $cell_height) = $this->image_std_params->getByType(ImageStandardParams::IMG_SQUARE)->sizing->ideal_size;

            $tpl_weeks = [];
            $tpl_crt_week = [];

            //fill the empty days in the week before first day of this month
            for ($i = 0; $i < $first_day_dow; $i++) {
                $tpl_crt_week[] = [];
            }

            $params = [
                'date_type' => $this->date_type,
                'view_type' => $this->view_type,
                'year' => $this->chronology_date[self::CYEAR],
                'month' => $this->chronology_date[self::CMONTH],
            ];
            if (!is_null($this->category_id)) {
                $route = 'calendar_category_monthly_year_month_day';
                $params['category_id'] = $this->category_id;
            } else {
                $route = 'calendar_categories_monthly_year_month_day';
            }

            $dow = 6;
            for ($day = 1; $day <= $this->getAllDaysInMonth($this->chronology_date[self::CYEAR], $this->chronology_date[self::CMONTH]); $day++) {
                $dow = ($first_day_dow + $day - 1) % 7;
                if ($dow == 0 and $day != 1) {
                    $tpl_weeks[] = $tpl_crt_week; // add finished week to week list
                    $tpl_crt_week = []; // start new week
                }

                if (!isset($items[$day])) { // empty day
                    $tpl_crt_week[] = ['DAY' => $day];
                } else {
                    $params['day'] = $day;
                    $url = $this->router->generate($route, $params);

                    $tpl_crt_week[] = [
                        'DAY' => $day,
                        'DOW' => $dow,
                        'NB_ELEMENTS' => $items[$day]['nb_images'],
                        'IMAGE' => $items[$day]['image'],
                        'U_IMG_LINK' => $url,
                        'IMAGE_ALT' => $items[$day]['file'],
                    ];
                }
            }
            //fill the empty days in the week after the last day of this month
            while ($dow < 6) {
                $tpl_crt_week[] = [];
                $dow++;
            }
            $tpl_weeks[] = $tpl_crt_week;

            $tpl_params['month_view'] = [
                'CELL_WIDTH' => $cell_width,
                'CELL_HEIGHT' => $cell_height,
                'wday_labels' => $wday_labels,
                'weeks' => $tpl_weeks,
            ];
        }

        return $tpl_params;
    }
}
