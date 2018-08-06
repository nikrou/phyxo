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

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

define('ALBUMS_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=albums');

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'list';
}

$tabsheet = new TabSheet();
$tabsheet->add('list', \Phyxo\Functions\Language::l10n('List'), ALBUMS_BASE_URL . '&amp;section=list', 'fa-bars');
$tabsheet->add('move', \Phyxo\Functions\Language::l10n('Move'), ALBUMS_BASE_URL . '&amp;section=move', 'fa-move');
$tabsheet->add('permalinks', \Phyxo\Functions\Language::l10n('Permalinks'), ALBUMS_BASE_URL . '&amp;section=permalinks', 'fa-link');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => ALBUMS_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'albums_' . $page['section'];

include(PHPWG_ROOT_PATH . 'admin/albums_' . $page['section'] . '.php');
