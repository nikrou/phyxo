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
  die ("Hacking attempt!");
}

define('THEMES_BASE_URL', get_root_url().'admin/index.php?page=themes');

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
    $page['section'] = 'installed';
}

$tabsheet = new TabSheet();
$tabsheet->add('installed', l10n('Installed Themes'), THEMES_BASE_URL.'&amp;section=installed', 'fa-paint-brush');
$tabsheet->add('update', l10n('Check for updates'), THEMES_BASE_URL.'&amp;section=update', 'fa-refresh');
$tabsheet->add('new', l10n('Add New Theme'), THEMES_BASE_URL.'&amp;section=new', 'fa-plus-circle');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => THEMES_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'themes_'.$page['section'];

include(PHPWG_ROOT_PATH.'admin/themes_'.$page['section'].'.php');
