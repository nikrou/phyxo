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

define('GROUPS_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=groups');

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!empty($_POST) or isset($_GET['delete']) or isset($_GET['toggle_is_default'])) {
    \Phyxo\Functions\Utils::check_token();
}
// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'list';
}

$tabsheet = new TabSheet();
$tabsheet->add('list', \Phyxo\Functions\Language::l10n('Groups'), GROUPS_BASE_URL . '&amp;section=list', 'fa-group');
$tabsheet->add('perm', \Phyxo\Functions\Language::l10n('Permissions'), GROUPS_BASE_URL . '&amp;section=perm', 'fa-lock');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => GROUPS_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'groups_' . $page['section'];

include(PHPWG_ROOT_PATH . 'admin/groups_' . $page['section'] . '.php');
