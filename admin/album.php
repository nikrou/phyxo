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

use Phyxo\TabSheet\TabSheet;
use App\Repository\CategoryRepository;

// +-----------------------------------------------------------------------+
// | Basic checks                                                          |
// +-----------------------------------------------------------------------+

\Phyxo\Functions\Utils::check_input_parameter('cat_id', $_GET, false, PATTERN_ID);

$category = (new CategoryRepository($conn))->findById($_GET['cat_id']);
foreach ($category as $k => $v) {
    if (!is_null($v) && $conn->is_boolean($v)) {
        $category[$k] = $conn->get_boolean($v);
    }
}

define('ALBUM_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=album&amp;cat_id=' . $category['id']);

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'properties';
}

$tabsheet = new TabSheet();
$tabsheet->add('properties', \Phyxo\Functions\Language::l10n('Properties'), ALBUM_BASE_URL . '&amp;section=properties', 'fa-pencil');
$tabsheet->add('sort_order', \Phyxo\Functions\Language::l10n('Manage photo ranks'), ALBUM_BASE_URL . '&amp;section=sort_order', 'fa-random');
$tabsheet->add('permissions', \Phyxo\Functions\Language::l10n('Permissions'), ALBUM_BASE_URL . '&amp;section=permissions', 'fa-lock');
$tabsheet->add('notification', \Phyxo\Functions\Language::l10n('Notification'), ALBUM_BASE_URL . '&amp;section=notification', 'fa-envelope');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => ALBUM_BASE_URL,
    'U_ALBUMS' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=albums',
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'album_' . $page['section'];

include(__DIR__ . '/album_' . $page['section'] . '.php');
