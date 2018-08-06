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

use Phyxo\Calendar\CalendarWeekly;
use Phyxo\Calendar\CalendarMonthly;


/** URL keyword for list view */
define('CAL_VIEW_LIST', 'list');
/** URL keyword for calendar view */
define('CAL_VIEW_CALENDAR', 'calendar');

/**
 * Initialize _$page_ and _$template_ vars for calendar view.
 */
function initialize_calendar()
{
    global $page, $conf, $user, $template, $persistent_cache, $filter, $conn;

    //------------------ initialize the condition on items to take into account ---
    $inner_sql = ' FROM ' . IMAGES_TABLE;

    if ($page['section'] == 'categories') { // we will regenerate the items by including subcats elements
        $page['items'] = array();
        $inner_sql .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id = image_id';

        if (isset($page['category'])) {
            $sub_ids = array_diff(
                get_subcat_ids(array($page['category']['id'])),
                explode(',', $user['forbidden_categories'])
            );

            if (empty($sub_ids)) {
                return; // nothing to do
            }
            $inner_sql .= ' WHERE category_id ' . $conn->in($sub_ids);
            $inner_sql .= ' ' . get_sql_condition_FandF(array('visible_images' => 'id'), 'AND', false);
        } else {
            $inner_sql .= ' ' . get_sql_condition_FandF(
                array(
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id'
                ),
                'WHERE',
                true
            );
        }
    } else {
        if (empty($page['items'])) {
            return; // nothing to do
        }
        $inner_sql .= ' WHERE id ' . $conn->in($page['items']);
    }

    //-------------------------------------- initialize the calendar parameters ---
    $fields = array(
        // Created
        'created' => array(
            'label' => \Phyxo\Functions\Language::l10n('Creation date'),
        ),
        // Posted
        'posted' => array(
            'label' => \Phyxo\Functions\Language::l10n('Post date'),
        ),
    );

    $styles = array(
        // Monthly style
        'monthly' => array(
            'include' => 'calendar_monthly.class.php',
            'view_calendar' => true,
            'classname' => 'CalendarMonthly',
        ),
        // Weekly style
        'weekly' => array(
            'include' => 'calendar_weekly.class.php',
            'view_calendar' => false,
            'classname' => 'CalendarWeekly',
        ),
    );

    $views = array(CAL_VIEW_LIST, CAL_VIEW_CALENDAR);

    // Retrieve calendar field
    isset($fields[$page['chronology_field']]) or fatal_error('bad chronology field');

    // Retrieve style
    if (!isset($styles[$page['chronology_style']])) {
        $page['chronology_style'] = 'monthly';
    }
    $cal_style = $page['chronology_style'];
    $classname = 'Phyxo\Calendar\\' . $styles[$cal_style]['classname'];

    $calendar = new $classname();

    // Retrieve view

    if (!isset($page['chronology_view']) or !in_array($page['chronology_view'], $views)) {
        $page['chronology_view'] = CAL_VIEW_LIST;
    }

    if (CAL_VIEW_CALENDAR == $page['chronology_view'] && !$styles[$cal_style]['view_calendar']) {
        $page['chronology_view'] = CAL_VIEW_LIST;
    }

    // perform a sanity check on $requested
    if (!isset($page['chronology_date'])) {
        $page['chronology_date'] = array();
    }
    while (count($page['chronology_date']) > 3) {
        array_pop($page['chronology_date']);
    }

    $any_count = 0;
    for ($i = 0; $i < count($page['chronology_date']); $i++) {
        if ($page['chronology_date'][$i] == 'any') {
            if ($page['chronology_view'] == CAL_VIEW_CALENDAR) { // we dont allow any in calendar view
                while ($i < count($page['chronology_date'])) {
                    array_pop($page['chronology_date']);
                }
                break;
            }
            $any_count++;
        } elseif ($page['chronology_date'][$i] == '') {
            while ($i < count($page['chronology_date'])) {
                array_pop($page['chronology_date']);
            }
        } else {
            $page['chronology_date'][$i] = (int)$page['chronology_date'][$i];
        }
    }
    if ($any_count == 3) {
        array_pop($page['chronology_date']);
    }

    $calendar->initialize($inner_sql);

    $must_show_list = true; // true until calendar generates its own display
    if (script_basename() != 'picture') { // basename without file extention
        if ($calendar->generate_category_content()) {
            $page['items'] = array();
            $must_show_list = false;
        }

        $page['comment'] = '';
        $template->assign('FILE_CHRONOLOGY_VIEW', 'month_calendar.tpl');

        foreach ($styles as $style => $style_data) {
            foreach ($views as $view) {
                if ($style_data['view_calendar'] or $view != CAL_VIEW_CALENDAR) {
                    $selected = false;

                    if ($style != $cal_style) {
                        $chronology_date = array();
                        if (isset($page['chronology_date'][0])) {
                            $chronology_date[] = $page['chronology_date'][0];
                        }
                    } else {
                        $chronology_date = $page['chronology_date'];
                    }
                    $url = \Phyxo\Functions\URL::duplicate_index_url(
                        array(
                            'chronology_style' => $style,
                            'chronology_view' => $view,
                            'chronology_date' => $chronology_date,
                        )
                    );

                    if ($style == $cal_style and $view == $page['chronology_view']) {
                        $selected = true;
                    }

                    $template->append(
                        'chronology_views',
                        array(
                            'VALUE' => $url,
                            'CONTENT' => \Phyxo\Functions\Language::l10n('chronology_' . $style . '_' . $view),
                            'SELECTED' => $selected,
                        )
                    );
                }
            }
        }
        $url = \Phyxo\Functions\URL::duplicate_index_url(array(), array('start', 'chronology_date'));
        $calendar_title = '<a href="' . $url . '">' . $fields[$page['chronology_field']]['label'] . '</a>';
        $calendar_title .= $calendar->get_display_name();
        $template->assign('chronology', array('TITLE' => $calendar_title));
    } // end category calling

    if ($must_show_list) {
        if (isset($page['super_order_by'])) {
            $order_by = $conf['order_by'];
        } else {
            if (count($page['chronology_date']) == 0
                or in_array('any', $page['chronology_date'])) {// selected period is very big so we show newest first
                $order = ' DESC, ';
            } else { // selected period is small (month,week) so we show oldest first
                $order = ' ASC, ';
            }

            $order_by = str_replace(
                'ORDER BY ',
                'ORDER BY ' . $calendar->date_field . $order,
                $conf['order_by']
            );
        }

        if ('categories' == $page['section'] && !isset($page['category'])
            && (count($page['chronology_date']) == 0 or ($page['chronology_date'][0] == 'any' && count($page['chronology_date']) == 1))) {
            $cache_key = $persistent_cache->make_key($user['id'] . $user['cache_update_time'] . $calendar->date_field . $order_by);
        }

        if (!isset($cache_key) || !$persistent_cache->get($cache_key, $page['items'])) {
            $query = 'SELECT DISTINCT id,' . addOrderByFields($order_by);
            $query .= $calendar->inner_sql . ' ' . $calendar->get_date_where();
            $query .= ' ' . $order_by;

            $page['items'] = $conn->query2array($query, null, 'id');
            if (isset($cache_key)) {
                $persistent_cache->set($cache_key, $page['items']);
            }
        }
    }
}
