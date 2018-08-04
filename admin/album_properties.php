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

if (!defined('ALBUM_BASE_URL')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'include/functions_mail.inc.php');

trigger_notify('loc_begin_cat_modify');

//---------------------------------------------------------------- verification
if (!isset($_GET['cat_id']) || !is_numeric($_GET['cat_id'])) {
    trigger_error('missing cat_id param', E_USER_ERROR);
}

//--------------------------------------------------------- form criteria check
if (isset($_POST['submit'])) {
    $data = array('id' => $_GET['cat_id'], 'name' => '', 'comment' => '');

    if (!empty($_POST['name'])) {
        $data['name'] = $_POST['name'];
    }

    if (!empty($_POST['comment'])) {
        $data['comment'] = $conf['allow_html_descriptions'] ? $_POST['comment'] : strip_tags($_POST['comment']);
    }

    if ($conf['activate_comments']) {
        $data['commentable'] = isset($_POST['commentable']) ? $conn->get_boolean($_POST['commentable']) : false;
    }

    $conn->single_update(
        CATEGORIES_TABLE,
        $data,
        array('id' => $data['id'])
    );
    if (!empty($_POST['apply_commentable_on_sub'])) {
        $subcats = get_subcat_ids(array('id' => $data['id']));
        $query = 'UPDATE ' . CATEGORIES_TABLE;
        $query .= ' SET commentable = \'' . $conn->boolean_to_db($data['commentable']) . '\'';
        $query .= ' WHERE id ' . $conn->in($subcats);
        $conn->db_query($query);
    }

    if (!empty($_POST['apply_commentable_on_sub'])) {
        $subcats = get_subcat_ids(array('id' => $data['id']));
        $query = 'UPDATE ' . CATEGORIES_TABLE;
        $query .= ' SET commentable = \'' . $conn->boolean_to_db($data['commentable']) . '\'';
        $query .= ' WHERE id ' . $conn->in($subcats);
        $conn->db_query($query);
    }

    // retrieve cat infos before continuing (following updates are expensive)
    $cat_info = get_cat_info($_GET['cat_id']);

    if (!empty($_POST['visible'])) {
        if ($_POST['visible'] == 'true_sub') {
            set_cat_visible(array($_GET['cat_id']), true, true);
        } elseif ($cat_info['visible'] != $conn->get_boolean($_POST['visible'])) {
            set_cat_visible(array($_GET['cat_id']), $conn->get_boolean($_POST['visible']));
        }
    }

    // in case the use moves his album to the gallery root, we force
    // $_POST['parent'] from 0 to null to be compared with
    // $cat_info['id_uppercat']
    if (empty($_POST['parent'])) {
        $_POST['parent'] = null;
    }

    // only move virtual albums
    if (empty($cat_info['dir']) and $cat_info['id_uppercat'] != $_POST['parent']) {
        move_categories(array($_GET['cat_id']), $_POST['parent']);
    }

    $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Album updated successfully');
    $redirect = true;
} elseif (isset($_POST['set_random_representant'])) {
    set_random_representant(array($_GET['cat_id']));
    $redirect = true;
} elseif (isset($_POST['delete_representant'])) {
    $query = 'UPDATE ' . CATEGORIES_TABLE;
    $query .= ' SET representative_picture_id = NULL WHERE id = ' . $conn->db_real_escape_string($_GET['cat_id']);
    $conn->db_query($query);
    $redirect = true;
}

if (isset($redirect)) {
    redirect(ALBUM_BASE_URL . '&amp;section=properties&amp;cat_id=' . $category['id']);
}

// nullable fields
foreach (array('comment', 'dir', 'site_id', 'id_uppercat') as $nullable) {
    if (!isset($category[$nullable])) {
        $category[$nullable] = '';
    }
}

$category['is_virtual'] = empty($category['dir']) ? true : false;

$query = 'SELECT DISTINCT category_id FROM ' . IMAGE_CATEGORY_TABLE;
$query .= ' WHERE category_id = ' . $conn->db_real_escape_string($_GET['cat_id']) . ' LIMIT 1';
$result = $conn->db_query($query);
$category['has_images'] = $conn->db_num_rows($result) > 0 ? true : false;

// Navigation path
$navigation = get_cat_display_name_cache(
    $category['uppercats'],
    ALBUM_BASE_URL . '&amp;section=properties&amp;cat_id='
);

$form_action = ALBUM_BASE_URL . '&amp;section=properties&amp;cat_id=' . $category['id'];

//----------------------------------------------------- template initialization

$base_url = get_root_url() . 'admin/index.php?page=';
$cat_list_url = $base_url . 'albums';

$self_url = $cat_list_url;
if (!empty($category['id_uppercat'])) {
    $self_url .= '&amp;parent_id=' . $category['id_uppercat'];
}

$template->assign(
    array(
        'CATEGORIES_NAV' => $navigation,
        'CAT_ID' => $category['id'],
        'CAT_NAME' => @htmlspecialchars($category['name']), // @TODO: remove arobase
        'CAT_COMMENT' => @htmlspecialchars($category['comment']), // @TODO: remove arobase
        'CAT_VISIBLE' => $conn->boolean_to_string($category['visible']),
        'U_JUMPTO' => make_index_url(array('category' => $category)),
        'U_ADD_PHOTOS_ALBUM' => $base_url . 'photos_add&amp;album=' . $category['id'],
        'U_CHILDREN' => $cat_list_url . '&amp;parent_id=' . $category['id'],
        //'U_HELP' => get_root_url().'admin/popuphelp.php?page=cat_modify',
        'F_ACTION' => $form_action,
    )
);

if ($conf['activate_comments']) {
    $template->assign('CAT_COMMENTABLE', $conn->boolean_to_string($category['commentable']));
}

// manage album elements link
if ($category['has_images']) {
    $template->assign(
        'U_MANAGE_ELEMENTS',
        $base_url . 'batch_manager&amp;filter=album-' . $category['id']
    );

    $query = 'SELECT COUNT(image_id), MIN(DATE(date_available)), MAX(DATE(date_available)) FROM ' . IMAGES_TABLE;
    $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON image_id = id';
    $query .= ' WHERE category_id = ' . $category['id'] . ';';
    list($image_count, $min_date, $max_date) = $conn->db_fetch_row($conn->db_query($query));

    if ($min_date == $max_date) {
        $intro = \Phyxo\Functions\Language::l10n(
            'This album contains %d photos, added on %s.',
            $image_count,
            format_date($min_date)
        );
    } else {
        $intro = \Phyxo\Functions\Language::l10n(
            'This album contains %d photos, added between %s and %s.',
            $image_count,
            format_date($min_date),
            format_date($max_date)
        );
    }
} else {
    $intro = \Phyxo\Functions\Language::l10n('This album contains no photo.');
}

$intro .= '<br>' . \Phyxo\Functions\Language::l10n('Numeric identifier : %d', $category['id']);

$template->assign(array(
    'INTRO' => $intro,
    'U_MANAGE_RANKS' => $base_url . 'element_set_ranks&amp;cat_id=' . $category['id'],
    'CACHE_KEYS' => get_admin_client_cache_keys(array('categories')),
));

if ($category['is_virtual']) {
    $template->assign(
        array(
            'U_DELETE' => $self_url . '&amp;delete=' . $category['id'] . '&amp;pwg_token=' . get_pwg_token(),
        )
    );
} else {
    $category['cat_full_dir'] = get_complete_dir($_GET['cat_id']);
    $template->assign(
        array(
            'CAT_FULL_DIR' => preg_replace('/\/$/', '', $category['cat_full_dir'])
        )
    );

    if ($conf['enable_synchronization']) {
        $template->assign(
            'U_SYNC',
            $base_url . 'site_update&amp;site=1&amp;cat_id=' . $category['id']
        );
    }
}

// representant management
if ($category['has_images'] || !empty($category['representative_picture_id'])) {
    $tpl_representant = array();

    // picture to display : the identified representant or the generic random
    // representant ?
    if (!empty($category['representative_picture_id'])) {
        $query = 'SELECT id,representative_ext,path FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id = ' . $category['representative_picture_id'];
        $row = $conn->db_fetch_assoc($conn->db_query($query));
        $src = DerivativeImage::thumb_url($row);
        $url = get_root_url() . 'admin/index.php?page=photo&amp;image_id=' . $category['representative_picture_id'];

        $tpl_representant['picture'] =
            array(
            'SRC' => $src,
            'URL' => $url
        );
    }

    // can the admin choose to set a new random representant ?
    $tpl_representant['ALLOW_SET_RANDOM'] = ($category['has_images']) ? true : false;

    // can the admin delete the current representant ?
    if (($category['has_images'] && $conf['allow_random_representative'])
        or (!$category['has_images'] and !empty($category['representative_picture_id']))) {
        $tpl_representant['ALLOW_DELETE'] = true;
    }
    $template->assign('representant', $tpl_representant);
}

if ($category['is_virtual']) {
    $template->assign('parent_category', empty($category['id_uppercat']) ? array() : array($category['id_uppercat']));
}

trigger_notify('loc_end_cat_modify');


// get_complete_dir returns the concatenation of get_site_url and
// get_local_dir
// Example : "pets > rex > 1_year_old" is on the the same site as the
// Piwigo files and this category has 22 for identifier
// get_complete_dir(22) returns "./galleries/pets/rex/1_year_old/"
function get_complete_dir($category_id)
{
    return get_site_url($category_id) . get_local_dir($category_id);
}

// get_local_dir returns an array with complete path without the site url
// Example : "pets > rex > 1_year_old" is on the the same site as the
// Piwigo files and this category has 22 for identifier
// get_local_dir(22) returns "pets/rex/1_year_old/"
function get_local_dir($category_id)
{
    global $page, $conn;

    $uppercats = '';
    $local_dir = '';

    if (isset($page['plain_structure'][$category_id]['uppercats'])) {
        $uppercats = $page['plain_structure'][$category_id]['uppercats'];
    } else {
        $query = 'SELECT uppercats';
        $query .= ' FROM ' . CATEGORIES_TABLE . ' WHERE id = ' . $category_id;
        $row = $conn->db_fetch_assoc($conn->db_query($query));
        $uppercats = $row['uppercats'];
    }

    $upper_array = explode(',', $uppercats);

    $database_dirs = array();
    $query = 'SELECT id,dir';
    $query .= ' FROM ' . CATEGORIES_TABLE . ' WHERE id ' . $conn->in($uppercats);
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $database_dirs[$row['id']] = $row['dir'];
    }
    foreach ($upper_array as $id) {
        $local_dir .= $database_dirs[$id] . '/';
    }

    return $local_dir;
}

// retrieving the site url : "http://domain.com/gallery/" or
// simply "./galleries/"
function get_site_url($category_id)
{
    global $page, $conn;

    $query = 'SELECT galleries_url FROM ' . SITES_TABLE . ' AS s,' . CATEGORIES_TABLE . ' AS c';
    $query .= ' WHERE s.id = c.site_id AND c.id = ' . $category_id;
    $row = $conn->db_fetch_assoc($conn->db_query($query));

    return $row['galleries_url'];
}
