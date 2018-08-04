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

// $filter['enabled']: Filter is enabled
// $filter['recent_period']: Recent period used to computed filter data
// $filter['categories']: Computed data of filtered categories
// $filter['visible_categories']:
//  List of visible categories (count(visible) < count(forbidden) more often)
// $filter['visible_images']: List of visible images

if (!get_filter_page_value('cancel')) {
    if (isset($_GET['filter'])) {
        $filter['matches'] = array();
        $filter['enabled'] = preg_match('/^start-recent-(\d+)$/', $_GET['filter'], $filter['matches']) === 1;
    } else {
        $filter['enabled'] = isset($_SESSION['filter_enabled']) ? $_SESSION['filter_enabled'] : false;
    }
} else {
    $filter['enabled'] = false;
}

if ($filter['enabled']) {
    $filter_key = isset($_SESSION['filter_check_key']) ? $_SESSION['filter_check_key'] : array('user' => 0, 'recent_period' => -1, 'time' => 0, 'date' => '');

    if (isset($filter['matches'])) {
        $filter['recent_period'] = $filter['matches'][1];
    } else {
        $filter['recent_period'] = $filter_key['recent_period'] > 0 ? $filter_key['recent_period'] : $user['recent_period'];
    }

    if (
        // New filter
    empty($_SESSION['filter_enabled']) or
        // Cache data updated
    $filter_key['time'] <= $user['cache_update_time'] or
        // Date, period, user are changed
    $filter_key['user'] != $user['id'] or
        $filter_key['recent_period'] != $filter['recent_period'] or
        $filter_key['date'] != date('Ymd')) {
        // Need to compute dats
        $filter_key = array(
            'user' => (int)$user['id'],
            'recent_period' => (int)$filter['recent_period'],
            'time' => time(),
            'date' => date('Ymd')
        );

        $filter['categories'] = get_computed_categories($user, (int)$filter['recent_period']);

        $filter['visible_categories'] = implode(',', array_keys($filter['categories']));
        if (empty($filter['visible_categories'])) {
            // Must be not empty
            $filter['visible_categories'] = -1;
        }

        $query = 'SELECT distinct image_id FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE ';

        if (!empty($filter['visible_categories'])) {
            $query .= ' category_id  ' . $conn->in($filter['visible_categories']) . ' AND ';
        }

        $query .= ' date_available >= ' . $conn->db_get_recent_period_expression($filter['recent_period']);

        $filter['visible_images'] = implode(',', $conn->query2array($query, null, 'image_id'));

        if (empty($filter['visible_images'])) {
            // Must be not empty
            $filter['visible_images'] = -1;
        }

        // Save filter data on session
        $_SESSION['filter_enabled'] = $filter['enabled'];
        $_SESSION['filter_check_key'] = $filter_key;
        $_SESSION['filter_categories'] = $filter['categories'];
        $_SESSION['filter_visible_categories'] = $filter['visible_categories'];
        $_SESSION['filter_visible_images'] = $filter['visible_images'];
    } else {
        // Read only data
        $filter['categories'] = isset($_SESSION['filter_categories']) ? $_SESSION['filter_categories'] : array();
        $filter['visible_categories'] = isset($_SESSION['filter_visible_categories']) ? $_SESSION['filter_visible_categories'] : '';
        $filter['visible_images'] = isset($_SESSION['filter_visible_images']) ? $_SESSION['filter_visible_images'] : '';
    }
    unset($filter_key);
    if (get_filter_page_value('add_notes')) {
        $header_notes[] = \Phyxo\Functions\Language::l10n_dec(
            'Photos posted within the last %d day.',
            'Photos posted within the last %d days.',
            $filter['recent_period']
        );
    }
    include_once(PHPWG_ROOT_PATH . 'include/functions_filter.inc.php');
} else {
    if (!empty($_SESSION['filter_enabled'])) {
        unset($_SESSION['filter_enabled'], $_SESSION['filter_check_key'], $_SESSION['filter_categories'],
            $_SESSION['filter_visible_categories'], $_SESSION['filter_visible_images']);
    }
}
