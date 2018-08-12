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

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

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
        $query = 'SELECT id, file, path, representative_ext FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id = ' . $category['representative_picture_id'];

        $result = $conn->db_query($query);
        if ($conn->db_num_rows($result) > 0) {
            $element = $conn->db_fetch_assoc($result);

            $img_url = '<a href="' .
                \Phyxo\Functions\URL::make_picture_url(array(
                'image_id' => $element['id'],
                'image_file' => $element['file'],
                'category' => $category
            )) . '" class="thumblnk"><img src="' . \Phyxo\Image\DerivativeImage::url(IMG_THUMB, $element) . '"></a>';
        }
    }

    if (!isset($img_url)) {
        $img_url = '';
    }

    \Phyxo\Functions\Mail::mail_group(
        $_POST['group'],
        array(
            'subject' => \Phyxo\Functions\Language::l10n(
                '[%s] Visit album %s',
                $conf['gallery_title'],
                \Phyxo\Functions\Plugin::trigger_change('render_category_name', $category['name'], 'admin_cat_list')
            ),
            // @TODO : change this language variable to 'Visit album %s'
            // @TODO : 'language_selected' => ....
        ),
        array(
            'filename' => 'cat_group_info',
            'assign' => array(
                'IMG_URL' => $img_url,
                'CAT_NAME' => \Phyxo\Functions\Plugin::trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
                'LINK' => \Phyxo\Functions\URL::make_index_url(array(
                    'category' => array(
                        'id' => $category['id'],
                        'name' => \Phyxo\Functions\Plugin::trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
                        'permalink' => $category['permalink']
                    )
                )),
                'CPL_CONTENT' => empty($_POST['mail_content']) ? '' : stripslashes($_POST['mail_content']),
            )
        )
    );

    \Phyxo\Functions\URL::unset_make_full_url();

    $query = 'SELECT name FROM ' . GROUPS_TABLE . ' WHERE id = ' . $conn->db_real_escape_string($_POST['group']);
    list($group_name) = $conn->db_fetch_row($conn->db_query($query));

    $page['infos'][] = \Phyxo\Functions\Language::l10n('An information email was sent to group "%s"', $group_name);
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->assign(
    array(
        'CATEGORIES_NAV' => \Phyxo\Functions\Category::get_cat_display_name_from_id(
            $page['cat'],
            './index.php?page=album&amp;cat_id='
        ),
        'F_ACTION' => ALBUM_BASE_URL . '&amp;section=notification',
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    )
);

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

$query = 'SELECT id AS group_id FROM ' . GROUPS_TABLE;
$all_group_ids = $conn->query2array($query, null, 'group_id');

if (count($all_group_ids) == 0) {
    $template->assign('no_group_in_gallery', true);
} else {
    if ('private' == $category['status']) {
        $query = 'SELECT group_id FROM ' . GROUP_ACCESS_TABLE . ' WHERE cat_id = ' . $category['id'];
        $group_ids = $conn->query2array($query, null, 'group_id');

        if (count($group_ids) == 0) {
            $template->assign('permission_url', ALBUM_BASE_URL . '&amp;section=permissions');
        }
    } else {
        $group_ids = $all_group_ids;
    }

    if (count($group_ids) > 0) {
        $query = 'SELECT id,name FROM ' . GROUPS_TABLE;
        $query .= ' WHERE id ' . $conn->in($group_ids) . ' ORDER BY name ASC';
        $template->assign(
            'group_mail_options',
            $conn->query2array($query, 'id', 'name')
        );
    }
}
