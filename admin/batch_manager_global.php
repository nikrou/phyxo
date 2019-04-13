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
use App\Repository\TagRepository;
use App\Repository\ImageTagRepository;
use App\Repository\CaddieRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_element_set_global');

\Phyxo\Functions\Utils::check_input_parameter('del_tags', $_POST, true, PATTERN_ID);
\Phyxo\Functions\Utils::check_input_parameter('associate', $_POST, false, PATTERN_ID);
\Phyxo\Functions\Utils::check_input_parameter('move', $_POST, false, PATTERN_ID);
\Phyxo\Functions\Utils::check_input_parameter('dissociate', $_POST, false, PATTERN_ID);

// +-----------------------------------------------------------------------+
// |                            current selection                          |
// +-----------------------------------------------------------------------+

$collection = [];
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
        (new CaddieRepository($conn))->deleteElements($collection, $user['id']);

        // remove from caddie action available only in caddie so reload content
        $redirect = true;
    } elseif ('add_tags' == $action) {
        if (empty($_POST['add_tags'])) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one tag');
        } else {
            $tag_ids = $tagMapper->getTagsIds($_POST['add_tags']);
            $tagMapper->addTags($tag_ids, $collection);

            if ('no_tag' == $page['prefilter']) {
                $redirect = true;
            }
        }
    } elseif ('del_tags' == $action) {
        if (isset($_POST['del_tags']) and count($_POST['del_tags']) > 0) {
            (new ImageTagRepository($conn))->deleteByImagesAndTags($collection, $_POST['del_tags']);

            if (isset($_SESSION['bulk_manager_filter']['tags'])
                && count(array_intersect($_SESSION['bulk_manager_filter']['tags'], $_POST['del_tags']))) {
                $redirect = true;
            }
        } else {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one tag');
        }
    }

    if ('associate' == $action) {
        \Phyxo\Functions\Category::associate_images_to_categories(
            $collection,
            [$_POST['associate']]
        );

        $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Information data registered in database');

        // let's refresh the page because we the current set might be modified
        if ('no_album' == $page['prefilter']) {
            $redirect = true;
        } elseif ('no_virtual_album' == $page['prefilter']) {
            $category_info = \Phyxo\Functions\Category::get_cat_info($_POST['associate']);
            if (empty($category_info['dir'])) {
                $redirect = true;
            }
        }
    } elseif ('move' == $action) {
        \Phyxo\Functions\Category::move_images_to_categories($collection, [$_POST['move']]);

        $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Information data registered in database');

        // let's refresh the page because we the current set might be modified
        if ('no_album' == $page['prefilter']) {
            $redirect = true;
        } elseif ('no_virtual_album' == $page['prefilter']) {
            $category_info = \Phyxo\Functions\Category::get_cat_info($_POST['move']);
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
        $result = (new ImageRepository($conn))->findImagesInVirtualCategory($collection, $_POST['dissociate']);
        $dissociables = $conn->result2array($result, null, 'id');

        if (!empty($dissociables)) {
            (new ImageCategoryRepository($conn))->deleteByCategory([$_POST['dissociate']], $dissociables);
            $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Information data registered in database');

            // let's refresh the page because the current set might be modified
            $redirect = true;
        }
    } elseif ('author' == $action) {
        if (isset($_POST['remove_author'])) {
            $_POST['author'] = null;
        }

        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'author' => $_POST['author']
            ];
        }

        (new ImageRepository($conn))->massUpdates(['primary' => ['id'], 'update' => ['author']], $datas);
    } elseif ('title' == $action) {
        if (isset($_POST['remove_title'])) {
            $_POST['title'] = null;
        }

        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'name' => $_POST['title']
            ];
        }

        (new ImageRepository($conn))->massUpdates(['primary' => ['id'], 'update' => ['name']], $datas);
    } elseif ('date_creation' == $action) {
        if (isset($_POST['remove_date_creation']) || empty($_POST['date_creation'])) {
            $date_creation = null;
        } else {
            $date_creation = $_POST['date_creation'];
        }

        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'date_creation' => $date_creation
            ];
        }

        (new ImageRepository($conn))->massUpdates(['primary' => ['id'], 'update' => ['date_creation']], $datas);
    } elseif ('level' == $action) { // privacy_level
        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'level' => $_POST['level']
            ];
        }

        (new ImageRepository($conn))->massUpdates(['primary' => ['id'], 'update' => ['level']], $datas);

        if (isset($_SESSION['bulk_manager_filter']['level'])) {
            if ($_POST['level'] < $_SESSION['bulk_manager_filter']['level']) {
                $redirect = true;
            }
        }
    } elseif ('add_to_caddie' == $action) {
        (new CaddieRepository($conn))->fillCaddie($user['id'], $collection);
    } elseif ('delete' == $action) {
        if (isset($_POST['confirm_deletion']) and 1 == $_POST['confirm_deletion']) {
            $deleted_count = \Phyxo\Functions\Utils::delete_elements($collection, true);
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
        $tagMapper->sync_metadata($collection);
        $page['infos'][] = \Phyxo\Functions\Language::l10n('Metadata synchronized from file');
    } elseif ('delete_derivatives' == $action && !empty($_POST['del_derivatives_type'])) {
        $result = (new ImageRepository($conn))->findByIds($collection);
        while ($info = $conn->db_fetch_assoc($result)) {
            foreach ($_POST['del_derivatives_type'] as $type) {
                \Phyxo\Functions\Utils::delete_element_derivatives($info, $type);
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

    if (!in_array($action, ['remove_from_caddie', 'add_to_caddie', 'delete_derivatives', 'generate_derivatives'])) {
        \Phyxo\Functions\Utils::invalidate_user_cache();
    }

    \Phyxo\Functions\Plugin::trigger_notify('element_set_global_action', $action, $collection);

    if ($redirect) {
        \Phyxo\Functions\Utils::redirect($redirect_url);
    }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$base_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php';

$prefilters = [
    ['id' => 'caddie', 'name' => \Phyxo\Functions\Language::l10n('Caddie')],
    ['id' => 'favorites', 'name' => \Phyxo\Functions\Language::l10n('Your favorites')],
    ['id' => 'last_import', 'name' => \Phyxo\Functions\Language::l10n('Last import')],
    ['id' => 'no_album', 'name' => \Phyxo\Functions\Language::l10n('With no album')],
    ['id' => 'no_tag', 'name' => \Phyxo\Functions\Language::l10n('With no tag')],
    ['id' => 'duplicates', 'name' => \Phyxo\Functions\Language::l10n('Duplicates')],
    ['id' => 'all_photos', 'name' => \Phyxo\Functions\Language::l10n('All')]
];

if ($conf['enable_synchronization']) {
    $prefilters[] = ['id' => 'no_virtual_album', 'name' => \Phyxo\Functions\Language::l10n('With no virtual album')];
}

$prefilters = \Phyxo\Functions\Plugin::trigger_change('get_batch_manager_prefilters', $prefilters);
usort($prefilters, '\Phyxo\Functions\Utils::name_compare');

$template->assign(
    [
        'prefilters' => $prefilters,
        'filter' => $_SESSION['bulk_manager_filter'],
        'selection' => $collection,
        'all_elements' => $page['cat_elements_id'],
        'START' => $page['start'],
        'U_DISPLAY' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(['display']),
        'F_ACTION' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(['cat', 'start', 'tag', 'filter']),
    ]
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
    [
        'filter_level_options' => $level_options,
        'filter_level_options_selected' => isset($_SESSION['bulk_manager_filter']['level'])
            ? $_SESSION['bulk_manager_filter']['level']
            : 0,
    ]
);

// tags
$filter_tags = [];

if (!empty($_SESSION['bulk_manager_filter']['tags'])) {
    $tags = $conn->result2array((new TagRepository($conn))->findTags($_SESSION['bulk_manager_filter']['tags']));
    $filter_tags = $tagMapper->prepareTagsListForUI($tags);
}

$template->assign('filter_tags', $filter_tags);

// in the filter box, which category to select by default
$selected_category = [];

if (isset($_SESSION['bulk_manager_filter']['category'])) {
    $selected_category[] = $_SESSION['bulk_manager_filter']['category'];
} else {
    // we need to know the category in which the last photo was added
    $selected_category[] = (new ImageCategoryRepository($conn))->getCategoryWithLastPhotoAdded();
}

$template->assign('filter_category_selected', $selected_category);

// Dissociate from a category : categories listed for dissociation can only
// represent virtual links. We can't create orphans. Links to physical
// categories can't be broken.
if (count($page['cat_elements_id']) > 0) {
    $result = (new ImageRepository($conn))->findVirtualCategoriesWithImages($page['cat_elements_id']);
    $template->assign('associated_categories', $conn->result2array($result, 'id', 'id'));
}

if (count($page['cat_elements_id']) > 0) {
    // remove tags
    $template->assign('associated_tags', $tagMapper->getCommonTags($user, $page['cat_elements_id'], -1));
}

// creation date
$template->assign('DATE_CREATION', empty($_POST['date_creation']) ? date('Y-m-d') . ' 00:00:00' : $_POST['date_creation']);

// image level options
$template->assign(
    [
        'level_options' => \Phyxo\Functions\Utils::get_privacy_level_options(),
        'level_options_selected' => 0,
    ]
);

// metadata
$site_reader = new LocalSiteReader('./'); // @TODO : in conf or somewhere else but no direct path here
$used_metadata = implode(', ', $site_reader->get_metadata_attributes());

$template->assign(['used_metadata' => $used_metadata]);

//derivatives
$del_deriv_map = [];
foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $params) {
    $del_deriv_map[$params->type] = \Phyxo\Functions\Language::l10n($params->type);
}
$gen_deriv_map = $del_deriv_map;
$del_deriv_map[IMG_CUSTOM] = \Phyxo\Functions\Language::l10n(IMG_CUSTOM);
$template->assign(
    [
        'del_derivatives_types' => $del_deriv_map,
        'generate_derivatives_types' => $gen_deriv_map,
    ]
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
    $nav_bar = \Phyxo\Functions\Utils::create_navigation_bar(
        $base_url . \Phyxo\Functions\URL::get_query_string_diff(['start']),
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

    if ($is_category) {
        $category_info = \Phyxo\Functions\Category::get_cat_info($_SESSION['bulk_manager_filter']['category']);

        $conf['order_by'] = $conf['order_by_inside_category'];
        if (!empty($category_info['image_order'])) {
            $conf['order_by'] = ' ORDER BY ' . $conn->db_real_escape_string($category_info['image_order']);
        }
    }
    $result = (new ImageRepository($conn))->findByImageIdsAndCategoryId(
        $page['cat_elements_id'],
        $_SESSION['bulk_manager_filter']['category'] ?? null,
        $conf['order_by'] ?? '  ',
        $page['nb_images'],
        $page['start']
    );
    $thumb_params = \Phyxo\Image\ImageStdParams::get_by_type(IMG_THUMB);
    // template thumbnail initialization
    while ($row = $conn->db_fetch_assoc($result)) {
        $nb_thumbs_page++;
        $src_image = new \Phyxo\Image\SrcImage($row);

        $ttitle = \Phyxo\Functions\Utils::render_element_name($row);
        if ($ttitle != \Phyxo\Functions\Utils::get_name_from_file($row['file'])) { // @TODO: simplify. code difficult to read
            $ttitle .= ' (' . $row['file'] . ')';
        }

        $template->append(
            'thumbnails',
            array_merge(
                $row,
                [
                    'thumb' => new \Phyxo\Image\DerivativeImage($thumb_params, $src_image),
                    'TITLE' => $ttitle,
                    'FILE_SRC' => \Phyxo\Image\DerivativeImage::url(IMG_LARGE, $src_image),
                    'U_EDIT' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&amp;image_id=' . $row['id'],
                ]
            )
        );
    }
    $template->assign('thumb_params', $thumb_params);
}

$template->assign([
    'nb_thumbs_page' => $nb_thumbs_page,
    'nb_thumbs_set' => count($page['cat_elements_id']),
    'CACHE_KEYS' => \Phyxo\Functions\Utils::get_admin_client_cache_keys(['tags', 'categories']),
]);

\Phyxo\Functions\Plugin::trigger_notify('loc_end_element_set_global');
