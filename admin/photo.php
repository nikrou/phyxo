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

if (!defined("PHPWG_ROOT_PATH")) {
    die("Hacking attempt!");
}

use Phyxo\TabSheet\TabSheet;
use App\Repository\CategoryRepository;

// +-----------------------------------------------------------------------+
// | Basic checks                                                          |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

\Phyxo\Functions\Utils::check_input_parameter('cat_id', $_GET, false, PATTERN_ID);
\Phyxo\Functions\Utils::check_input_parameter('image_id', $_GET, false, PATTERN_ID);

define('PHOTO_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&amp;image_id=' . $_GET['image_id']);

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'properties';
}

$tabsheet = new TabSheet();
$tabsheet->add('properties', \Phyxo\Functions\Language::l10n('Properties'), PHOTO_BASE_URL . '&amp;section=properties');
$tabsheet->add('coi', \Phyxo\Functions\Language::l10n('Center of interest'), PHOTO_BASE_URL . '&amp;section=coi', 'fa-crop');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => PHOTO_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

if (!empty($_GET['cat_id'])) {
    $result = (new CategoryRepository($conn))->findById($_GET['cat_id']);
    $category = $conn->db_fetch_assoc($result);
}

// +-----------------------------------------------------------------------+
// |                             Load the tab                              |
// +-----------------------------------------------------------------------+

$template_filename = 'photo_' . $page['section'];

include(PHPWG_ROOT_PATH . 'admin/photo_' . $page['section'] . '.php');
