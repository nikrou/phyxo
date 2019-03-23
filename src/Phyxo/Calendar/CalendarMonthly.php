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
use Phyxo\Image\SrcImage;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStdParams;
use Phyxo\DBLayer\DBLayer;
use App\Repository\ImageRepository;

/**
 * Monthly calendar style (composed of years/months and days)
 */
class CalendarMonthly extends CalendarBase
{
    const CYEAR = 0, CMONTH = 1, CDAY = 2;

    public function __construct(DBLayer $conn, string $date_type = 'posted')
    {
        global $lang;

        parent::__construct($conn, $date_type);

        $this->calendar_levels = [
            [
                'sql' => $this->conn->db_get_year($this->date_field),
                'labels' => null
            ],
            [
                'sql' => $this->conn->db_get_month($this->date_field),
                'labels' => $lang['month']
            ],
            [
                'sql' => $this->conn->db_get_dayofmonth($this->date_field),
                'labels' => null
            ],
        ];
    }

    /**
     * Generate navigation bars for category page.
     *
     * @return boolean false indicates that thumbnails where not included
     */
    public function generateCategoryContent()
    {
        global $page, $template;

        $view_type = $page['chronology_view'];
        if ($view_type == CAL_VIEW_CALENDAR) {
            $tpl_var = [];
            if (count($page['chronology_date']) == 0) { //case A: no year given - display all years+months
                if ($this->buildGlobalCalendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                    return true;
                }
            }

            if (count($page['chronology_date']) == 1) { //case B: year given - display all days in given year
                if ($this->buildYearCalendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                    $this->buildNavigationBar(self::CYEAR); // years
                    return true;
                }
            }

            if (count($page['chronology_date']) == 2) { //case C: year+month given - display a nice month calendar
                if ($this->buildMonthCalendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                }
                $this->buildNextPrev();
                return true;
            }
        }

        if ($view_type == CAL_VIEW_LIST or count($page['chronology_date']) == 3) {
            if (count($page['chronology_date']) == 0) {
                $this->buildNavigationBar(self::CYEAR); // years
            }
            if (count($page['chronology_date']) == 1) {
                $this->buildNavigationBar(self::CMONTH); // month
            }
            if (count($page['chronology_date']) == 2) {
                $day_labels = range(1, $this->getAllDaysInMonth($page['chronology_date'][self::CYEAR], $page['chronology_date'][self::CMONTH]));
                array_unshift($day_labels, 0);
                unset($day_labels[0]);
                $this->buildNavigationBar(self::CDAY, $day_labels); // days
            }
            $this->buildNextPrev();
        }
        return false;
    }

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     * @return string
     */
    public function getDateWhere($max_levels = 3)
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
                    $e .= $this->getAllDaysInMonth($date[self::CYEAR], $date[self::CMONTH]);
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
    protected function getAllDaysInMonth($year, $month)
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
    protected function buildGlobalCalendar(&$tpl_var)
    {
        global $page, $lang;

        if (count($page['chronology_date']) != 0) {
            return;
        }

        if ($this->find_by_items) {
            $result = (new ImageRepository($this->conn))->findYYYYMMPeriodAndImagesCountByIds($this->date_field, $this->getDateWhere(), $this->items);
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    (new CategoryRepository($this->conn))->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $result = (new ImageRepository($this->conn))->findYYYYMMPeriodAndImagesCount($this->date_field, $this->getDateWhere(), $this->condition, $sub_ids);
        }

        $items = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $y = substr($row['period'], 0, 4);
            $m = (int)substr($row['period'], 4, 2);
            if (!isset($items[$y])) {
                $items[$y] = ['nb_images' => 0, 'children' => []];
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
            $chronology_date = [$year];
            $url = \Phyxo\Functions\URL::duplicate_index_url(['chronology_date' => $chronology_date]);

            $nav_bar = $this->getNavigationBarFromItems(
                $chronology_date,
                $year_data['children'],
                false,
                false,
                $lang['month']
            );

            $tpl_var['calendar_bars'][] = [
                'U_HEAD' => $url,
                'NB_IMAGES' => $year_data['nb_images'],
                'HEAD_LABEL' => $year,
                'items' => $nav_bar,
            ];
        }

        return true;
    }

    /**
     * Build year calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function buildYearCalendar(&$tpl_var)
    {
        global $page, $lang;

        if (count($page['chronology_date']) != 1) {
            return;
        }

        if ($this->find_by_items) {
            $result = (new ImageRepository($this->conn))->findMMDDPeriodAndImagesCountByIds($this->date_field, $this->getDateWhere(), $this->items);
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    (new CategoryRepository($this->conn))->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $result = (new ImageRepository($this->conn))->findMMDDPeriodAndImagesCount($this->date_field, $this->getDateWhere(), $this->condition, $sub_ids);
        }

        $items = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $m = (int)substr($row['period'], 0, 2);
            $d = substr($row['period'], 2, 2);
            if (!isset($items[$m])) {
                $items[$m] = ['nb_images' => 0, 'children' => []];
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
            $chronology_date = [$page['chronology_date'][self::CYEAR], $month];
            $url = \Phyxo\Functions\URL::duplicate_index_url(['chronology_date' => $chronology_date]);

            $nav_bar = $this->getNavigationBarFromItems(
                $chronology_date,
                $month_data['children'],
                false
            );

            $tpl_var['calendar_bars'][] = [
                'U_HEAD' => $url,
                'NB_IMAGES' => $month_data['nb_images'],
                'HEAD_LABEL' => $lang['month'][$month],
                'items' => $nav_bar,
            ];
        }

        return true;
    }

    /**
     * Build month calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function buildMonthCalendar(&$tpl_var)
    {
        global $page, $lang, $conf;

        if ($this->find_by_items) {
            $result = (new ImageRepository($this->conn))->findDayOfMonthPeriodAndImagesCountByIds($this->date_field, $this->getDateWhere(), $this->items);
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    (new CategoryRepository($this->conn))->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $result = (new ImageRepository($this->conn))->findDayOfMonthPeriodAndImagesCount($this->date_field, $this->getDateWhere(), $this->condition, $sub_ids);
        }

        $items = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $d = (int)$row['period'];
            $items[$d] = ['nb_images' => $row['count']];
        }

        foreach ($items as $day => $data) {
            $page['chronology_date'][self::CDAY] = $day;

            if ($this->find_by_items) {
                $result = (new ImageRepository($this->conn))->findOneRandomInWeekByIds($this->date_field, $this->getDateWhere(), $this->items);
            } else {
                if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                    $sub_ids = array_diff(
                        (new CategoryRepository($this->conn))->getSubcatIds([$this->category_id]),
                        $this->forbidden_categories
                    );
                } else {
                    $sub_ids = [];
                }
                $result = (new ImageRepository($this->conn))->findOneRandomInWeek($this->date_field, $this->getDateWhere(), $this->condition, $sub_ids);
            }
    
            unset($page['chronology_date'][self::CDAY]);

            $row = $this->conn->db_fetch_assoc($result);
            $derivative = new DerivativeImage(IMG_SQUARE, new SrcImage($row));
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

            list($cell_width, $cell_height) = ImageStdParams::get_by_type(IMG_SQUARE)->sizing->ideal_size;

            $tpl_weeks = [];
            $tpl_crt_week = [];

            //fill the empty days in the week before first day of this month
            for ($i = 0; $i < $first_day_dow; $i++) {
                $tpl_crt_week[] = [];
            }

            for ($day = 1; $day <= $this->getAllDaysInMonth($page['chronology_date'][self::CYEAR], $page['chronology_date'][self::CMONTH]); $day++) {
                $dow = ($first_day_dow + $day - 1) % 7;
                if ($dow == 0 and $day != 1) {
                    $tpl_weeks[] = $tpl_crt_week; // add finished week to week list
                    $tpl_crt_week = []; // start new week
                }

                if (!isset($items[$day])) { // empty day
                    $tpl_crt_week[] = ['DAY' => $day];
                } else {
                    $url = \Phyxo\Functions\URL::duplicate_index_url(
                        [
                            'chronology_date' =>
                            [
                                $page['chronology_date'][self::CYEAR],
                                $page['chronology_date'][self::CMONTH],
                                $day
                            ]
                        ]
                    );

                    $tpl_crt_week[] = [
                        'DAY' => $day,
                        'DOW' => $dow,
                        'NB_ELEMENTS' => $items[$day]['nb_images'],
                        'IMAGE' => $items[$day]['derivative']->get_url(),
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

            $tpl_var['month_view'] = [
                'CELL_WIDTH' => $cell_width,
                'CELL_HEIGHT' => $cell_height,
                'wday_labels' => $wday_labels,
                'weeks' => $tpl_weeks,
            ];
        }

        return true;
    }
}
