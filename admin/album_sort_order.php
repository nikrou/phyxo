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

/**
 * Change rank of images inside a category
 *
 */

if (!defined('ALBUM_BASE_URL')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

$page['category_id'] = $category['id'];

// +-----------------------------------------------------------------------+
// |                       global mode form submission                     |
// +-----------------------------------------------------------------------+

$image_order_choices = array('default', 'rank', 'user_define');
$image_order_choice = 'default';

if (isset($_POST['submit'])) {
    if (isset($_POST['rank_of_image'])) {
        asort($_POST['rank_of_image'], SORT_NUMERIC);

        save_images_order(
            $page['category_id'],
            array_keys($_POST['rank_of_image'])
        );

        $page['infos'][] = \Phyxo\Functions\Language::l10n('Images manual order was saved');
    }

    if (!empty($_POST['image_order_choice']) && in_array($_POST['image_order_choice'], $image_order_choices)) {
        $image_order_choice = $_POST['image_order_choice'];
    }

    $image_order = null;
    if ($image_order_choice == 'user_define') {
        for ($i = 0; $i < 3; $i++) {
            if (!empty($_POST['image_order'][$i])) {
                if (!empty($image_order)) {
                    $image_order .= ',';
                }
                $image_order .= $_POST['image_order'][$i];
            }
        }
    } elseif ($image_order_choice == 'rank') {
        $image_order = 'rank ASC';
    }
    $query = 'UPDATE ' . CATEGORIES_TABLE;
    $query .= ' SET image_order = ' . (isset($image_order) ? '\'' . $conn->db_real_escape_string($image_order) . '\'' : 'NULL');
    $query .= ' WHERE id=' . $conn->db_real_escape_string($page['category_id']);
    $conn->db_query($query);

    if (isset($_POST['image_order_subcats'])) {
        $cat_info = \Phyxo\Functions\Category::get_cat_info($page['category_id']);

        $query = 'UPDATE ' . CATEGORIES_TABLE;
        $query .= ' SET image_order = ' . (isset($image_order) ? '\'' . $conn->db_real_escape_string($image_order) . '\'' : 'NULL');
        $query .= ' WHERE uppercats LIKE \'' . $conn->db_real_escape_string($cat_info['uppercats']) . ',%\'';
        $conn->db_query($query);
    }

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Your configuration settings are saved');
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$base_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php';

$query = 'SELECT * FROM ' . CATEGORIES_TABLE . ' WHERE id = ' . $conn->db_real_escape_string($page['category_id']);
$category = $conn->db_fetch_assoc($conn->db_query($query));

if ($category['image_order'] == 'rank ASC') {
    $image_order_choice = 'rank';
} elseif ($category['image_order'] != '') {
    $image_order_choice = 'user_define';
}

// Navigation path
$navigation = \Phyxo\Functions\Category::get_cat_display_name_cache(
    $category['uppercats'],
    \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=album-'
);

$template->assign(
    array(
        'CATEGORIES_NAV' => $navigation,
        'F_ACTION' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(array()),
    )
);

// +-----------------------------------------------------------------------+
// |                              thumbnails                               |
// +-----------------------------------------------------------------------+

$query = 'SELECT id,file,path,representative_ext,width, height,rotation,';
$query .= ' name,rank FROM ' . IMAGES_TABLE;
$query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON image_id = id';
$query .= ' WHERE category_id = ' . $conn->db_real_escape_string($page['category_id']) . ' ORDER BY rank;';
$result = $conn->db_query($query);
if ($conn->db_num_rows($result) > 0) {
	// template thumbnail initialization
    $current_rank = 1;
    $derivativeParams = \Phyxo\Image\ImageStdParams::get_by_type(IMG_SQUARE);
    while ($row = $conn->db_fetch_assoc($result)) {
        $derivative = new \Phyxo\Image\DerivativeImage($derivativeParams, new \Phyxo\Image\SrcImage($row));

        if (!empty($row['name'])) {
            $thumbnail_name = $row['name'];
        } else {
            $file_wo_ext = \Phyxo\Functions\Utils::get_filename_wo_extension($row['file']);
            $thumbnail_name = str_replace('_', ' ', $file_wo_ext);
        }
        $current_rank++;
        $template->append(
            'thumbnails',
            array(
                'ID' => $row['id'],
                'NAME' => $thumbnail_name,
                'TN_SRC' => $derivative->get_url(),
                'RANK' => $current_rank * 10,
                'SIZE' => $derivative->get_size(),
            )
        );
    }
}
// image order management
$sort_fields = array(
    '' => '',
    'file ASC' => \Phyxo\Functions\Language::l10n('File name, A &rarr; Z'),
    'file DESC' => \Phyxo\Functions\Language::l10n('File name, Z &rarr; A'),
    'name ASC' => \Phyxo\Functions\Language::l10n('Photo title, A &rarr; Z'),
    'name DESC' => \Phyxo\Functions\Language::l10n('Photo title, Z &rarr; A'),
    'date_creation DESC' => \Phyxo\Functions\Language::l10n('Date created, new &rarr; old'),
    'date_creation ASC' => \Phyxo\Functions\Language::l10n('Date created, old &rarr; new'),
    'date_available DESC' => \Phyxo\Functions\Language::l10n('Date posted, new &rarr; old'),
    'date_available ASC' => \Phyxo\Functions\Language::l10n('Date posted, old &rarr; new'),
    'rating_score DESC' => \Phyxo\Functions\Language::l10n('Rating score, high &rarr; low'),
    'rating_score ASC' => \Phyxo\Functions\Language::l10n('Rating score, low &rarr; high'),
    'hit DESC' => \Phyxo\Functions\Language::l10n('Visits, high &rarr; low'),
    'hit ASC' => \Phyxo\Functions\Language::l10n('Visits, low &rarr; high'),
    'id ASC' => \Phyxo\Functions\Language::l10n('Numeric identifier, 1 &rarr; 9'),
    'id DESC' => \Phyxo\Functions\Language::l10n('Numeric identifier, 9 &rarr; 1'),
    'rank ASC' => \Phyxo\Functions\Language::l10n('Manual sort order'),
);

$template->assign('image_order_options', $sort_fields);

$image_order = explode(',', $category['image_order']);

for ($i = 0; $i < 3; $i++) { // 3 fields
    if (isset($image_order[$i])) {
        $template->append('image_order', $image_order[$i]);
    } else {
        $template->append('image_order', '');
    }
}

$template->assign('image_order_choice', $image_order_choice);

// +-----------------------------------------------------------------------+
// |                               functions                               |
// +-----------------------------------------------------------------------+

/**
 * save the rank depending on given images order
 *
 * The list of ordered images id is supposed to be in the same parent
 * category
 *
 * @param array categories
 * @return void
 */
function save_images_order($category_id, $images)
{
    global $conn;

    $current_rank = 0;
    $datas = array();
    foreach ($images as $id) {
        $datas[] = array(
            'category_id' => $category_id,
            'image_id' => $id,
            'rank' => ++$current_rank,
        );
    }
    $fields = array(
        'primary' => array('image_id', 'category_id'),
        'update' => array('rank')
    );
    $conn->mass_updates(IMAGE_CATEGORY_TABLE, $fields, $datas);
}
