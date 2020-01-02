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

namespace Phyxo\Functions;

class DateTime
{
    /**
     * converts a string into a DateTime object
     *
     * @param int|string timestamp or datetime string
     * @param string $format input format respecting date() syntax
     * @return DateTime|false
     */
    public static function str2DateTime($original, $format = null)
    {
        if (empty($original)) {
            return false;
        }

        if ($original instanceof DateTime) {
            return $original;
        }

        if (!empty($format)) { // from known date format
            return \DateTime::createFromFormat('!' . $format, $original); // ! char to reset fields to UNIX epoch
        } else {
            $dt = new \DateTime();
            $dt->setTimestamp(strtotime($original));

            return $dt;
        }
    }

    /**
     * returns a formatted and localized date for display
     *
     * @param int|string timestamp or datetime string
     * @param array $show list of components displayed, default is ['day_name', 'day', 'month', 'year']
     *    THIS PARAMETER IS PLANNED TO CHANGE
     * @param string $format input format respecting date() syntax
     * @return string
     */
    public static function format_date($original, $show = null, $format = null)
    {
        global $lang;

        $date = self::str2DateTime($original, $format);

        if (!$date) {
            return 'N/A'; // @TODO: label is not translated
        }

        if ($show === null || $show === true) {
            $show = ['day_name', 'day', 'month', 'year'];
        }

        // @TODO : use IntlDateFormatter for proper i18n
        $print = '';
        if (in_array('day_name', $show)) {
            $print .= $lang['day'][$date->format('w')] . ' ';
        }

        if (in_array('day', $show)) {
            $print .= $date->format('j') . ' ';
        }

        if (in_array('month', $show)) {
            $print .= $lang['month'][$date->format('n')] . ' ';
        }

        if (in_array('year', $show)) {
            $print .= $date->format('Y') . ' ';
        }

        if (in_array('time', $show)) {
            $temp = $date->format('H:i');
            if ($temp != '00:00') {
                $print .= $temp . ' ';
            }
        }

        return trim($print);
    }

    /**
     * Format a "From ... to ..." string from two dates
     * @param string $from
     * @param string $to
     * @param boolean $full
     * @return string
     */
    public static function format_fromto($from, $to, $full = false)
    {
        $from = self::str2DateTime($from);
        $to = self::str2DateTime($to);

        if ($from->format('Y-m-d') == $to->format('Y-m-d')) {
            return self::format_date($from);
        } else {
            if ($full || $from->format('Y') != $to->format('Y')) {
                $from_str = self::format_date($from);
            } elseif ($from->format('m') != $to->format('m')) {
                $from_str = self::format_date($from, ['day_name', 'day', 'month']);
            } else {
                $from_str = self::format_date($from, ['day_name', 'day']);
            }
            $to_str = self::format_date($to);

            return sprintf('from %s to %s', $from_str, $to_str); // @TODO: label is not translated
        }
    }

    /**
     * Works out the time since the given date
     *
     * @param int|string timestamp or datetime string
     * @param string $stop year,month,week,day,hour,minute,second
     * @param string $format input format respecting date() syntax
     * @param bool $with_text append "ago" or "in the future"
     * @param bool $with_weeks
     * @return string
     */
    public static function time_since($original, $stop = 'minute', $format = null, $with_text = true, $with_week = true)
    {
        $date = self::str2DateTime($original, $format);

        if (!$date) {
            return 'N/A'; // @TODO: label is not translated
        }

        $now = new \DateTime();
        $diff = $now->diff($date);

        $chunks = [
            'year' => $diff->y,
            'month' => $diff->m,
            'week' => 0,
            'day' => $diff->d,
            'hour' => $diff->h,
            'minute' => $diff->i,
            'second' => $diff->s,
        ];

        // DateInterval does not contain the number of weeks
        if ($with_week) {
            $chunks['week'] = (int)floor($chunks['day'] / 7);
            $chunks['day'] = $chunks['day'] - $chunks['week'] * 7;
        }

        $j = array_search($stop, array_keys($chunks));

        $print = '';
        $i = 0;
        foreach ($chunks as $name => $value) {
            if ($value != 0) {
                $print .= ' ' . $value === 1 ? 'one ' . $name : sprintf('%d ' . $name . 's', $value); // @TODO: label is not translated
            }
            if (!empty($print) && $i >= $j) {
                break;
            }
            $i++;
        }

        $print = trim($print);

        if ($with_text) {
            if ($diff->invert) {
                $print = sprintf('%s ago', $print); // @TODO: label is not translated
            } else {
                $print = sprintf('%s in the future', $print); // @TODO: label is not translated
            }
        }

        return $print;
    }

    /**
     * transform a date string from a format to another (MySQL to d/M/Y for instance)
     *
     * @param string $original
     * @param string $format_in respecting date() syntax
     * @param string $format_out respecting date() syntax
     * @param string $default if _$original_ is empty
     * @return string
     *
     * @TODO: to remove ?
     */
    public static function transform_date($original, $format_in, $format_out, $default = null)
    {
        if (empty($original)) {
            return $default;
        }
        $date = self::str2DateTime($original, $format_in);
        return $date->format($format_out);
    }
}
