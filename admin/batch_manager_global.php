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

use Phyxo\LocalSiteReader;

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

trigger_notify('loc_begin_element_set_global');

check_input_parameter('del_tags', $_POST, true, PATTERN_ID);
check_input_parameter('associate', $_POST, false, PATTERN_ID);
check_input_parameter('move', $_POST, false, PATTERN_ID);
check_input_parameter('dissociate', $_POST, false, PATTERN_ID);

// +-----------------------------------------------------------------------+
// |                            current selection                          |
// +-----------------------------------------------------------------------+

$collection = array();
if (isset($_POST['setSelected'])) {
    $collection = $page['cat_elements_id'];
} elseif (isset($_POST['selection'])) {
    $collection = $_POST['selection'];
}

// +-----------------------------------------------------------------------+
// |                       global mode form submission                     |
// +-----------------------------------------------------------------------+

// $page['prefilter'] is a shortcut to test if the current filter contains a
// given prefilter. The idea is to make conditions simpler to write in the
// code.
$page['prefilter'] = 'none';
if (isset($_SESSION['bulk_manager_filter']['prefilter'])) {
    $page['prefilter'] = $_SESSION['bulk_manager_filter']['prefilter'];
}

$redirect_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=' . $_GET['page'];

if (isset($_POST['submit'])) {
    // if the user tries to apply an action, it means that there is at least 1
    // photo in the selection
    if (count($collection) == 0) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one photo');
    }

    $action = $_POST['selectAction'];
    $redirect = false;

    if ('remove_from_caddie' == $action) {
        $query = 'DELETE FROM ' . CADDIE_TABLE;
        $query .= ' WHERE element_id ' . $conn->in($collection);
        $query .= ' AND user_id = ' . $conn->db_real_escape_string($user['id']);
        $conn->db_query($query);

        // remove from caddie action available only in caddie so reload content
        $redirect = true;
    } elseif ('add_tags' == $action) {
        if (empty($_POST['add_tags'])) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one tag');
        } else {
            $tag_ids = $services['tags']->getTagsIds($_POST['add_tags']);
            $services['tags']->addTags($tag_ids, $collection);

            if ('no_tag' == $page['prefilter']) {
                $redirect = true;
            }
        }
    } elseif ('del_tags' == $action) {
        if (isset($_POST['del_tags']) and count($_POST['del_tags']) > 0) {
            // @TODO: move delete to src/Phyxo/Model/Repository/Tags::dissociateTags
            $query = 'DELETE FROM ' . IMAGE_TAG_TABLE;
            $query .= ' WHERE image_id ' . $conn->in($collection);
            $query .= ' AND tag_id ' . $conn->in($_POST['del_tags']);
            $conn->db_query($query);

            if (isset($_SESSION['bulk_manager_filter']['tags'])
                && count(array_intersect($_SESSION['bulk_manager_filter']['tags'], $_POST['del_tags']))) {
                $redirect = true;
            }
        } else {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one tag');
        }
    }

    if ('associate' == $action) {
        associate_images_to_categories(
            $collection,
            array($_POST['associate'])
        );

        $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Information data registered in database');

        // let's refresh the page because we the current set might be modified
        if ('no_album' == $page['prefilter']) {
            $redirect = true;
        } elseif ('no_virtual_album' == $page['prefilter']) {
            $category_info = get_cat_info($_POST['associate']);
            if (empty($category_info['dir'])) {
                $redirect = true;
            }
        }
    } elseif ('move' == $action) {
        move_images_to_categories($collection, array($_POST['move']));

        $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Information data registered in database');

        // let's refresh the page because we the current set might be modified
        if ('no_album' == $page['prefilter']) {
            $redirect = true;
        } elseif ('no_virtual_album' == $page['prefilter']) {
            $category_info = get_cat_info($_POST['move']);
            if (empty($category_info['dir'])) {
                $redirect = true;
            }
        } elseif (isset($_SESSION['bulk_manager_filter']['category'])
            and $_POST['move'] != $_SESSION['bulk_manager_filter']['category']) {
            $redirect = true;
        }
    } elseif ('dissociate' == $action) {
        // physical links must not be broken, so we must first retrieve image_id
        // which create virtual links with the category to "dissociate from".
        $query = 'SELECT id FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE category_id = ' . $conn->db_real_escape_string($_POST['dissociate']);
        $query .= ' AND id ' . $conn->in($collection);
        $query .= ' AND (category_id != storage_category_id OR storage_category_id IS NULL);';
        $dissociables = $conn->query2array($query, null, 'id');

        if (!empty($dissociables)) {
            $query = 'DELETE FROM ' . IMAGE_CATEGORY_TABLE;
            $query .= ' WHERE category_id = ' . $conn->db_real_escape_string($_POST['dissociate']);
            $query .= ' AND image_id ' . $conn->in($dissociables);
            $conn->db_query($query);

            $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Information data registered in database');

            // let's refresh the page because the current set might be modified
            $redirect = true;
        }
    } elseif ('author' == $action) {
        if (isset($_POST['remove_author'])) {
            $_POST['author'] = null;
        }

        $datas = array();
        foreach ($collection as $image_id) {
            $datas[] = array(
                'id' => $image_id,
                'author' => $_POST['author']
            );
        }

        $conn->mass_updates(
            IMAGES_TABLE,
            array('primary' => array('id'), 'update' => array('author')),
            $datas
        );
    } elseif ('title' == $action) {
        if (isset($_POST['remove_title'])) {
            $_POST['title'] = null;
        }

        $datas = array();
        foreach ($collection as $image_id) {
            $datas[] = array(
                'id' => $image_id,
                'name' => $_POST['title']
            );
        }

        $conn->mass_updates(
            IMAGES_TABLE,
            array('primary' => array('id'), 'update' => array('name')),
            $datas
        );
    } elseif ('date_creation' == $action) {
        if (isset($_POST['remove_date_creation']) || empty($_POST['date_creation'])) {
            $date_creation = null;
        } else {
            $date_creation = $_POST['date_creation'];
        }

        $datas = array();
        foreach ($collection as $image_id) {
            $datas[] = array(
                'id' => $image_id,
                'date_creation' => $date_creation
            );
        }

        $conn->mass_updates(
            IMAGES_TABLE,
            array('primary' => array('id'), 'update' => array('date_creation')),
            $datas
        );
    } elseif ('level' == $action) { // privacy_level
        $datas = array();
        foreach ($collection as $image_id) {
            $datas[] = array(
                'id' => $image_id,
                'level' => $_POST['level']
            );
        }

        $conn->mass_updates(
            IMAGES_TABLE,
            array('primary' => array('id'), 'update' => array('level')),
            $datas
        );

        if (isset($_SESSION['bulk_manager_filter']['level'])) {
            if ($_POST['level'] < $_SESSION['bulk_manager_filter']['level']) {
                $redirect = true;
            }
        }
    } elseif ('add_to_caddie' == $action) {
        fill_caddie($collection);
    } elseif ('delete' == $action) {
        if (isset($_POST['confirm_deletion']) and 1 == $_POST['confirm_deletion']) {
            $deleted_count = delete_elements($collection, true);
            if ($deleted_count > 0) {
                $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n_dec(
                    '%d photo was deleted',
                    '%d photos were deleted',
                    $deleted_count
                );

                $redirect_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=' . $_GET['page'];
                $redirect = true;
            } else {
                $page['errors'][] = \Phyxo\Functions\Language::l10n('No photo can be deleted');
            }
        } else {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('You need to confirm deletion');
        }
    } elseif ('metadata' == $action) {
        sync_metadata($collection);
        $page['infos'][] = \Phyxo\Functions\Language::l10n('Metadata synchronized from file');
    } elseif ('delete_derivatives' == $action && !empty($_POST['del_derivatives_type'])) {
        $query = 'SELECT path,representative_ext FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id ' . $conn->in($collection);
        $result = $conn->db_query($query);
        while ($info = $conn->db_fetch_assoc($result)) {
            foreach ($_POST['del_derivatives_type'] as $type) {
                delete_element_derivatives($info, $type);
            }
        }
    } elseif ('generate_derivatives' == $action) {
        if ($_POST['regenerateSuccess'] != '0') {
            $page['infos'][] = \Phyxo\Functions\Language::l10n('%s photos have been regenerated', $_POST['regenerateSuccess']);
        }
        if ($_POST['regenerateError'] != '0') {
            $page['warnings'][] = \Phyxo\Functions\Language::l10n('%s photos can not be regenerated', $_POST['regenerateError']);
        }
    }

    if (!in_array($action, array('remove_from_caddie', 'add_to_caddie', 'delete_derivatives', 'generate_derivatives'))) {
        invalidate_user_cache();
    }

    trigger_notify('element_set_global_action', $action, $collection);

    if ($redirect) {
        redirect($redirect_url);
    }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$base_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php';

$prefilters = array(
    array('ID' => 'caddie', 'NAME' => \Phyxo\Functions\Language::l10n('Caddie')),
    array('ID' => 'favorites', 'NAME' => \Phyxo\Functions\Language::l10n('Your favorites')),
    array('ID' => 'last_import', 'NAME' => \Phyxo\Functions\Language::l10n('Last import')),
    array('ID' => 'no_album', 'NAME' => \Phyxo\Functions\Language::l10n('With no album')),
    array('ID' => 'no_tag', 'NAME' => \Phyxo\Functions\Language::l10n('With no tag')),
    array('ID' => 'duplicates', 'NAME' => \Phyxo\Functions\Language::l10n('Duplicates')),
    array('ID' => 'all_photos', 'NAME' => \Phyxo\Functions\Language::l10n('All'))
);

if ($conf['enable_synchronization']) {
    $prefilters[] = array('ID' => 'no_virtual_album', 'NAME' => \Phyxo\Functions\Language::l10n('With no virtual album'));
}

$prefilters = trigger_change('get_batch_manager_prefilters', $prefilters);
usort($prefilters, 'UC_name_compare');

$template->assign(
    array(
        'prefilters' => $prefilters,
        'filter' => $_SESSION['bulk_manager_filter'],
        'selection' => $collection,
        'all_elements' => $page['cat_elements_id'],
        'START' => $page['start'],
        'U_DISPLAY' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(array('display')),
        'F_ACTION' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(array('cat', 'start', 'tag', 'filter')),
    )
);

// +-----------------------------------------------------------------------+
// |                            caddie options                             |
// +-----------------------------------------------------------------------+
$template->assign('IN_CADDIE', 'caddie' == $page['prefilter']);


// +-----------------------------------------------------------------------+
// |                           global mode form                            |
// +-----------------------------------------------------------------------+

// privacy level
foreach ($conf['available_permission_levels'] as $level) {
    $level_options[$level] = \Phyxo\Functions\Language::l10n(sprintf('Level %d', $level));

    if (0 == $level) {
        $level_options[$level] = \Phyxo\Functions\Language::l10n('Everybody');
    }
}
$template->assign(
    array(
        'filter_level_options' => $level_options,
        'filter_level_options_selected' => isset($_SESSION['bulk_manager_filter']['level'])
            ? $_SESSION['bulk_manager_filter']['level']
            : 0,
    )
);

// tags
$filter_tags = array();

if (!empty($_SESSION['bulk_manager_filter']['tags'])) {
    $query = 'SELECT id,name FROM ' . TAGS_TABLE;
    $query .= ' WHERE id ' . $conn->in($_SESSION['bulk_manager_filter']['tags']);

    $filter_tags = $services['tags']->getTagsList($query);
}

$template->assign('filter_tags', $filter_tags);

// in the filter box, which category to select by default
$selected_category = array();

if (isset($_SESSION['bulk_manager_filter']['category'])) {
    $selected_category = array($_SESSION['bulk_manager_filter']['category']);
} else {
    // we need to know the category in which the last photo was added
    $query = 'SELECT category_id FROM ' . IMAGE_CATEGORY_TABLE;
    $query .= ' ORDER BY image_id DESC LIMIT 1';
    $result = $conn->db_query($query);
    if ($conn->db_num_rows($result) > 0) {
        $row = $conn->db_fetch_assoc($result);
        $selected_category[] = $row['category_id'];
    }
}

$template->assign('filter_category_selected', $selected_category);

// Dissociate from a category : categories listed for dissociation can only
// represent virtual links. We can't create orphans. Links to physical
// categories can't be broken.
if (count($page['cat_elements_id']) > 0) {
    $query = 'SELECT DISTINCT(category_id) AS id FROM ' . IMAGES_TABLE . ' AS i';
    $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON i.id = ic.image_id';
    $query .= ' WHERE ic.image_id ' . $conn->in($page['cat_elements_id']);
    $query .= ' AND (ic.category_id != i.storage_category_id OR i.storage_category_id IS NULL)';
    $template->assign('associated_categories', $conn->query2array($query, 'id', 'id'));
}

if (count($page['cat_elements_id']) > 0) {
    // remove tags
    $template->assign('associated_tags', $services['tags']->getCommonTags($page['cat_elements_id'], -1));
}

// creation date
$template->assign('DATE_CREATION', empty($_POST['date_creation']) ? date('Y-m-d') . ' 00:00:00' : $_POST['date_creation']);

// image level options
$template->assign(
    array(
        'level_options' => get_privacy_level_options(),
        'level_options_selected' => 0,
    )
);

// metadata
$site_reader = new LocalSiteReader('./'); // @TODO : in conf or somewhere else but no direct path here
$used_metadata = implode(', ', $site_reader->get_metadata_attributes());

$template->assign(array('used_metadata' => $used_metadata));

//derivatives
$del_deriv_map = array();
foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $params) {
    $del_deriv_map[$params->type] = \Phyxo\Functions\Language::l10n($params->type);
}
$gen_deriv_map = $del_deriv_map;
$del_deriv_map[IMG_CUSTOM] = \Phyxo\Functions\Language::l10n(IMG_CUSTOM);
$template->assign(
    array(
        'del_derivatives_types' => $del_deriv_map,
        'generate_derivatives_types' => $gen_deriv_map,
    )
);

// +-----------------------------------------------------------------------+
// |                        global mode thumbnails                         |
// +-----------------------------------------------------------------------+

// how many items to display on this page
if (!empty($_GET['display'])) {
    if ('all' == $_GET['display']) {
        $page['nb_images'] = count($page['cat_elements_id']);
    } else {
        $page['nb_images'] = intval($_GET['display']);
    }
} else {
    $page['nb_images'] = 20;
}

$nb_thumbs_page = 0;

if (count($page['cat_elements_id']) > 0) {
    $nav_bar = create_navigation_bar(
        $base_url . \Phyxo\Functions\URL::get_query_string_diff(array('start')),
        count($page['cat_elements_id']),
        $page['start'],
        $page['nb_images']
    );
    $template->assign('navbar', $nav_bar);

    $is_category = false;
    if (isset($_SESSION['bulk_manager_filter']['category']) && !isset($_SESSION['bulk_manager_filter']['category_recursive'])) {
        $is_category = true;
    }

    if (isset($_SESSION['bulk_manager_filter']['prefilter']) && 'duplicates' == $_SESSION['bulk_manager_filter']['prefilter']) {
        $conf['order_by'] = ' ORDER BY file, id';
    }

    $query = 'SELECT id,path,representative_ext,file,filesize,level,name,width,height,rotation FROM ' . IMAGES_TABLE;

    if ($is_category) {
        $category_info = get_cat_info($_SESSION['bulk_manager_filter']['category']);

        $conf['order_by'] = $conf['order_by_inside_category'];
        if (!empty($category_info['image_order'])) {
            $conf['order_by'] = ' ORDER BY ' . $conn->db_real_escape_string($category_info['image_order']);
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

    $thumb_params = \Phyxo\Image\ImageStdParams::get_by_type(IMG_THUMB);
    // template thumbnail initialization
    while ($row = $conn->db_fetch_assoc($result)) {
        $nb_thumbs_page++;
        $src_image = new \Phyxo\Image\SrcImage($row);

        $ttitle = render_element_name($row);
        if ($ttitle != get_name_from_file($row['file'])) { // @TODO: simplify. code difficult to read
            $ttitle .= ' (' . $row['file'] . ')';
        }

        $template->append(
            'thumbnails',
            array_merge(
                $row,
                array(
                    'thumb' => new \Phyxo\Image\DerivativeImage($thumb_params, $src_image),
                    'TITLE' => $ttitle,
                    'FILE_SRC' => \Phyxo\Image\DerivativeImage::url(IMG_LARGE, $src_image),
                    'U_EDIT' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&amp;image_id=' . $row['id'],
                )
            )
        );
    }
    $template->assign('thumb_params', $thumb_params);
}

$template->assign(array(
    'nb_thumbs_page' => $nb_thumbs_page,
    'nb_thumbs_set' => count($page['cat_elements_id']),
    'CACHE_KEYS' => get_admin_client_cache_keys(array('tags', 'categories')),
));

trigger_notify('loc_end_element_set_global');
