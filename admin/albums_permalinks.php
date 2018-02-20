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

if (!defined("ALBUMS_BASE_URL")) {
    die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions_permalinks.php');

$selected_cat = array();
if (isset($_POST['set_permalink']) and $_POST['cat_id']>0) {
    $permalink = $_POST['permalink'];
    if (empty($permalink)) {
        delete_cat_permalink($_POST['cat_id'], isset($_POST['save']));
    } else {
        set_cat_permalink($_POST['cat_id'], $permalink, isset($_POST['save']));
    }
    $selected_cat = array( $_POST['cat_id'] );
} elseif ( isset($_GET['delete_permanent'])) {
    $query = 'DELETE FROM '.OLD_PERMALINKS_TABLE;
    $query .= ' WHERE permalink=\''.$conn->db_real_escape_string($_GET['delete_permanent']).'\' LIMIT 1';
    $result = $conn->db_query($query);
    if ($conn->db_changes($result)==0) {
        $page['errors'][] = l10n('Cannot delete the old permalink !');
    }
}

$query = 'SELECT id,permalink,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
display_select_cat_wrapper($query, $selected_cat, 'categories', false);

// --- generate display of active permalinks -----------------------------------
$sort_by = parse_sort_variables(
    array('id', 'name', 'permalink'), 'name',
    'psf',
    array('delete_permanent'),
    'SORT_'
);

$query = 'SELECT id, permalink, uppercats, global_rank FROM '.CATEGORIES_TABLE.' WHERE permalink IS NOT NULL';
if ($sort_by[0]=='id' or $sort_by[0]=='permalink') {
    $query .= ' ORDER BY '.$sort_by[0];
}
$categories=array();
$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $row['name'] = get_cat_display_name_cache($row['uppercats']);
    $categories[] = $row;
}

if ($sort_by[0]=='name') {
    usort($categories, 'global_rank_compare');
}
$template->assign( 'permalinks', $categories );

// --- generate display of old permalinks --------------------------------------

$sort_by = parse_sort_variables(
    array('cat_id','permalink','date_deleted','last_hit','hit'), null,
    'dpsf',
    array('delete_permanent'),
    'SORT_OLD_', '#old_permalinks'
);

$url_del_base = ALBUMS_BASE_URL.'&map;section=permalinks';
$query = 'SELECT * FROM '.OLD_PERMALINKS_TABLE;
if (count($sort_by)) {
    $query .= ' ORDER BY '.$sort_by[0];
}
$result = $conn->db_query($query);
$deleted_permalinks = array();
while ($row = $conn->db_fetch_assoc($result)) {
    $row['name'] = get_cat_display_name_cache($row['cat_id']);
    $row['U_DELETE'] = add_url_params(
        $url_del_base,
        array('delete_permanent'=> $row['permalink'])
    );
    $deleted_permalinks[] = $row;
}
$template->assign('deleted_permalinks', $deleted_permalinks);
//$template->assign('U_HELP', get_root_url().'admin/popuphelp.php?page=permalinks');
