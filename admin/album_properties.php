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

use App\Repository\CategoryRepository;
use App\Repository\SiteRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;

if (!defined('ALBUM_BASE_URL')) {
    die('Hacking attempt!');
}

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_cat_modify');

//---------------------------------------------------------------- verification
if (!isset($_GET['cat_id']) || !is_numeric($_GET['cat_id'])) {
    trigger_error('missing cat_id param', E_USER_ERROR);
}

//--------------------------------------------------------- form criteria check
if (isset($_POST['submit'])) {
    $data = ['id' => $_GET['cat_id'], 'name' => '', 'comment' => ''];

    if (!empty($_POST['name'])) {
        $data['name'] = $_POST['name'];
    }

    if (!empty($_POST['comment'])) {
        $data['comment'] = $conf['allow_html_descriptions'] ? $_POST['comment'] : strip_tags($_POST['comment']);
    }

    if ($conf['activate_comments']) {
        $data['commentable'] = isset($_POST['commentable']) ? $conn->get_boolean($_POST['commentable']) : false;
    }

    (new CategoryRepository($conn))->updateCategory(
        $data,
        $data['id']
    );
    if (!empty($_POST['apply_commentable_on_sub'])) {
        $subcats = (new CategoryRepository($conn))->getSubcatIds(['id' => $data['id']]);
        (new CategoryRepository($conn))->updateCategories(
            ['commentable' => $data['commentable']],
            $subcats
        );
    }

    // retrieve cat infos before continuing (following updates are expensive)
    $cat_info = $categoryMapper->getCatInfo($_GET['cat_id']);

    if (!empty($_POST['visible'])) {
        if ($_POST['visible'] === 'true_sub') {
            $categoryMapper->setCatVisible([$_GET['cat_id']], true, true);
        } elseif ($cat_info['visible'] != $conn->get_boolean($_POST['visible'])) {
            $categoryMapper->setCatVisible([$_GET['cat_id']], $conn->get_boolean($_POST['visible']));
        }
    }

    // in case the use moves his album to the gallery root, we force
    // $_POST['parent'] from 0 to null to be compared with
    // $cat_info['id_uppercat']
    if (empty($_POST['parent'])) {
        $_POST['parent'] = null;
    }

    // only move virtual albums
    if (empty($cat_info['dir']) && $cat_info['id_uppercat'] != $_POST['parent']) {
        $categoryMapper->moveCategories([$_GET['cat_id']], $_POST['parent']);
    }

    $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Album updated successfully');
    $redirect = true;
} elseif (isset($_POST['set_random_representant'])) {
    $categoryMapper->setRandomRepresentant([$_GET['cat_id']]);
    $redirect = true;
} elseif (isset($_POST['delete_representant'])) {
    (new CategoryRepository($conn))->updateCategory(['representative_picture_id' => null], $_GET['cat_id']);
    $redirect = true;
}

if (isset($redirect)) {
    \Phyxo\Functions\Utils::redirect(ALBUM_BASE_URL . '&amp;section=properties&amp;cat_id=' . $category['id']);
}

// nullable fields
foreach (['comment', 'dir', 'site_id', 'id_uppercat'] as $nullable) {
    if (!isset($category[$nullable])) {
        $category[$nullable] = '';
    }
}

$category['is_virtual'] = empty($category['dir']) ? true : false;
$result = (new ImageCategoryRepository($conn))->findDistinctCategoryId($_GET['cat_id']);
$category['has_images'] = $conn->db_num_rows($result) > 0 ? true : false;

// Navigation path
$navigation = $categoryMapper->getCatDisplayNameCache(
    $category['uppercats'],
    ALBUM_BASE_URL . '&amp;section=properties&amp;cat_id='
);

$form_action = ALBUM_BASE_URL . '&amp;section=properties&amp;cat_id=' . $category['id'];

//----------------------------------------------------- template initialization

$base_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=';
$cat_list_url = $base_url . 'albums';

$self_url = $cat_list_url;
if (!empty($category['id_uppercat'])) {
    $self_url .= '&amp;parent_id=' . $category['id_uppercat'];
}

$template->assign(
    [
        'CATEGORIES_NAV' => $navigation,
        'CAT_ID' => $category['id'],
        'CAT_NAME' => @htmlspecialchars($category['name']), // @TODO: remove arobase
        'CAT_COMMENT' => @htmlspecialchars($category['comment']), // @TODO: remove arobase
        'CAT_VISIBLE' => $conn->boolean_to_string($category['visible']),
        'U_JUMPTO' => \Phyxo\Functions\URL::make_index_url(['category' => $category]),
        'U_ADD_PHOTOS_ALBUM' => $base_url . 'photos_add&amp;album=' . $category['id'],
        'U_CHILDREN' => $cat_list_url . '&amp;parent_id=' . $category['id'],
        //'U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=cat_modify',
        'F_ACTION' => $form_action,
    ]
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

    $result = (new ImageRepository($conn))->getImagesInfosInCategory($category['id']);
    list($image_count, $min_date, $max_date) = $conn->db_fetch_row($result);

    if ($min_date == $max_date) {
        $intro = \Phyxo\Functions\Language::l10n(
            'This album contains %d photos, added on %s.',
            $image_count,
            \Phyxo\Functions\DateTime::format_date($min_date)
        );
    } else {
        $intro = \Phyxo\Functions\Language::l10n(
            'This album contains %d photos, added between %s and %s.',
            $image_count,
            \Phyxo\Functions\DateTime::format_date($min_date),
            \Phyxo\Functions\DateTime::format_date($max_date)
        );
    }
} else {
    $intro = \Phyxo\Functions\Language::l10n('This album contains no photo.');
}

$intro .= '<br>' . \Phyxo\Functions\Language::l10n('Numeric identifier : %d', $category['id']);

$template->assign([
    'INTRO' => $intro,
    'U_MANAGE_RANKS' => $base_url . 'element_set_ranks&amp;cat_id=' . $category['id'],
    'CACHE_KEYS' => \Phyxo\Functions\Utils::get_admin_client_cache_keys(['categories']),
]);

if ($category['is_virtual']) {
    $template->assign(
        [
            'U_DELETE' => $self_url . '&amp;delete=' . $category['id'] . '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token(),
        ]
    );
} else {
    $result = (new SiteRepository($conn))->getSiteUrl($_GET['cat_id']);
    $row = $conn->db_fetch_assoc($result);

    $uppercats = '';
    $local_dir = '';
    if (isset($page['plain_structure'][$category_id]['uppercats'])) {
        $uppercats = $page['plain_structure'][$category_id]['uppercats'];
    } else {
        $result = (new CategoryRepository($conn))->findById($_GET['cat_id']);
        $row = $conn->db_fetch_assoc($result);
        $uppercats = $row['uppercats'];
    }

    $upper_array = explode(',', $uppercats);

    $database_dirs = [];
    $result = (new CategoryRepository($conn))->findByIds($uppercats);
    while ($row = $conn->db_fetch_assoc($result)) {
        $database_dirs[$row['id']] = $row['dir'];
    }
    foreach ($upper_array as $id) {
        $local_dir .= $database_dirs[$id] . '/';
    }

    $category['cat_full_dir'] = $row['galleries_url'] . $local_dir;

    $template->assign(
        [
            'CAT_FULL_DIR' => preg_replace('/\/$/', '', $category['cat_full_dir'])
        ]
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
    $tpl_representant = [];

    // picture to display : the identified representant or the generic random
    // representant ?
    if (!empty($category['representative_picture_id'])) {
        $result = (new ImageRepository($conn))->findById($app_user, [], $category['representative_picture_id']);
        $row = $conn->db_fetch_assoc($result);
        $src = \Phyxo\Image\DerivativeImage::thumb_url($row, $conf['picture_ext']);
        $url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&amp;image_id=' . $category['representative_picture_id'];

        $tpl_representant['picture'] =
            [
                'SRC' => $src,
                'URL' => $url
            ];
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
    $template->assign('parent_category', empty($category['id_uppercat']) ? [] : [$category['id_uppercat']]);
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_cat_modify');
