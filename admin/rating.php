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

define('RATING_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=rating');

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'photos';
}

$tabsheet = new TabSheet();
$tabsheet->add('photos', \Phyxo\Functions\Language::l10n('Photos'), RATING_BASE_URL . '&amp;section=photos');
$tabsheet->add('users', \Phyxo\Functions\Language::l10n('Users'), RATING_BASE_URL . '&amp;section=users');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => RATING_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'rating_' . $page['section'];

include(__DIR__ . '/rating_' . $page['section'] . '.php');
