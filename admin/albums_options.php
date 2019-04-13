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

define('ALBUMS_OPTIONS_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=albums_options');

use Phyxo\TabSheet\TabSheet;
use App\Repository\CategoryRepository;

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'status';
}

$tabsheet = new TabSheet();
$tabsheet->add('status', \Phyxo\Functions\Language::l10n('Public / Private'), ALBUMS_OPTIONS_BASE_URL . '&amp;section=status', 'fa-lock');
$tabsheet->add('visible', \Phyxo\Functions\Language::l10n('Lock'), ALBUMS_OPTIONS_BASE_URL . '&amp;section=visible', 'fa-ban');
if ($conf['activate_comments']) {
    $tabsheet->add('comments', \Phyxo\Functions\Language::l10n('Comments'), ALBUMS_OPTIONS_BASE_URL . '&amp;section=comments', 'fa-comments');
}
if ($conf['allow_random_representative']) {
    $tabsheet->add('representative', \Phyxo\Functions\Language::l10n('Representative'), ALBUMS_OPTIONS_BASE_URL . '&amp;section=representative');
}
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => ALBUMS_OPTIONS_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                       modification registration                       |
// +-----------------------------------------------------------------------+

if (isset($_POST['falsify'], $_POST['cat_true']) && count($_POST['cat_true']) > 0) {
    switch ($_GET['section']) {
        case 'comments':
            {
                (new CategoryRepository($conn))->updateCategories(['commentable' => false], $_POST['cat_true']);
                break;
            }
        case 'visible':
            {
                \Phyxo\Functions\Category::set_cat_visible($_POST['cat_true'], false);
                break;
            }
        case 'status':
            {
                \Phyxo\Functions\Category::set_cat_status($_POST['cat_true'], 'private');
                break;
            }
        case 'representative':
            {
                (new CategoryRepository($conn))->updateCategories(['representative_picture_id' => null], $_POST['cat_true']);
                break;
            }
    }
} elseif (isset($_POST['trueify'], $_POST['cat_false']) && count($_POST['cat_false']) > 0) {
    switch ($_GET['section']) {
        case 'comments':
            {
                (new CategoryRepository($conn))->updateCategories(['commentable' => true], $_POST['cat_false']);
                break;
            }
        case 'visible':
            {
                \Phyxo\Functions\Category::set_cat_visible($_POST['cat_false'], true);
                break;
            }
        case 'status':
            {
                \Phyxo\Functions\Category::set_cat_status($_POST['cat_false'], 'public');
                break;
            }
        case 'representative':
            {
                // theoretically, all categories in $_POST['cat_false'] contain at
                // least one element, so Phyxo can find a representant.
                \Phyxo\Functions\Category::set_random_representant($_POST['cat_false']);
                break;
            }
    }
}

$template->assign(
    [
        //'U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=cat_options',
        'F_ACTION' => ALBUMS_OPTIONS_BASE_URL . '&amp;section=' . $page['section']
    ]
);

// +-----------------------------------------------------------------------+
// |                              form display                             |
// +-----------------------------------------------------------------------+

// for each section, categories in the multiselect field can be :
//
// - true : commentable for comment section
// - false : un-commentable for comment section
// - NA : (not applicable) for virtual categories
//
// for true and false status, we associates an array of category ids,
// function display_select_categories will use the given CSS class for each
// option
$cats_true = [];
$cats_false = [];
switch ($page['section']) {
    case 'comments':
        {
            $result = (new CategoryRepository($conn))->findByField('commentable', true);
            $cats_true = $conn->result2array($result);
            $result = (new CategoryRepository($conn))->findByField('commentable', false);
            $cats_false = $conn->result2array($result);
            $template->assign([
                'L_SECTION' => \Phyxo\Functions\Language::l10n('Authorize users to add comments on selected albums'),
                'L_CAT_OPTIONS_TRUE' => \Phyxo\Functions\Language::l10n('Authorized'),
                'L_CAT_OPTIONS_FALSE' => \Phyxo\Functions\Language::l10n('Forbidden'),
                'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('Comments'),
            ]);
            break;
        }
    case 'visible':
        {
            $result = (new CategoryRepository($conn))->findByField('visible', true);
            $cats_true = $conn->result2array($result);
            $result = (new CategoryRepository($conn))->findByField('visible', false);
            $cats_false = $conn->result2array($result);
            $template->assign([
                'L_SECTION' => \Phyxo\Functions\Language::l10n('Lock albums'),
                'L_CAT_OPTIONS_TRUE' => \Phyxo\Functions\Language::l10n('Unlocked'),
                'L_CAT_OPTIONS_FALSE' => \Phyxo\Functions\Language::l10n('Locked'),
                'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('Lock'),
            ]);
            break;
        }
    case 'status':
        {
            $result = (new CategoryRepository($conn))->findByField('status', 'public');
            $cats_true = $conn->result2array($result);
            $result = (new CategoryRepository($conn))->findByField('status', 'private');
            $cats_false = $conn->result2array($result);
            $template->assign([
                'L_SECTION' => \Phyxo\Functions\Language::l10n('Manage authorizations for selected albums'),
                'L_CAT_OPTIONS_TRUE' => \Phyxo\Functions\Language::l10n('Public'),
                'L_CAT_OPTIONS_FALSE' => \Phyxo\Functions\Language::l10n('Private'),
                'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('Public / Private'),
            ]);
            break;
        }
    case 'representative':
        {
            $result = (new CategoryRepository($conn))->findWithRepresentant();
            $cats_true = $conn->result2array($result);
            $result = (new CategoryRepository($conn))->findWithNoRepresentant();
            $cats_false = $conn->result2array($result);
            $template->assign([
                'L_SECTION' => \Phyxo\Functions\Language::l10n('Representative'),
                'L_CAT_OPTIONS_TRUE' => \Phyxo\Functions\Language::l10n('singly represented'),
                'L_CAT_OPTIONS_FALSE' => \Phyxo\Functions\Language::l10n('randomly represented'),
                'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('Representative'),
            ]);
            break;
        }
}
\Phyxo\Functions\Category::display_select_cat_wrapper($cats_true, [], 'category_option_true');
\Phyxo\Functions\Category::display_select_cat_wrapper($cats_false, [], 'category_option_false');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template_filename = 'albums_options';

$template->set_filenames(['double_select' => 'double_select.tpl']);
$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
