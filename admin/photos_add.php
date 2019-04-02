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

define('PHOTOS_ADD_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photos_add');

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                          Load configuration                           |
// +-----------------------------------------------------------------------+

$upload_form_config = \Phyxo\Functions\Upload::get_upload_form_config();

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'direct';
}

$tabsheet = new TabSheet();
$tabsheet->add('direct', \Phyxo\Functions\Language::l10n('Web Form'), PHOTOS_ADD_BASE_URL . '&amp;section=direct', 'fa-upload');
/*
if ($conf['enable_synchronization']) {
    $tabsheet->add('ftp', \Phyxo\Functions\Language::l10n('FTP + Synchronization'), PHOTOS_ADD_BASE_URL.'&amp;section=ftp', 'fa-exchange');
}
 */
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => PHOTOS_ADD_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'photos_add_' . $page['section'];

include(__DIR__ . '/photos_add_' . $page['section'] . '.php');
