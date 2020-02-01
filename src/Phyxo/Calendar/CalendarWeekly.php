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

/**
 * Weekly calendar style (composed of years/week in years and days in week)
 */
class CalendarWeekly extends CalendarBase
{
    const CYEAR = 0, CWEEK = 1, CDAY = 2;

    protected $parts = [self::CYEAR => 'year', self::CWEEK => 'week', self::CDAY => 'wday'];

    protected $week;

    protected $calendar_type = 'weekly';

    public function setWeek(int $week)
    {
        $this->week = $week;
    }

    public function getCalendarLevels(): array
    {
        $week_no_labels = [];
        for ($i = 1; $i <= 53; $i++) {
            $week_no_labels[$i] = sprintf('Week %d', $i);
        }

        $calendar_levels = [
            [
                'sql' => $this->conn->db_get_year($this->date_field),
                'labels' => []
            ],
            [
                'sql' => $this->conn->db_get_week($this->date_field) . '+1',
                'labels' => $week_no_labels,
            ],
            [
                'sql' => $this->conn->db_get_dayofweek($this->date_field) . '-1',
                'labels' => $this->days
            ],
        ];

        //Comment next lines for week starting on Sunday or if MySQL version<4.0.17
        //WEEK(date,5) = "0-53 - Week 1=the first week with a Monday in this year"
        if ($this->conf['week_starts_on'] === 'monday') {
            $calendar_levels[self::CWEEK]['sql'] = $this->conn->db_get_week($this->date_field, 5) . '+1';
            $calendar_levels[self::CDAY]['sql'] = $this->conn->db_get_weekday($this->date_field);
            $calendar_levels[self::CDAY]['labels'][] = array_shift($calendar_levels[self::CDAY]['labels']);
        }

        return $calendar_levels;
    }

    protected function urlFromDateComponents($item, array $date_components = []): string
    {
        $route = '';
        $params = [
            'date_type' => $this->date_type,
        ];

        if (count($date_components) === 2) {
            if (!is_null($this->category_id)) {
                $route = 'calendar_category_weekly_year_week_wday';
                $params['category_id'] = $this->category_id;
            } else {
                $route = 'calendar_categories_weekly_year_week_wday';
            }

            $params = array_merge($params, [
                'year' => $date_components[0],
                'week' => $date_components[1],
                'wday' => $item
            ]);
        } elseif (count($date_components) === 1) {
            if (!is_null($this->category_id)) {
                $params['category_id'] = $this->category_id;
                $route = 'calendar_category_weekly_year_week';
            } else {
                $route = 'calendar_categories_weekly_year_week';
            }
            $params = array_merge($params, [
                'year' => $date_components[0],
                'week' => $item,
            ]);
        } else {
            if (!is_null($this->category_id)) {
                $params['category_id'] = $this->category_id;
                $route = 'calendar_category_weekly_year';
            } else {
                $route = 'calendar_categories_weekly_year';
            }
            $params['year'] = $item;
        }

        return $this->router->generate($route, $params);
    }

    public function getNextPrevUrl(array $date_components = []): string
    {
        $route = '';
        $params = [
            'date_type' => $this->date_type,
            'year' => $date_components[0],
        ];

        if (count($date_components) === 3) {
            if (!is_null($this->category_id)) {
                $params['category_id'] = $this->category_id;
                $route = 'calendar_categories_weekly_year_week_wday';
            } else {
                $route = 'calendar_categories_weekly_year_week_wday';
            }
            $params['week'] = $date_components[1];
            $params['wday'] = $date_components[2];
        } elseif (count($date_components) === 2) {
            if (!is_null($this->category_id)) {
                $params['category_id'] = $this->category_id;
                $route = 'calendar_categories_weekly_year_week';
            } else {
                $route = 'calendar_categories_weekly_year_week';
            }
            $params['week'] = $date_components[1];
        } elseif (count($date_components) === 1) {
            if (!is_null($this->category_id)) {
                $params['category_id'] = $this->category_id;
                $route = 'calendar_categories_weekly_year';
            } else {
                $route = 'calendar_categories_weekly_year';
            }
        }

        return $this->router->generate($route, $params);
    }

    /**
     * Generate navigation bars for category page.
     */
    public function generateCategoryContent(): array
    {
        $tpl_params = [];

        if (count($this->chronology_date) === 0) {
            $tpl_params['chronology_navigation_bars'][] = $this->buildNavigationBar(self::CYEAR); // years
        }

        if (count($this->chronology_date) === 1) {
            $tpl_params['chronology_navigation_bars'][] = $this->buildNavigationBar(self::CWEEK); // week nav bar 1-53
        }

        if (count($this->chronology_date) === 2) {
            $tpl_params['chronology_navigation_bars'][] = $this->buildNavigationBar(self::CDAY, $this->getCalendarLevels()[self::CDAY]['labels']); // days nav bar Mon-Sun
        }

        $tpl_params = array_merge($tpl_params, $this->buildNextPrev($tpl_params));

        return $tpl_params;
    }

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     */
    function getDateWhere(int $max_levels = 3): string
    {
        $date = $this->chronology_date;
        while (count($date) > $max_levels) {
            array_pop($date);
        }
        $res = '';
        if (isset($date[self::CYEAR]) and $date[self::CYEAR] !== 'any') {
            $y = $date[self::CYEAR];
            $res = " AND $this->date_field BETWEEN '$y-01-01' AND '$y-12-31 23:59:59'";
        }

        if (isset($date[self::CWEEK]) and $date[self::CWEEK] !== 'any') {
            $res .= ' AND ' . $this->getCalendarLevels()[self::CWEEK]['sql'] . '=' . $date[self::CWEEK];
        }
        if (isset($date[self::CDAY]) and $date[self::CDAY] !== 'any') {
            $res .= ' AND ' . $this->getCalendarLevels()[self::CDAY]['sql'] . '=' . $date[self::CDAY];
        }
        if (empty($res)) {
            $res = ' AND ' . $this->date_field . ' IS NOT NULL';
        }

        return $res;
    }
}
