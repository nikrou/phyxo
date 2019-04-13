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

define('UPDATES_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=updates');

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'core';
}

$tabsheet = new TabSheet();
$tabsheet->add('core', \Phyxo\Functions\Language::l10n('Phyxo Update'), UPDATES_BASE_URL . '&amp;section=core');
$tabsheet->add('ext', \Phyxo\Functions\Language::l10n('Extensions Update'), UPDATES_BASE_URL . '&amp;section=ext');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => UPDATES_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'updates_' . $page['section'];

include(__DIR__ . '/updates_' . $page['section'] . '.php');
