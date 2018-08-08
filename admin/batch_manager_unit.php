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
 * Management of elements set. Elements can belong to a category or to the
 * user caddie.
 *
 */

if (!defined('BATCH_MANAGER_BASE_URL')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

trigger_notify('loc_begin_element_set_unit');

// +-----------------------------------------------------------------------+
// |                        unit mode form submission                      |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit'])) {
    $collection = explode(',', $_POST['element_ids']);

    $datas = array();

    $query = 'SELECT id, date_creation FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id ' . $conn->in($collection);
    $result = $conn->db_query($query);

    while ($row = $conn->db_fetch_assoc($result)) {
        $data = array();

        $data['id'] = $row['id'];
        $data['name'] = $_POST['name-' . $row['id']];
        $data['author'] = $_POST['author-' . $row['id']];
        $data['level'] = $_POST['level-' . $row['id']];

        if ($conf['allow_html_descriptions']) {
            $data['comment'] = @$_POST['description-' . $row['id']]; // @TODO: remove arobase !!
        } else {
            $data['comment'] = strip_tags(@$_POST['description-' . $row['id']]);// @TODO: remove arobase and strip_tags !!
        }

        if (!empty($_POST['date_creation-' . $row['id']])) {
            $data['date_creation'] = $_POST['date_creation-' . $row['id']];
        } else {
            $data['date_creation'] = null;
        }

        $datas[] = $data;

        // tags management
        $tag_ids = array();
        if (!empty($_POST['tags-' . $row['id']])) {
            $tag_ids = $services['tags']->getTagIds($_POST['tags-' . $row['id']]);
        }
        $services['tags']->setTags($tag_ids, $row['id']);
    }

    $conn->mass_updates(
        IMAGES_TABLE,
        array(
            'primary' => array('id'),
            'update' => array('name', 'author', 'level', 'comment', 'date_creation')
        ),
        $datas
    );

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Photo informations updated');
    invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$base_url = PHPWG_ROOT_PATH . 'admin/index.php';

$template->assign(
    array(
        'U_ELEMENTS_PAGE' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(array('display', 'start')),
        'F_ACTION' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(array()),
        'level_options' => get_privacy_level_options(),
    )
);

// +-----------------------------------------------------------------------+
// |                        global mode thumbnails                         |
// +-----------------------------------------------------------------------+

// how many items to display on this page
if (!empty($_GET['display'])) {
    $page['nb_images'] = intval($_GET['display']);
} else {
    $page['nb_images'] = 5;
}



if (count($page['cat_elements_id']) > 0) {
    $nav_bar = create_navigation_bar(
        $base_url . \Phyxo\Functions\URL::get_query_string_diff(array('start')),
        count($page['cat_elements_id']),
        $page['start'],
        $page['nb_images']
    );
    $template->assign(array('navbar' => $nav_bar));

    $element_ids = array();

    $is_category = false;
    if (isset($_SESSION['bulk_manager_filter']['category']) && !isset($_SESSION['bulk_manager_filter']['category_recursive'])) {
        $is_category = true;
    }

    if (isset($_SESSION['bulk_manager_filter']['prefilter']) && 'duplicates' == $_SESSION['bulk_manager_filter']['prefilter']) {
        $conf['order_by'] = ' ORDER BY file, id';
    }

    $query = 'SELECT * FROM ' . IMAGES_TABLE;

    if ($is_category) {
        $category_info = get_cat_info($_SESSION['bulk_manager_filter']['category']);

        $conf['order_by'] = $conf['order_by_inside_category'];
        if (!empty($category_info['image_order'])) {
            $conf['order_by'] = ' ORDER BY ' . $category_info['image_order'];
        }

        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id = image_id';
    }

    $query .= ' WHERE id ' . $conn->in($page['cat_elements_id']);

    if ($is_category) {
        $query .= ' AND category_id = ' . $conn->db_real_escape_string($_SESSION['bulk_manager_filter']['category']);
    }

    $query .= ' ' . $conf['order_by'] . ' LIMIT ' . $conn->db_real_escape_string($page['nb_images']);
    $query .= ' OFFSET ' . $conn->db_real_escape_string($page['start']);
    $result = $conn->db_query($query);

    // @TODO : query in a loop ???? getTagsList is another query
    while ($row = $conn->db_fetch_assoc($result)) {
        $element_ids[] = $row['id'];

        $src_image = new \Phyxo\Image\SrcImage($row);

        $query = 'SELECT id,name FROM ' . TAGS_TABLE . ' AS t';
        $query .= ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' AS it ON t.id = it.tag_id';
        $query .= ' WHERE image_id = ' . $row['id'] . ';';
        $tag_selection = $services['tags']->getTagsList($query);

        $legend = render_element_name($row);
        if ($legend != get_name_from_file($row['file'])) {
            $legend .= ' (' . $row['file'] . ')';
        }

        $template->append(
            'elements',
            array_merge(
                $row,
                array(
                    'ID' => $row['id'],
                    'TN_SRC' => \Phyxo\Image\DerivativeImage::url(IMG_THUMB, $src_image),
                    'FILE_SRC' => \Phyxo\Image\DerivativeImage::url(IMG_LARGE, $src_image),
                    'LEGEND' => $legend,
                    'U_EDIT' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&image_id=' . $row['id'],
                    'NAME' => htmlspecialchars(@$row['name']), // @TODO: remove arobase
                    'AUTHOR' => htmlspecialchars(@$row['author']), // @TODO: remove arobase
                    'LEVEL' => !empty($row['level']) ? $row['level'] : '0',
                    'DESCRIPTION' => htmlspecialchars(@$row['comment']), // @TODO: remove arobase
                    'DATE_CREATION' => $row['date_creation'],
                    'TAGS' => $tag_selection,
                )
            )
        );
    }

    $template->assign(array(
        'ELEMENT_IDS' => implode(',', $element_ids),
        'CACHE_KEYS' => get_admin_client_cache_keys(array('tags')),
    ));
}

trigger_notify('loc_end_element_set_unit');
