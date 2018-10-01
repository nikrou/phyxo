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
    die("Hacking attempt!");
}

use App\Repository\OldPermalinkRepository;
use App\Repository\CategoryRepository;

$selected_cat = [];
if (isset($_POST['set_permalink']) and $_POST['cat_id'] > 0) {
    $permalink = $_POST['permalink'];
    if (empty($permalink)) {
        \Phyxo\Functions\Permalink::delete_cat_permalink($_POST['cat_id'], isset($_POST['save']));
    } else {
        \Phyxo\Functions\Permalink::set_cat_permalink($_POST['cat_id'], $permalink, isset($_POST['save']));
    }
    $selected_cat = [$_POST['cat_id']];
} elseif (isset($_GET['delete_permanent'])) {
    $result = (new OldPermalinkRepository($conn))->deleteByPermalink($_GET['delete_permanent']);
    if ($conn->db_changes($result) == 0) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Cannot delete the old permalink !');
    }
}

$result = (new CategoryRepository($conn))->findAll();
$categories = $conn->result2array($result);
\Phyxo\Functions\Category::display_select_cat_wrapper($categories, $selected_cat, 'categories', false);

// --- generate display of active permalinks -----------------------------------
$sort_by = \Phyxo\Functions\Permalink::parse_sort_variables(
    ['id', 'name', 'permalink'],
    'name',
    'psf',
    ['delete_permanent'],
    'SORT_'
);

if ($sort_by[0] == 'id' or $sort_by[0] == 'permalink') {
    $order = $sort_by[0];
} else {
    $order = '';
}
$result = (new CategoryRepository($conn))->findWithPermalinks($order);
$categories = [];
while ($row = $conn->db_fetch_assoc($result)) {
    $row['name'] = \Phyxo\Functions\Category::get_cat_display_name_cache($row['uppercats']);
    $categories[] = $row;
}

if ($sort_by[0] == 'name') {
    usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');
}
$template->assign('permalinks', $categories);

// --- generate display of old permalinks --------------------------------------

$sort_by = \Phyxo\Functions\Permalink::parse_sort_variables(
    ['cat_id', 'permalink', 'date_deleted', 'last_hit', 'hit'],
    null,
    'dpsf',
    ['delete_permanent'],
    'SORT_OLD_',
    '#old_permalinks'
);

$url_del_base = ALBUMS_BASE_URL . '&map;section=permalinks';
$result = (new OldPermalinkRepository($conn))->findAll((count($sort_by)) > 0 ? $sort_by[0] : null);
$deleted_permalinks = [];
while ($row = $conn->db_fetch_assoc($result)) {
    $row['name'] = \Phyxo\Functions\Category::get_cat_display_name_cache($row['cat_id']);
    $row['U_DELETE'] = \Phyxo\Functions\URL::add_url_params(
        $url_del_base,
        ['delete_permanent' => $row['permalink']]
    );
    $deleted_permalinks[] = $row;
}
$template->assign('deleted_permalinks', $deleted_permalinks);
