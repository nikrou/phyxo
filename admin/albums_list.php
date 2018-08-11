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

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_cat_list');

if (!empty($_POST) or isset($_GET['delete'])) {
    \Phyxo\Functions\Utils::check_token();
}

$sort_orders = array(
    'name ASC' => \Phyxo\Functions\Language::l10n('Album name, A &rarr; Z'),
    'name DESC' => \Phyxo\Functions\Language::l10n('Album name, Z &rarr; A'),
    'date_creation DESC' => \Phyxo\Functions\Language::l10n('Date created, new &rarr; old'),
    'date_creation ASC' => \Phyxo\Functions\Language::l10n('Date created, old &rarr; new'),
    'date_available DESC' => \Phyxo\Functions\Language::l10n('Date posted, new &rarr; old'),
    'date_available ASC' => \Phyxo\Functions\Language::l10n('Date posted, old &rarr; new'),
);

// +-----------------------------------------------------------------------+
// |                               functions                               |
// +-----------------------------------------------------------------------+

/**
 * save the rank depending on given categories order
 *
 * The list of ordered categories id is supposed to be in the same parent
 * category
 *
 * @param array categories
 * @return void
 */
function save_categories_order($categories)
{
    global $conn;

    $current_rank_for_id_uppercat = array();
    $current_rank = 0;

    $datas = array();
    foreach ($categories as $category) {
        if (is_array($category)) {
            $id = $category['id'];
            $id_uppercat = $category['id_uppercat'];

            if (!isset($current_rank_for_id_uppercat[$id_uppercat])) {
                $current_rank_for_id_uppercat[$id_uppercat] = 0;
            }
            $current_rank = ++$current_rank_for_id_uppercat[$id_uppercat];
        } else {
            $id = $category;
            $current_rank++;
        }

        $datas[] = array('id' => $id, 'rank' => $current_rank);
    }
    $fields = array('primary' => array('id'), 'update' => array('rank'));
    $conn->mass_updates(CATEGORIES_TABLE, $fields, $datas);

    update_global_rank();
}

function get_categories_ref_date($ids, $field = 'date_available', $minmax = 'max')
{
    global $conn;

    // we need to work on the whole tree under each category, even if we don't
    // want to sort sub categories
    $category_ids = get_subcat_ids($ids);

    // search for the reference date of each album
    $query = 'SELECT category_id,' . $minmax . '(' . $field . ') as ref_date FROM ' . IMAGES_TABLE;
    $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON image_id = id';
    $query .= ' WHERE category_id ' . $conn->in($category_ids);
    $query .= ' GROUP BY category_id;';
    $ref_dates = $conn->query2array($query, 'category_id', 'ref_date');

    // the iterate on all albums (having a ref_date or not) to find the
    // reference_date, with a search on sub-albums
    $query = 'SELECT id,uppercats FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id ' . $conn->in($category_ids);
    $uppercats_of = $conn->query2array($query, 'id', 'uppercats');

    foreach (array_keys($uppercats_of) as $cat_id) {
        // find the subcats
        $subcat_ids = array();

        foreach ($uppercats_of as $id => $uppercats) {
            if (preg_match('/(^|,)' . $cat_id . '(,|$)/', $uppercats)) {
                $subcat_ids[] = $id;
            }
        }

        $to_compare = array();
        foreach ($subcat_ids as $id) {
            if (isset($ref_dates[$id])) {
                $to_compare[] = $ref_dates[$id];
            }
        }

        if (count($to_compare) > 0) {
            $ref_dates[$cat_id] = 'max' == $minmax ? max($to_compare) : min($to_compare);
        } else {
            $ref_dates[$cat_id] = null;
        }
    }

    // only return the list of $ids, not the sub-categories
    $return = array();
    foreach ($ids as $id) {
        $return[$id] = $ref_dates[$id];
    }

    return $return;
}

// +-----------------------------------------------------------------------+
// |                            initialization                             |
// +-----------------------------------------------------------------------+

\Phyxo\Functions\Utils::check_input_parameter('parent_id', $_GET, false, PATTERN_ID);

$categories = array();

$navigation = '<a href="' . ALBUMS_BASE_URL . '">';
$navigation .= \Phyxo\Functions\Language::l10n('Home');
$navigation .= '</a>';


// +-----------------------------------------------------------------------+
// |                    virtual categories management                      |
// +-----------------------------------------------------------------------+
// request to delete a virtual category
if (isset($_GET['delete']) and is_numeric($_GET['delete'])) {
    delete_categories(array($_GET['delete']));
    $_SESSION['page_infos'] = array(\Phyxo\Functions\Language::l10n('Virtual album deleted'));
    update_global_rank();
    invalidate_user_cache();

    $redirect_url = ALBUMS_BASE_URL . '&amp;section=list';
    if (isset($_GET['parent_id'])) {
        $redirect_url .= '&parent_id=' . $_GET['parent_id'];
    }
    \Phyxo\Functions\Utils::redirect($redirect_url);
} elseif (isset($_POST['submitAdd'])) { // request to add a virtual category
    $output_create = create_virtual_category(
        $_POST['virtual_name'],
        @$_GET['parent_id']
    );

    invalidate_user_cache();
    if (isset($output_create['error'])) {
        $page['errors'][] = $output_create['error'];
    } else {
        $page['infos'][] = $output_create['info'];
    }
} elseif (isset($_POST['submitManualOrder'])) { // save manual category ordering
    asort($_POST['catOrd'], SORT_NUMERIC);
    save_categories_order(array_keys($_POST['catOrd']));

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Album manual order was saved');
} elseif (isset($_POST['submitAutoOrder'])) {
    if (!isset($sort_orders[$_POST['order_by']])) {
        die('Invalid sort order');
    }

    $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id_uppercat ';
    $query .= !isset($_GET['parent_id']) ? 'IS NULL' : '= ' . $conn->db_real_escape_string($_GET['parent_id']);
    $category_ids = $conn->query2array($query, null, 'id');

    if (isset($_POST['recursive'])) {
        $category_ids = get_subcat_ids($category_ids);
    }

    $categories = array();
    $sort = array();

    list($order_by_field, $order_by_asc) = explode(' ', $_POST['order_by']);

    $order_by_date = false;
    if (strpos($order_by_field, 'date_') === 0) {
        $order_by_date = true;

        $ref_dates = get_categories_ref_date(
            $category_ids,
            $order_by_field,
            'ASC' == $order_by_asc ? 'min' : 'max'
        );
    }

    $query = 'SELECT id, name, id_uppercat FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id ' . $conn->in($category_ids);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        if ($order_by_date) {
            $sort[] = $ref_dates[$row['id']];
        } else {
            $sort[] = $row['name'];
        }

        $categories[] = array(
            'id' => $row['id'],
            'id_uppercat' => $row['id_uppercat'],
        );
    }

    array_multisort(
        $sort,
        SORT_REGULAR,
        'ASC' == $order_by_asc ? SORT_ASC : SORT_DESC,
        $categories
    );

    save_categories_order($categories);

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Albums automatically sorted');
}

// +-----------------------------------------------------------------------+
// |                            Navigation path                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['parent_id'])) {
    $navigation .= $conf['level_separator'];

    $navigation .= get_cat_display_name_from_id(
        $_GET['parent_id'],
        ALBUMS_BASE_URL . '&amp;section=list&amp;parent_id='
    );
}
// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$form_action = ALBUMS_BASE_URL;
if (isset($_GET['parent_id'])) {
    $form_action .= '&amp;parent_id=' . $_GET['parent_id'];
}
$sort_orders_checked = array_keys($sort_orders);

$template->assign(array(
    'CATEGORIES_NAV' => $navigation,
    'F_ACTION' => $form_action,
    'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    'sort_orders' => $sort_orders,
    'sort_order_checked' => array_shift($sort_orders_checked),
));

// +-----------------------------------------------------------------------+
// |                          Categories display                           |
// +-----------------------------------------------------------------------+

$categories = array();

$query = 'SELECT id, name, permalink, dir, rank, status FROM ' . CATEGORIES_TABLE;
if (!isset($_GET['parent_id'])) {
    $query .= ' WHERE id_uppercat IS NULL';
} else {
    $query .= ' WHERE id_uppercat = ' . $conn->db_real_escape_string($_GET['parent_id']);
}
$query .= ' ORDER BY rank ASC;';
$categories = $conn->query2array($query, 'id');

// get the categories containing images directly
$categories_with_images = array();
if (count($categories)) {
    $query = 'SELECT category_id,COUNT(1) AS nb_photos FROM ' . IMAGE_CATEGORY_TABLE;
    $query .= ' GROUP BY category_id;';

    $nb_photos_in = $conn->query2array($query, 'category_id', 'nb_photos');

    $query = 'SELECT id,uppercats FROM ' . CATEGORIES_TABLE;
    $all_categories = $conn->query2array($query, 'id', 'uppercats');
    $subcats_of = array();

    foreach (array_keys($categories) as $cat_id) {
        foreach ($all_categories as $id => $uppercats) {
            if (preg_match('/(^|,)' . $cat_id . ',/', $uppercats)) {
                @$subcats_of[$cat_id][] = $id;
            }
        }
    }

    $nb_sub_photos = array();
    foreach ($subcats_of as $cat_id => $subcat_ids) {
        $nb_photos = 0;
        foreach ($subcat_ids as $id) {
            if (isset($nb_photos_in[$id])) {
                $nb_photos += $nb_photos_in[$id];
            }
        }

        $nb_sub_photos[$cat_id] = $nb_photos;
    }
}

$template->assign('categories', array());
$base_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=';
$cat_list_url = ALBUMS_BASE_URL . '&amp;section=list';

if (isset($_GET['parent_id'])) {
    $template->assign('PARENT_EDIT', $base_url . 'album&amp;cat_id=' . $_GET['parent_id']);
}

foreach ($categories as $category) {
    $self_url = $cat_list_url;
    if (isset($_GET['parent_id'])) {
        $self_url .= '&amp;parent_id=' . $_GET['parent_id'];
    }

    $tpl_cat = array(
        'NAME' => \Phyxo\Functions\Plugin::trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
        'NB_PHOTOS' => isset($nb_photos_in[$category['id']]) ? $nb_photos_in[$category['id']] : 0,
        'NB_SUB_PHOTOS' => isset($nb_sub_photos[$category['id']]) ? $nb_sub_photos[$category['id']] : 0,
        'NB_SUB_ALBUMS' => isset($subcats_of[$category['id']]) ? count($subcats_of[$category['id']]) : 0,
        'ID' => $category['id'],
        'RANK' => $category['rank'] * 10,
        'U_JUMPTO' => \Phyxo\Functions\URL::make_index_url(array('category' => $category)),
        'U_CHILDREN' => $cat_list_url . '&amp;parent_id=' . $category['id'],
        'U_EDIT' => $base_url . 'album&amp;cat_id=' . $category['id'],
        'IS_VIRTUAL' => empty($category['dir'])
    );

    if (empty($category['dir'])) {
        $tpl_cat['U_DELETE'] = $self_url . '&amp;delete=' . $category['id'];
        $tpl_cat['U_DELETE'] .= '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token();
    } else {
        if ($conf['enable_synchronization']) {
            $tpl_cat['U_SYNC'] = $base_url . 'site_update&amp;site=1&amp;cat_id=' . $category['id'];
        }
    }

    $template->append('categories', $tpl_cat);
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_cat_list');
