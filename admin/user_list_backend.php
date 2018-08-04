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

define('PHPWG_ROOT_PATH', '../');
define('IN_ADMIN', true);

include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Easy set variables
 */

/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */
$aColumns = array(
    $conf['user_fields']['id'],
    $conf['user_fields']['username'],
    'status',
    $conf['user_fields']['email'],
    'recent_period',
    'level',
    'registration_date'
);

$aColumns = trigger_change('user_list_columns', $aColumns);

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = 'user_id';

/* DB table to use */
$sTable = USERS_TABLE . ' LEFT JOIN ' . USER_INFOS_TABLE . ' AS ui ON ' . $conf['user_fields']['id'] . ' = ui.user_id';

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * If you just want to use the basic configuration for DataTables with PHP server-side, there is
 * no need to edit below this line
 */

/*
 * Paging
 */
$sLimit = '';
if (isset($_REQUEST['iDisplayStart']) && $_REQUEST['iDisplayLength'] != '-1') {
    $sLimit = sprintf(
        'LIMIT %d OFFSET %d',
        $conn->db_real_escape_string($_REQUEST['iDisplayLength']),
        $conn->db_real_escape_string($_REQUEST['iDisplayStart'])
    );
}

/*
 * Ordering
 */
if (isset($_REQUEST['iSortCol_0'])) {
    $sOrder = 'ORDER BY  ';
    for ($i = 0; $i < intval($_REQUEST['iSortingCols']); $i++) {
        if ($_REQUEST['bSortable_' . intval($_REQUEST['iSortCol_' . $i])] == 'true') {
            $sOrder .= $aColumns[intval($_REQUEST['iSortCol_' . $i])];
            $sOrder .= ' ' . $conn->db_real_escape_string($_REQUEST['sSortDir_' . $i]) . ', ';
        }
    }

    $sOrder = substr_replace($sOrder, '', -2);
    if ($sOrder == 'ORDER BY') {
        $sOrder = '';
    }
}


/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */
$sWhere = '';
if ($_REQUEST['sSearch'] != '') {
    $sWhere = "WHERE (";
    for ($i = 0; $i < count($aColumns); $i++) {
        $sWhere .= $aColumns[$i] . " LIKE '%" . $conn->db_real_escape_string($_REQUEST['sSearch']) . "%' OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3);
    $sWhere .= ')';
}

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_REQUEST['bSearchable_' . $i]) && isset($_REQUEST['sSearch_' . $i]) && $_REQUEST['bSearchable_' . $i] == "true" && $_REQUEST['sSearch_' . $i] != '') {
        if ($sWhere == '') {
            $sWhere = 'WHERE ';
        } else {
            $sWhere .= ' AND ';
        }
        $sWhere .= $aColumns[$i] . " LIKE '%" . $conn->db_real_escape_string($_REQUEST['sSearch_' . $i]) . "%' ";
    }
}


/*
 * SQL queries
 * Get data to display
 */
$sQuery = 'SELECT ' . str_replace(' , ', ' ', implode(', ', $aColumns)) . ' FROM ' . $sTable;
$sQuery .= $sWhere . ' ' . $sOrder . ' ' . $sLimit;
$rResult = $conn->db_query($sQuery);

/* Data set length after filtering */
$iFilteredTotal = 0;

/* Total data set length */
$sQuery = "SELECT COUNT(" . $sIndexColumn . ") FROM   $sTable";
$rResultTotal = $conn->db_query($sQuery);
$aResultTotal = $conn->db_fetch_row($rResultTotal);
$iTotal = $aResultTotal[0];


/*
 * Output
 */
$output = array(
    'sEcho' => intval($_REQUEST['sEcho']),
    'iTotalRecords' => $iTotal,
    'iTotalDisplayRecords' => $iFilteredTotal,
    'aaData' => array()
);

$user_ids = array();

while ($aRow = $conn->db_fetch_assoc($rResult)) {
    $user_ids[] = $aRow[$conf['user_fields']['id']];

    $row = array();
    for ($i = 0; $i < count($aColumns); $i++) {
        if ($aColumns[$i] == 'status') {
            $row[] = \Phyxo\Functions\Language::l10n('user_status_' . $aRow[$aColumns[$i]]);
        } elseif ($aColumns[$i] == 'level') {
            $row[] = $aRow[$aColumns[$i]] == 0 ? '' : \Phyxo\Functions\Language::l10n(sprintf('Level %d', $aRow[$aColumns[$i]]));
        } elseif ($aColumns[$i] != ' ') {
            /* General output */
            $colname = $aColumns[$i];
            foreach ($conf['user_fields'] as $real_name => $alias) {
                if ($aColumns[$i] == $real_name) {
                    $colname = $alias;
                }
            }
            $row[] = $aRow[$colname];
        }
    }
    $output['aaData'][] = $row;
}

// replace "recent_period" by the list of groups
if (count($user_ids) > 0) {
    $groups_of_user = array();

    $query = 'SELECT user_id, name FROM ' . USER_GROUP_TABLE;
    $query .= ' LEFT JOIN ' . GROUPS_TABLE . ' ON id = group_id';
    $query .= ' WHERE user_id ' . $conn->in($user_ids);

    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        if (empty($groups_of_user[$row['user_id']])) {
            $groups_of_user[$row['user_id']] = $row['name'];
        } else {
            $groups_of_user[$row['user_id']] .= ',' . $row['name'];
        }
    }

    $key_replace = array_search('recent_period', $aColumns);

    // replacement
    foreach (array_keys($output['aaData']) as $idx) {
        $user_id = $output['aaData'][$idx][0];
        $output['aaData'][$idx][$key_replace] = isset($groups_of_user[$user_id]) ? $groups_of_user[$user_id] : '';
    }
}

$output = trigger_change('after_render_user_list', $output);

echo json_encode($output);
