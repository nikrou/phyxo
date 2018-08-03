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

define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_GUEST);

if (empty($_GET['q'])) {
    redirect(make_index_url());
}

$search = array();
$search['q'] = $_GET['q'];

$query = 'SElECT id FROM ' . SEARCH_TABLE . ' WHERE rules = \'' . $conn->db_real_escape_string(serialize($search)) . '\'';
$search_id = $conn->query2array($query, null, 'id');
if (!empty($search_id)) {
    $search_id = $search_id[0];
    $query = 'UPDATE ' . SEARCH_TABLE . ' SET last_seen=NOW() WHERE id=' . $search_id;
    $conn->db_query($query);
} else {
    $query = 'INSERT INTO ' . SEARCH_TABLE . ' (rules, last_seen) VALUES (\'' . $conn->db_real_escape_string(serialize($search)) . '\', NOW() );';
    $conn->db_query($query);
    $search_id = $conn->db_insert_id(SEARCH_TABLE);
}

redirect(
    make_index_url(
        array(
            'section' => 'search',
            'search' => $search_id,
        )
    )
);
