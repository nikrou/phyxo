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

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

/**
 * Display filtered history lines
 */
// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

define('HISTORY_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=history');

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
    $page['section'] = 'stats';
}

$tabsheet = new TabSheet();
$tabsheet->add('stats', \Phyxo\Functions\Language::l10n('Statistics'), HISTORY_BASE_URL . '&amp;section=stats', 'fa-signal');
$tabsheet->add('search', \Phyxo\Functions\Language::l10n('Search'), HISTORY_BASE_URL . '&amp;section=search', 'fa-search');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => HISTORY_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'history_' . $page['section'];

include(PHPWG_ROOT_PATH . 'admin/history_' . $page['section'] . '.php');
