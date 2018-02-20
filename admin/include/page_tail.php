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

trigger_notify('loc_begin_page_tail');

$template->assign(
    array(
        'VERSION' => $conf['show_version'] ? PHPWG_VERSION : '',
        'PHPWG_URL' => defined('PHPWG_URL') ? PHPWG_URL : '',
    )
);

//--------------------------------------------------------------------- contact

if (!$services['users']->isGuest()) {
    $template->assign(
        'CONTACT_MAIL', get_webmaster_mail_address()
    );
}

//------------------------------------------------------------- generation time
$debug_vars = array();

if ($conf['show_queries'] && !empty($conn)) {
    $debug_vars = array_merge(
        $debug_vars,
        array('QUERIES_LIST' => $conn->getQueries())
    );
}

if ($conf['show_gt']) {
    if (!isset($page['count_queries'])) {
        $page['count_queries'] = 0;
        $page['queries_time'] = 0;
    }
    $time = get_elapsed_time($t2, get_moment());

    if (!empty($conn)) {
        $debug_vars = array_merge(
            $debug_vars,
            array('TIME' => $time,
            'NB_QUERIES' => $conn->getQueriesCount(),
            'SQL_TIME' => number_format($conn->getQueriesTime(), 3, '.', ' ').' s')
        );
    }
}

$template->assign('debug', $debug_vars);

trigger_notify('loc_end_page_tail');
