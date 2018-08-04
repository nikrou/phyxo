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

    /**
     * Initialize the calendar
     * @param string $inner_sql
     */
    public function initialize($inner_sql)
    {
        global $lang, $conf, $conn;

        parent::initialize($inner_sql);
        $week_no_labels = array();
        for ($i = 1; $i <= 53; $i++) {
            $week_no_labels[$i] = \Phyxo\Functions\Language::l10n('Week %d', $i);
        }

        $this->calendar_levels = array(
            array(
                'sql' => $conn->db_get_year($this->date_field),
                'labels' => null
            ),
            array(
                'sql' => $conn->db_get_week($this->date_field) . '+1',
                'labels' => $week_no_labels,
            ),
            array(
                'sql' => $conn->db_get_dayofweek($this->date_field) . '-1',
                'labels' => $lang['day']
            ),
        );
        //Comment next lines for week starting on Sunday or if MySQL version<4.0.17
        //WEEK(date,5) = "0-53 - Week 1=the first week with a Monday in this year"
        if ('monday' == $conf['week_starts_on']) {
            $this->calendar_levels[self::CWEEK]['sql'] = $conn->db_get_week($this->date_field, 5) . '+1';
            $this->calendar_levels[self::CDAY]['sql'] = $conn->db_get_weekday($this->date_field);
            $this->calendar_levels[self::CDAY]['labels'][] = array_shift($this->calendar_levels[self::CDAY]['labels']);
        }
    }

    /**
     * Generate navigation bars for category page.
     *
     * @return boolean false indicates that thumbnails where not included
     */
    public function generate_category_content()
    {
        global $conf, $page;

        if (count($page['chronology_date']) == 0) {
            $this->build_nav_bar(self::CYEAR); // years
        }
        if (count($page['chronology_date']) == 1) {
            $this->build_nav_bar(self::CWEEK, array()); // week nav bar 1-53
        }
        if (count($page['chronology_date']) == 2) {
            $this->build_nav_bar(self::CDAY); // days nav bar Mon-Sun
        }
        $this->build_next_prev();

        return false;
    }

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     * @return string
     */
    function get_date_where($max_levels = 3)
    {
        global $page;

        $date = $page['chronology_date'];
        while (count($date) > $max_levels) {
            array_pop($date);
        }
        $res = '';
        if (isset($date[self::CYEAR]) and $date[self::CYEAR] !== 'any') {
            $y = $date[self::CYEAR];
            $res = " AND $this->date_field BETWEEN '$y-01-01' AND '$y-12-31 23:59:59'";
        }

        if (isset($date[self::CWEEK]) and $date[self::CWEEK] !== 'any') {
            $res .= ' AND ' . $this->calendar_levels[self::CWEEK]['sql'] . '=' . $date[self::CWEEK];
        }
        if (isset($date[self::CDAY]) and $date[self::CDAY] !== 'any') {
            $res .= ' AND ' . $this->calendar_levels[self::CDAY]['sql'] . '=' . $date[self::CDAY];
        }
        if (empty($res)) {
            $res = ' AND ' . $this->date_field . ' IS NOT NULL';
        }
        return $res;
    }
}
