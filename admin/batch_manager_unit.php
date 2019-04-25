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

use App\Repository\TagRepository;
use App\Repository\ImageRepository;
use Phyxo\Image\ImageStdParams;
/**
 * Management of elements set. Elements can belong to a category or to the
 * user caddie.
 *
 */

if (!defined('BATCH_MANAGER_BASE_URL')) {
    die('Hacking attempt!');
}

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_element_set_unit');

// +-----------------------------------------------------------------------+
// |                        unit mode form submission                      |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit'])) {
    $collection = explode(',', $_POST['element_ids']);

    $datas = [];

    $result = (new ImageRepository($conn))->findByIds($collection);
    while ($row = $conn->db_fetch_assoc($result)) {
        $data = [];

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
        $tag_ids = [];
        if (!empty($_POST['tags-' . $row['id']])) {
            $tag_ids = $tagMapper->getTagIds($_POST['tags-' . $row['id']]);
        }
        $tagMapper->setTags($tag_ids, $row['id']);
    }

    (new ImageRepository($conn))->massUpdates(
        [
            'primary' => ['id'],
            'update' => ['name', 'author', 'level', 'comment', 'date_creation']
        ],
        $datas
    );

    $page['infos'][] = \Phyxo\Functions\Language::l10n('Photo informations updated');
    \Phyxo\Functions\Utils::invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$base_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php';

$template->assign(
    [
        'U_ELEMENTS_PAGE' => $base_url . \Phyxo\Functions\URL::get_query_string_diff(['display', 'start']),
        'F_ACTION' => $base_url . \Phyxo\Functions\URL::get_query_string_diff([]),
        'level_options' => \Phyxo\Functions\Utils::get_privacy_level_options(),
    ]
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
    $nav_bar = \Phyxo\Functions\Utils::create_navigation_bar(
        $base_url . \Phyxo\Functions\URL::get_query_string_diff(['start']),
        count($page['cat_elements_id']),
        $page['start'],
        $page['nb_images']
    );
    $template->assign(['navbar' => $nav_bar]);

    $element_ids = [];

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
            $conf['order_by'] = ' ORDER BY ' . $category_info['image_order'];
        }
    }
    $result = (new ImageRepository($conn))->findByImageIdsAndCategoryId(
        $page['cat_elements_id'],
        $_SESSION['bulk_manager_filter']['category'] ?? null,
        $conf['order_by'] ?? '',
        $page['nb_images'],
        $page['start']
    );
    while ($row = $conn->db_fetch_assoc($result)) {
        $element_ids[] = $row['id'];

        $src_image = new \Phyxo\Image\SrcImage($row);

        $tags = $conn->result2array((new TagRepository($conn))->getTagsByImage($row['id']));
        $tag_selection = $tagMapper->prepareTagsListForUI($tags);

        $legend = \Phyxo\Functions\Utils::render_element_name($row);
        if ($legend != \Phyxo\Functions\Utils::get_name_from_file($row['file'])) {
            $legend .= ' (' . $row['file'] . ')';
        }

        $template->append(
            'elements',
            array_merge(
                $row,
                [
                    'ID' => $row['id'],
                    'TN_SRC' => \Phyxo\Image\DerivativeImage::url(ImageStdParams::IMG_THUMB, $src_image),
                    'FILE_SRC' => \Phyxo\Image\DerivativeImage::url(ImageStdParams::IMG_LARGE, $src_image),
                    'LEGEND' => $legend,
                    'U_EDIT' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&image_id=' . $row['id'],
                    'NAME' => htmlspecialchars(@$row['name']), // @TODO: remove arobase
                    'AUTHOR' => htmlspecialchars(@$row['author']), // @TODO: remove arobase
                    'LEVEL' => !empty($row['level']) ? $row['level'] : '0',
                    'DESCRIPTION' => htmlspecialchars(@$row['comment']), // @TODO: remove arobase
                    'DATE_CREATION' => $row['date_creation'],
                    'TAGS' => $tag_selection,
                ]
            )
        );
    }

    $template->assign([
        'ELEMENT_IDS' => implode(',', $element_ids),
        'CACHE_KEYS' => \Phyxo\Functions\Utils::get_admin_client_cache_keys(['tags']),
    ]);
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_element_set_unit');
