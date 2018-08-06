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
 * Monthly calendar style (composed of years/months and days)
 */
class CalendarMonthly extends CalendarBase
{
    const CYEAR = 0, CMONTH = 1, CDAY = 2;

    /**
     * Initialize the calendar.
     * @param string $inner_sql
     */
    public function initialize($inner_sql)
    {
        global $lang, $conn;

        parent::initialize($inner_sql);
        $this->calendar_levels = array(
            array(
                'sql' => $conn->db_get_year($this->date_field),
                'labels' => null
            ),
            array(
                'sql' => $conn->db_get_month($this->date_field),
                'labels' => $lang['month']
            ),
            array(
                'sql' => $conn->db_get_dayofmonth($this->date_field),
                'labels' => null
            ),
        );
    }

    /**
     * Generate navigation bars for category page.
     *
     * @return boolean false indicates that thumbnails where not included
     */
    public function generate_category_content()
    {
        global $conf, $page, $template;

        $view_type = $page['chronology_view'];
        if ($view_type == CAL_VIEW_CALENDAR) {
            $tpl_var = array();
            if (count($page['chronology_date']) == 0) { //case A: no year given - display all years+months
                if ($this->build_global_calendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                    return true;
                }
            }

            if (count($page['chronology_date']) == 1) { //case B: year given - display all days in given year
                if ($this->build_year_calendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                    $this->build_nav_bar(self::CYEAR); // years
                    return true;
                }
            }

            if (count($page['chronology_date']) == 2) { //case C: year+month given - display a nice month calendar
                if ($this->build_month_calendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                }
                $this->build_next_prev();
                return true;
            }
        }

        if ($view_type == CAL_VIEW_LIST or count($page['chronology_date']) == 3) {
            if (count($page['chronology_date']) == 0) {
                $this->build_nav_bar(self::CYEAR); // years
            }
            if (count($page['chronology_date']) == 1) {
                $this->build_nav_bar(self::CMONTH); // month
            }
            if (count($page['chronology_date']) == 2) {
                $day_labels = range(1, $this->get_all_days_in_month($page['chronology_date'][self::CYEAR], $page['chronology_date'][self::CMONTH]));
                array_unshift($day_labels, 0);
                unset($day_labels[0]);
                $this->build_nav_bar(self::CDAY, $day_labels); // days
            }
            $this->build_next_prev();
        }
        return false;
    }

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     * @return string
     */
    public function get_date_where($max_levels = 3)
    {
        global $page;

        $date = $page['chronology_date'];
        while (count($date) > $max_levels) {
            array_pop($date);
        }
        $res = '';
        if (isset($date[self::CYEAR]) and $date[self::CYEAR] !== 'any') {
            $b = $date[self::CYEAR] . '-';
            $e = $date[self::CYEAR] . '-';
            if (isset($date[self::CMONTH]) and $date[self::CMONTH] !== 'any') {
                $b .= sprintf('%02d-', $date[self::CMONTH]);
                $e .= sprintf('%02d-', $date[self::CMONTH]);
                if (isset($date[self::CDAY]) and $date[self::CDAY] !== 'any') {
                    $b .= sprintf('%02d', $date[self::CDAY]);
                    $e .= sprintf('%02d', $date[self::CDAY]);
                } else {
                    $b .= '01';
                    $e .= $this->get_all_days_in_month($date[self::CYEAR], $date[self::CMONTH]);
                }
            } else {
                $b .= '01-01';
                $e .= '12-31';
                if (isset($date[self::CMONTH]) and $date[self::CMONTH] !== 'any') {
                    $res .= ' AND ' . $this->calendar_levels[self::CMONTH]['sql'] . '=' . $date[self::CMONTH];
                }
                if (isset($date[self::CDAY]) and $date[self::CDAY] !== 'any') {
                    $res .= ' AND ' . $this->calendar_levels[self::CDAY]['sql'] . '=' . $date[self::CDAY];
                }
            }
            $res = " AND $this->date_field BETWEEN '$b' AND '$e 23:59:59'" . $res;
        } else {
            $res = ' AND ' . $this->date_field . ' IS NOT NULL';
            if (isset($date[self::CMONTH]) and $date[self::CMONTH] !== 'any') {
                $res .= ' AND ' . $this->calendar_levels[self::CMONTH]['sql'] . '=' . $date[self::CMONTH];
            }
            if (isset($date[self::CDAY]) and $date[self::CDAY] !== 'any') {
                $res .= ' AND ' . $this->calendar_levels[self::CDAY]['sql'] . '=' . $date[self::CDAY];
            }
        }
        return $res;
    }

    /**
     * Returns an array with all the days in a given month.
     *
     * @param int $year
     * @param int $month
     * @return int[]
     */
    protected function get_all_days_in_month($year, $month)
    {
        // cannot use cal_days_in_month(CAL_GREGORIAN, $month, $year); because of params that can be 'any'

        $days_in_month = [1 => 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if (is_numeric($year) and $month == 2) {
            $nb_days = $days_in_month[2];
            if (($year % 4 == 0) && (($year % 100 != 0) || ($year % 400 != 0))) {
                $nb_days++;
            }
        } elseif (is_numeric($month)) {
            $nb_days = $days_in_month[$month];
        } else {
            $nb_days = 31;
        }

        return $nb_days;
    }

    /**
     * Build global calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function build_global_calendar(&$tpl_var)
    {
        global $page, $lang, $conn;

        if (count($page['chronology_date']) != 0) {
            return;
        }

        $query = 'SELECT ' . $conn->db_get_date_YYYYMM($this->date_field) . ' as period,';
        $query .= 'COUNT(distinct id) as count';
        $query .= $this->inner_sql;
        $query .= $this->get_date_where();
        $query .= ' GROUP BY period, ' . $this->date_field;
        $query .= ' ORDER BY ' . $conn->db_get_year($this->date_field) . ' DESC, ' . $conn->db_get_month($this->date_field) . ' ASC';

        $result = $conn->db_query($query);
        $items = array();
        while ($row = $conn->db_fetch_assoc($result)) {
            $y = substr($row['period'], 0, 4);
            $m = (int)substr($row['period'], 4, 2);
            if (!isset($items[$y])) {
                $items[$y] = array('nb_images' => 0, 'children' => array());
            }
            $items[$y]['children'][$m] = $row['count'];
            $items[$y]['nb_images'] += $row['count'];
        }

        if (count($items) == 1) { // only one year exists so bail out to year view
            list($y) = array_keys($items);
            $page['chronology_date'][self::CYEAR] = $y;
            return false;
        }

        foreach ($items as $year => $year_data) {
            $chronology_date = array($year);
            $url = \Phyxo\Functions\URL::duplicate_index_url(array('chronology_date' => $chronology_date));

            $nav_bar = $this->get_nav_bar_from_items(
                $chronology_date,
                $year_data['children'],
                false,
                false,
                $lang['month']
            );

            $tpl_var['calendar_bars'][] = array(
                'U_HEAD' => $url,
                'NB_IMAGES' => $year_data['nb_images'],
                'HEAD_LABEL' => $year,
                'items' => $nav_bar,
            );
        }

        return true;
    }

    /**
     * Build year calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function build_year_calendar(&$tpl_var)
    {
        global $page, $lang, $conn;

        if (count($page['chronology_date']) != 1) {
            return;
        }
        $query = 'SELECT ' . $conn->db_get_date_MMDD($this->date_field) . ' as period,';
        $query .= ' COUNT(DISTINCT id) as count';
        $query .= $this->inner_sql;
        $query .= $this->get_date_where();
        $query .= ' GROUP BY period';
        $query .= ' ORDER BY period ASC';

        $result = $conn->db_query($query);
        $items = array();
        while ($row = $conn->db_fetch_assoc($result)) {
            $m = (int)substr($row['period'], 0, 2);
            $d = substr($row['period'], 2, 2);
            if (!isset($items[$m])) {
                $items[$m] = array('nb_images' => 0, 'children' => array());
            }
            $items[$m]['children'][$d] = $row['count'];
            $items[$m]['nb_images'] += $row['count'];
        }
        if (count($items) == 1) { // only one month exists so bail out to month view
            list($m) = array_keys($items);
            $page['chronology_date'][self::CMONTH] = $m;
            return false;
        }

        foreach ($items as $month => $month_data) {
            $chronology_date = array($page['chronology_date'][self::CYEAR], $month);
            $url = \Phyxo\Functions\URL::duplicate_index_url(array('chronology_date' => $chronology_date));

            $nav_bar = $this->get_nav_bar_from_items(
                $chronology_date,
                $month_data['children'],
                false
            );

            $tpl_var['calendar_bars'][] = array(
                'U_HEAD' => $url,
                'NB_IMAGES' => $month_data['nb_images'],
                'HEAD_LABEL' => $lang['month'][$month],
                'items' => $nav_bar,
            );
        }

        return true;
    }

    /**
     * Build month calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function build_month_calendar(&$tpl_var)
    {
        global $page, $lang, $conf, $conn;

        $query = 'SELECT ' . $conn->db_get_dayofmonth($this->date_field) . ' as period,';
        $query .= ' COUNT(DISTINCT id) as count';
        $query .= $this->inner_sql;
        $query .= $this->get_date_where();
        $query .= ' GROUP BY period ORDER BY period ASC';

        $items = array();
        $result = $conn->db_query($query);
        while ($row = $conn->db_fetch_assoc($result)) {
            $d = (int)$row['period'];
            $items[$d] = array('nb_images' => $row['count']);
        }

        foreach ($items as $day => $data) {
            $page['chronology_date'][self::CDAY] = $day;
            $query = 'SELECT id, file,representative_ext,path,width,height,rotation, ';
            $query .= $conn->db_get_dayofweek($this->date_field) . '-1 as dow';
            $query .= $this->inner_sql;
            $query .= $this->get_date_where();
            $query .= 'ORDER BY ' . $conn::RANDOM_FUNCTION . '() LIMIT 1';
            unset($page['chronology_date'][self::CDAY]);

            $row = $conn->db_fetch_assoc($conn->db_query($query));
            $derivative = new \DerivativeImage(IMG_SQUARE, new \SrcImage($row));
            $items[$day]['derivative'] = $derivative;
            $items[$day]['file'] = $row['file'];
            $items[$day]['dow'] = $row['dow'];
        }

        if (!empty($items)) {
            list($known_day) = array_keys($items);
            $known_dow = $items[$known_day]['dow'];
            $first_day_dow = ($known_dow - ($known_day - 1)) % 7;
            if ($first_day_dow < 0) {
                $first_day_dow += 7;
            }
            //first_day_dow = week day corresponding to the first day of this month
            $wday_labels = $lang['day'];

            if ('monday' == $conf['week_starts_on']) {
                if ($first_day_dow == 0) {
                    $first_day_dow = 6;
                } else {
                    $first_day_dow -= 1;
                }

                $wday_labels[] = array_shift($wday_labels);
            }

            list($cell_width, $cell_height) = \ImageStdParams::get_by_type(IMG_SQUARE)->sizing->ideal_size;

            $tpl_weeks = array();
            $tpl_crt_week = array();

            //fill the empty days in the week before first day of this month
            for ($i = 0; $i < $first_day_dow; $i++) {
                $tpl_crt_week[] = array();
            }

            for ($day = 1; $day <= $this->get_all_days_in_month($page['chronology_date'][self::CYEAR], $page['chronology_date'][self::CMONTH]); $day++) {
                $dow = ($first_day_dow + $day - 1) % 7;
                if ($dow == 0 and $day != 1) {
                    $tpl_weeks[] = $tpl_crt_week; // add finished week to week list
                    $tpl_crt_week = array(); // start new week
                }

                if (!isset($items[$day])) { // empty day
                    $tpl_crt_week[] = array('DAY' => $day);
                } else {
                    $url = \Phyxo\Functions\URL::duplicate_index_url(
                        array(
                            'chronology_date' =>
                                array(
                                $page['chronology_date'][self::CYEAR],
                                $page['chronology_date'][self::CMONTH],
                                $day
                            )
                        )
                    );

                    $tpl_crt_week[] = array(
                        'DAY' => $day,
                        'DOW' => $dow,
                        'NB_ELEMENTS' => $items[$day]['nb_images'],
                        'IMAGE' => $items[$day]['derivative']->get_url(),
                        'U_IMG_LINK' => $url,
                        'IMAGE_ALT' => $items[$day]['file'],
                    );
                }
            }
            //fill the empty days in the week after the last day of this month
            while ($dow < 6) {
                $tpl_crt_week[] = array();
                $dow++;
            }
            $tpl_weeks[] = $tpl_crt_week;

            $tpl_var['month_view'] = array(
                'CELL_WIDTH' => $cell_width,
                'CELL_HEIGHT' => $cell_height,
                'wday_labels' => $wday_labels,
                'weeks' => $tpl_weeks,
            );
        }

        return true;
    }
}
