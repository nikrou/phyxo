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

if (!defined("PHOTO_BASE_URL")) {
    die("Hacking attempt!");
}

use App\Repository\TagRepository;
use App\Repository\RateRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\UserRepository;

\Phyxo\Functions\Utils::check_input_parameter('image_id', $_GET, false, PATTERN_ID);
\Phyxo\Functions\Utils::check_input_parameter('cat_id', $_GET, false, PATTERN_ID);

// represent
$result = (new CategoryRepository($conn))->findByField('representative_picture_id', $_GET['image_id']);
$represented_albums = $conn->result2array($result, null, 'id');

// +-----------------------------------------------------------------------+
// |                             delete photo                              |
// +-----------------------------------------------------------------------+

if (isset($_GET['delete'])) {
    \Phyxo\Functions\Utils::check_token();

    \Phyxo\Functions\Utils::delete_elements([$_GET['image_id']], true);
    \Phyxo\Functions\Utils::invalidate_user_cache();

    // where to redirect the user now?
    //
    // 1. if a category is available in the URL, use it
    // 2. else use the first reachable linked category
    // 3. redirect to gallery root

    if (!empty($_GET['cat_id'])) {
        \Phyxo\Functions\Utils::redirect(
            \Phyxo\Functions\URL::make_index_url(
                [
                    'category' => \Phyxo\Functions\Category::get_cat_info($_GET['cat_id'])
                ]
            )
        );
    }

    $result = (new ImageCategoryRepository($conn))->findByImageId($_GET['image_id']);
    $authorizeds = array_diff(
        $conn->resulty2array($result, null, 'category_id'),
        explode(',', $services['users']->calculatePermissions($user['id'], $user['status']))
    );

    foreach ($authorizeds as $category_id) {
        \Phyxo\Functions\Utils::redirect(
            \Phyxo\Functions\URL::make_index_url(
                [
                    'category' => \Phyxo\Functions\Category::get_cat_info($category_id)
                ]
            )
        );
    }

    \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::make_index_url());
}

// +-----------------------------------------------------------------------+
// |                          synchronize metadata                         |
// +-----------------------------------------------------------------------+

if (isset($_GET['sync_metadata'])) {
    \Phyxo\Functions\Metadata::sync_metadata([intval($_GET['image_id'])]);
    $page['infos'][] = \Phyxo\Functions\Language::l10n('Metadata synchronized from file');
}

//--------------------------------------------------------- update informations
if (isset($_POST['submit'])) {
    $data = [];
    $data['id'] = $_GET['image_id'];
    $data['name'] = $_POST['name'];
    $data['author'] = $_POST['author'];
    $data['level'] = $_POST['level'];

    // @TODO: remove arobases
    if ($conf['allow_html_descriptions']) {
        $data['comment'] = @$_POST['description'];
    } else {
        $data['comment'] = strip_tags(@$_POST['description']);
    }

    if (!empty($_POST['date_creation'])) {
        $data['date_creation'] = $_POST['date_creation'];
    } else {
        $data['date_creation'] = null;
    }

    $data = \Phyxo\Functions\Plugin::trigger_change('picture_modify_before_update', $data);

    (new ImageRepository($conn))->updateImage($data, $data['id']);

    // time to deal with tags
    $tag_ids = [];
    if (!empty($_POST['tags'])) {
        $tag_ids = $services['tags']->getTagsIds($_POST['tags']);
    }
    $services['tags']->setTags($tag_ids, $_GET['image_id']);

    // association to albums
    if (!isset($_POST['associate'])) {
        $_POST['associate'] = [];
    }
    \Phyxo\Functions\Utils::check_input_parameter('associate', $_POST, true, PATTERN_ID);
    \Phyxo\Functions\Category::move_images_to_categories([$_GET['image_id']], $_POST['associate']);

    \Phyxo\Functions\Utils::invalidate_user_cache();

    // thumbnail for albums
    if (!isset($_POST['represent'])) {
        $_POST['represent'] = [];
    }
    \Phyxo\Functions\Utils::check_input_parameter('represent', $_POST, true, PATTERN_ID);

    $no_longer_thumbnail_for = array_diff($represented_albums, $_POST['represent']);
    if (count($no_longer_thumbnail_for) > 0) {
        \Phyxo\Functions\Utils::set_random_representant($no_longer_thumbnail_for);
    }

    $new_thumbnail_for = array_diff($_POST['represent'], $represented_albums);
    if (count($new_thumbnail_for) > 0) {
        (new CategoryRepository($conn))->updateCategories(['representative_picture_id' => $_GET['image_id']], $new_thumbnail_for);
    }

    $represented_albums = $_POST['represent'];
    $page['infos'][] = \Phyxo\Functions\Language::l10n('Photo informations updated');
}

// tags
$tags = $conn->result2array((new TagRepository($conn))->getTagsByImage($_GET['image_id'], $validated = true));
$tag_selection = $services['tags']->prepareTagsListForUI($tags);

// retrieving direct information about picture
$result = (new ImageRepository($conn))->findById($_GET['image_id']);
$row = $conn->db_fetch_assoc($result);

$storage_category_id = null;
if (!empty($row['storage_category_id'])) {
    $storage_category_id = $row['storage_category_id'];
}

$image_file = $row['file'];

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$admin_url_start = PHOTO_BASE_URL . '&amp;section=properties';
$admin_url_start .= isset($_GET['cat_id']) ? '&amp;cat_id=' . $_GET['cat_id'] : '';

$src_image = new \Phyxo\Image\SrcImage($row);

$template->assign(
    [
        'tag_selection' => $tag_selection,
        'U_SYNC' => $admin_url_start . '&amp;sync_metadata=1',
        'U_DELETE' => $admin_url_start . '&amp;delete=1&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token(),
        'PATH' => $row['path'],
        'TN_SRC' => \Phyxo\Image\DerivativeImage::url(IMG_THUMB, $src_image),
        'FILE_SRC' => \Phyxo\Image\DerivativeImage::url(IMG_LARGE, $src_image),
        'NAME' => isset($_POST['name']) ? stripslashes($_POST['name']) : @$row['name'],
        'TITLE' => \Phyxo\Functions\Utils::render_element_name($row),
        'DIMENSIONS' => @$row['width'] . ' * ' . @$row['height'],
        'FILESIZE' => @$row['filesize'] . ' KB',
        'REGISTRATION_DATE' => \Phyxo\Functions\DateTime::format_date($row['date_available']),
        'AUTHOR' => htmlspecialchars(isset($_POST['author']) ? stripslashes($_POST['author']) : @$row['author']),
        'DATE_CREATION' => $row['date_creation'],
        'DESCRIPTION' => htmlspecialchars(isset($_POST['description']) ? stripslashes($_POST['description']) : @$row['comment']),
        'F_ACTION' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php' . \Phyxo\Functions\URL::get_query_string_diff(['sync_metadata'])
    ]
);

$added_by = 'N/A';
$result = (new UserRepository($conn))->findById($row['added_by']);
while ($user_row = $conn->db_fetch_assoc($result)) {
    $row['added_by'] = $user_row['username'];
}

$intro_vars = [
    'file' => \Phyxo\Functions\Language::l10n('Original file : %s', $row['file']),
    'add_date' => \Phyxo\Functions\Language::l10n(
        'Posted %s on %s',
        \Phyxo\Functions\DateTime::time_since($row['date_available'], 'year'),
        \Phyxo\Functions\DateTime::format_date($row['date_available'], ['day', 'month', 'year'])
    ),
    'added_by' => \Phyxo\Functions\Language::l10n('Added by %s', $row['added_by']),
    'size' => $row['width'] . '&times;' . $row['height'] . ' pixels, ' . sprintf('%.2f', $row['filesize'] / 1024) . 'MB',
    'stats' => \Phyxo\Functions\Language::l10n('Visited %d times', $row['hit']),
    'id' => \Phyxo\Functions\Language::l10n('Numeric identifier : %d', $row['id']),
];

if ($conf['rate'] and !empty($row['rating_score'])) {
    $nb_rates = (new RateRepository($conn))->count($_GET['image_id']);
    $intro_vars['stats'] .= ', ' . sprintf(\Phyxo\Functions\Language::l10n('Rated %d times, score : %.2f'), $nb_rates, $row['rating_score']);
}

$template->assign('INTRO', $intro_vars);

if (in_array(\Phyxo\Functions\Utils::get_extension($row['path']), $conf['picture_ext'])) {
    $template->assign('U_COI', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=picture_coi&amp;image_id=' . $_GET['image_id']);
}

// image level options
$selected_level = isset($_POST['level']) ? $_POST['level'] : $row['level'];
$template->assign(
    [
        'level_options' => \Phyxo\Functions\Utils::get_privacy_level_options(),
        'level_options_selected' => [$selected_level]
    ]
);

// associate to albums
$result = (new CategoryRepository($conn))->findCategoriesForImage($_GET['image_id']);
$associated_albums = $conn->result2array($result, 'id');
foreach ($associated_albums as $album) {
    $name = \Phyxo\Functions\Category::get_cat_display_name_cache($album['uppercats'], \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=album-');

    if ($album['category_id'] == $storage_category_id) {
        $template->assign('STORAGE_CATEGORY', $name);
    } else {
        $template->append('related_categories', $name);
    }
}

// jump to link
//
// 1. find all linked categories that are reachable for the current user.
// 2. if a category is available in the URL, use it if reachable
// 3. if URL category not available or reachable, use the first reachable
//    linked category
// 4. if no category reachable, no jumpto link

$result = (new ImageCategoryRepository($conn))->findByImageId($_GET['image_id']);
$authorizeds = array_diff(
    $conn->result2array($result, null, 'category_id'),
    explode(',', $services['users']->calculatePermissions($user['id'], $user['status']))
);

if (isset($_GET['cat_id']) && in_array($_GET['cat_id'], $authorizeds)) {
    $url_img = \Phyxo\Functions\URL::make_picture_url(
        [
            'image_id' => $_GET['image_id'],
            'image_file' => $image_file,
            'category' => $cache['cat_names'][$_GET['cat_id']],
        ]
    );
} else {
    foreach ($authorizeds as $category) {
        $url_img = \Phyxo\Functions\URL::make_picture_url(
            [
                'image_id' => $_GET['image_id'],
                'image_file' => $image_file,
                'category' => $cache['cat_names'][$category],
            ]
        );
        break;
    }
}

if (isset($url_img)) {
    $template->assign('U_JUMPTO', $url_img);
}

$template->assign([
    'associated_albums' => $associated_albums,
    'represented_albums' => $represented_albums,
    'STORAGE_ALBUM' => $storage_category_id,
    'CACHE_KEYS' => \Phyxo\Functions\Utils::get_admin_client_cache_keys(['tags', 'categories']),
]);

\Phyxo\Functions\Plugin::trigger_notify('loc_end_picture_modify');
