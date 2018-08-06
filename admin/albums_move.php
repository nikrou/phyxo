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

if (!defined('ALBUMS_BASE_URL')) {
    die('Hacking attempt!');
}

// +-----------------------------------------------------------------------+
// |                          categories movement                          |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit'])) {
    if (count($_POST['selection']) > 0) {
        // @TODO: tests
        move_categories($_POST['selection'], $_POST['parent']);
    } else {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one album');
    }
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->assign(
    array(
        //'U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=cat_move',
        'F_ACTION' => ALBUMS_BASE_URL . '&amp;section=move',
    )
);

// +-----------------------------------------------------------------------+
// |                          Categories display                           |
// +-----------------------------------------------------------------------+

$query = 'SELECT id,name,uppercats,global_rank FROM ' . CATEGORIES_TABLE . ' WHERE dir IS NULL;';
display_select_cat_wrapper(
    $query,
    array(),
    'category_to_move_options'
);

$query = 'SELECT id,name,uppercats,global_rank FROM ' . CATEGORIES_TABLE;

display_select_cat_wrapper(
    $query,
    array(),
    'category_parent_options'
);
