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
    die("Hacking attempt!");
}

use App\Repository\ImageRepository;
use App\Repository\GroupRepository;
use App\Repository\GroupAccessRepository;

// +-----------------------------------------------------------------------+
// |                       variable initialization                         |
// +-----------------------------------------------------------------------+

$page['cat'] = $category['id'];

// +-----------------------------------------------------------------------+
// |                           form submission                             |
// +-----------------------------------------------------------------------+

// info by email to an access granted group of category informations
if (isset($_POST['submitEmail']) and !empty($_POST['group'])) {
    \Phyxo\Functions\URL::set_make_full_url();

    /* @TODO: if $category['representative_picture_id']
       is empty find child representative_picture_id */
    if (!empty($category['representative_picture_id'])) {
        $result = (new ImageRepository())->findById($category['representative_picture_id']);
        if ($conn->db_num_rows($result) > 0) {
            $element = $conn->db_fetch_assoc($result);

            $img_url = '<a href="' .
                \Phyxo\Functions\URL::make_picture_url([
                'image_id' => $element['id'],
                'image_file' => $element['file'],
                'category' => $category
            ]) . '" class="thumblnk"><img src="' . \Phyxo\Image\DerivativeImage::url(IMG_THUMB, $element) . '"></a>';
        }
    }

    if (!isset($img_url)) {
        $img_url = '';
    }

    \Phyxo\Functions\Mail::mail_group(
        $_POST['group'],
        [
            'subject' => \Phyxo\Functions\Language::l10n(
                '[%s] Visit album %s',
                $conf['gallery_title'],
                \Phyxo\Functions\Plugin::trigger_change('render_category_name', $category['name'], 'admin_cat_list')
            ),
            // @TODO : change this language variable to 'Visit album %s'
            // @TODO : 'language_selected' => ....
        ],
        [
            'filename' => 'cat_group_info',
            'assign' => [
                'IMG_URL' => $img_url,
                'CAT_NAME' => \Phyxo\Functions\Plugin::trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
                'LINK' => \Phyxo\Functions\URL::make_index_url([
                    'category' => [
                        'id' => $category['id'],
                        'name' => \Phyxo\Functions\Plugin::trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
                        'permalink' => $category['permalink']
                    ]
                ]),
                'CPL_CONTENT' => empty($_POST['mail_content']) ? '' : stripslashes($_POST['mail_content']),
            ]
        ]
    );

    \Phyxo\Functions\URL::unset_make_full_url();

    $result = (new GroupRepository($conn))->findById($_POST['group']);
    $row = $conn->db_fetch_assoc($result);

    $page['infos'][] = \Phyxo\Functions\Language::l10n('An information email was sent to group "%s"', $row['name']);
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->assign(
    [
        'CATEGORIES_NAV' => \Phyxo\Functions\Category::get_cat_display_name_from_id(
            $page['cat'],
            './index.php?page=album&amp;cat_id='
        ),
        'F_ACTION' => ALBUM_BASE_URL . '&amp;section=notification',
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    ]
);

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

$result = (new GroupRepository($conn))->findAll();
$all_group_ids = $conn->result2array($result, null, 'id');

if (count($all_group_ids) == 0) {
    $template->assign('no_group_in_gallery', true);
} else {
    if ('private' == $category['status']) {
        $result = (new GroupAccessRepository($conn))->findByCatId($category['id']);
        $group_ids = $conn->result2array($result, null, 'group_id');

        if (count($group_ids) == 0) {
            $template->assign('permission_url', ALBUM_BASE_URL . '&amp;section=permissions');
        }
    } else {
        $group_ids = $all_group_ids;
    }

    if (count($group_ids) > 0) {
        $result = (new GroupRepository($conn))->findByIds($group_ids, 'ORDER BY name ASC');
        $template->assign(
            'group_mail_options',
            $conn->result2array($result, 'id', 'name')
        );
    }
}
