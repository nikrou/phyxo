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

if (!defined('HISTORY_BASE_URL')) {
    die("Hacking attempt!");
}

use App\Repository\HistorySummaryRepository;
// +-----------------------------------------------------------------------+
// | Refresh summary from details                                          |
// +-----------------------------------------------------------------------+

$query = 'SELECT date,' . $conn->db_get_hour('time') . ' AS hour,MAX(id) AS max_id,';
$query .= 'COUNT(1) AS nb_pages FROM ' . HISTORY_TABLE;
$query .= ' WHERE summarized = \'' . $conn->boolean_to_db(false) . '\'';
$query .= ' GROUP BY date,hour';
$query .= ' ORDER BY date ASC,hour ASC;';
$result = $conn->db_query($query);

$need_update = [];

$max_id = 0;
$is_first = true;
$first_time_key = null;

while ($row = $conn->db_fetch_assoc($result)) {
    $time_keys = [
        substr($row['date'], 0, 4), //yyyy
        substr($row['date'], 0, 7), //yyyy-mm
        substr($row['date'], 0, 10),//yyyy-mm-dd
        sprintf(
            '%s-%02u',
            $row['date'],
            $row['hour']
        ),
    ];

    foreach ($time_keys as $time_key) {
        if (!isset($need_update[$time_key])) {
            $need_update[$time_key] = 0;
        }
        $need_update[$time_key] += $row['nb_pages'];
    }

    if ($row['max_id'] > $max_id) {
        $max_id = $row['max_id'];
    }

    if ($is_first) {
        $is_first = false;
        $first_time_key = $time_keys[3];
    }
}

// Only the oldest time_key might be already summarized, so we have to
// update the 4 corresponding lines instead of simply inserting them.
//
// For example, if the oldest unsummarized is 2005.08.25.21, the 4 lines
// that can be updated are:
//
// +---------------+----------+
// | id            | nb_pages |
// +---------------+----------+
// | 2005          |   241109 |
// | 2005-08       |    20133 |
// | 2005-08-25    |      620 |
// | 2005-08-25-21 |      151 |
// +---------------+----------+


$updates = [];
$inserts = [];

if (isset($first_time_key)) {
    list($year, $month, $day, $hour) = explode('-', $first_time_key);

    $query = 'SELECT * FROM ' . HISTORY_SUMMARY_TABLE;
    $query .= ' WHERE year=' . $year;
    $query .= ' AND ( month IS NULL OR ( month=' . $month . ' AND ( day is NULL OR (day=' . $day . ' AND (hour IS NULL OR hour=' . $hour . ')))));';
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $key = sprintf('%4u', $row['year']);
        if (isset($row['month'])) {
            $key .= sprintf('-%02u', $row['month']);
            if (isset($row['day'])) {
                $key .= sprintf('-%02u', $row['day']);
                if (isset($row['hour'])) {
                    $key .= sprintf('-%02u', $row['hour']);
                }
            }
        }

        if (isset($need_update[$key])) {
            $row['nb_pages'] += $need_update[$key];
            $updates[] = $row;
            unset($need_update[$key]);
        }
    }
}

foreach ($need_update as $time_key => $nb_pages) {
    $time_tokens = explode('-', $time_key);

    $inserts[] = [
        'year' => $time_tokens[0],
        'month' => @$time_tokens[1],
        'day' => @$time_tokens[2],
        'hour' => @$time_tokens[3],
        'nb_pages' => $nb_pages,
    ];
}

if (count($updates) > 0) {
    $conn->mass_updates(
        HISTORY_SUMMARY_TABLE,
        [
            'primary' => ['year', 'month', 'day', 'hour'],
            'update' => ['nb_pages'],
        ],
        $updates
    );
}

if (count($inserts) > 0) {
    $conn->mass_inserts(
        HISTORY_SUMMARY_TABLE,
        array_keys($inserts[0]),
        $inserts
    );
}

if ($max_id != 0) {
    $query = 'UPDATE ' . HISTORY_TABLE . ' SET summarized = \'' . $conn->boolean_to_db(true) . '\'';
    $query .= ' WHERE summarized = \'' . $conn->boolean_to_db(false) . '\'';
    $query .= ' AND id <= ' . $max_id . ';';
    $conn->db_query($query);
}

// +-----------------------------------------------------------------------+
// | Page parameters check                                                 |
// +-----------------------------------------------------------------------+

foreach (['day', 'month', 'year'] as $key) {
    if (isset($_GET[$key])) {
        $page[$key] = (int)$_GET[$key];
    }
}

if (isset($page['day'])) {
    if (!isset($page['month'])) {
        die('month is missing in URL');
    }
}

if (isset($page['month'])) {
    if (!isset($page['year'])) {
        die('year is missing in URL');
    }
}

$summary_lines = $conn->result2array((new HistorySummaryRepository($conn))->getSummary(
    isset($page['year']) ? $page['year'] : null,
    isset($page['month']) ? $page['month'] : null,
    isset($page['day']) ? $page['day'] : null
));

// +-----------------------------------------------------------------------+
// | Display statistics header                                             |
// +-----------------------------------------------------------------------+

// page title creation
$title_parts = [];

$url = HISTORY_BASE_URL;

$title_parts[] = '<a href="' . $url . '">' . \Phyxo\Functions\Language::l10n('Overall') . '</a>';

$period_label = \Phyxo\Functions\Language::l10n('Year');

if (isset($page['year'])) {
    $url .= '&amp;year=' . $page['year'];

    $title_parts[] = '<a href="' . $url . '">' . $page['year'] . '</a>';

    $period_label = \Phyxo\Functions\Language::l10n('Month');
}

if (isset($page['month'])) {
    $url .= '&amp;month=' . $page['month'];

    $title_parts[] = '<a href="' . $url . '">' . $lang['month'][$page['month']] . '</a>';

    $period_label = \Phyxo\Functions\Language::l10n('Day');
}

if (isset($page['day'])) {
    $url .= '&amp;day=' . $page['day'];

    $time = mktime(12, 0, 0, $page['month'], $page['day'], $page['year']);

    $day_title = sprintf(
        '%u (%s)',
        $page['day'],
        $lang['day'][date('w', $time)]
    );

    $title_parts[] = '<a href="' . $url . '">' . $day_title . '</a>';

    $period_label = \Phyxo\Functions\Language::l10n('Hour');
}

$base_url = HISTORY_BASE_URL;

$template->assign(
    [
        'L_STAT_TITLE' => implode($conf['level_separator'], $title_parts),
        'PERIOD_LABEL' => $period_label,
        //'U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=history',
        'F_ACTION' => $base_url,
    ]
);

// +-----------------------------------------------------------------------+
// | Display statistic rows                                                |
// +-----------------------------------------------------------------------+

$max_width = 400;

$datas = [];

if (isset($page['day'])) {
    $key = 'hour';
    $min_x = 0;
    $max_x = 23;
} elseif (isset($page['month'])) {
    $key = 'day';
    $min_x = 1;
    $max_x = date(
        't',
        mktime(12, 0, 0, $page['month'], 1, $page['year'])
    );
} elseif (isset($page['year'])) {
    $key = 'month';
    $min_x = 1;
    $max_x = 12;
} else {
    $key = 'year';
}

$max_pages = 1;
foreach ($summary_lines as $line) {
    if ($line['nb_pages'] > $max_pages) {
        $max_pages = $line['nb_pages'];
    }

    $datas[$line[$key]] = $line['nb_pages'];
}

if (!isset($min_x) and !isset($max_x) and count($datas) > 0) {
    $min_x = min(array_keys($datas));
    $max_x = max(array_keys($datas));
}

if (count($datas) > 0) {
    for ($i = $min_x; $i <= $max_x; $i++) {
        if (!isset($datas[$i])) {
            $datas[$i] = 0;
        }

        $url = null;

        if (isset($page['day'])) {
            $value = sprintf('%02u', $i);
        } elseif (isset($page['month'])) {
            $url = HISTORY_BASE_URL . '&amp;year=' . $page['year'] . '&amp;month=' . $page['month'] . '&amp;day=' . $i;
            $time = mktime(12, 0, 0, $page['month'], $i, $page['year']);
            $value = $i . ' (' . $lang['day'][date('w', $time)] . ')';
        } elseif (isset($page['year'])) {
            $url = HISTORY_BASE_URL . '&amp;year=' . $page['year'] . '&amp;month=' . $i;
            $value = $lang['month'][$i];
        } else { // at least the year is defined
            $url = HISTORY_BASE_URL . '&amp;year=' . $i;
            $value = $i;
        }

        if ($datas[$i] != 0 and isset($url)) {
            $value = '<a href="' . $url . '">' . $value . '</a>';
        }

        $template->append(
            'statrows',
            [
                'VALUE' => $value,
                'PAGES' => $datas[$i],
                'WIDTH' => ceil(($datas[$i] * $max_width) / $max_pages),
            ]
        );
    }
}
