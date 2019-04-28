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

use App\Repository\CategoryRepository;

// +-----------------------------------------------------------------------+
// |                          categories movement                          |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit'])) {
    if (count($_POST['selection']) > 0) {
        // @TODO: tests
        $categoryMapper->moveCategories($_POST['selection'], $_POST['parent']);
    } else {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one album');
    }
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->assign(
    [
        'F_ACTION' => ALBUMS_BASE_URL . '&amp;section=move',
    ]
);

// +-----------------------------------------------------------------------+
// |                          Categories display                           |
// +-----------------------------------------------------------------------+
$result = (new CategoryRepository($conn))->findWithCondition(['dir IS NULL']);
$categories = $conn->result2array($result);
$template->assign($categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_to_move_options'));

$result = (new CategoryRepository($conn))->findAll();
$categories = $conn->result2array($result);
$template->assign($categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_parent_options'));
