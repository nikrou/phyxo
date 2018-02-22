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
    die ('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

define('TAGS_BASE_URL', get_root_url().'admin/index.php?page=tags');

use Phyxo\TabSheet\TabSheet;

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!empty($_POST)) {
    check_pwg_token();
}

if (!empty($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'all';
}

$tabsheet = new TabSheet();
$tabsheet->add('all', l10n('All tags'), TAGS_BASE_URL.'&section=all');
$tabsheet->add('perm', l10n('Permissions'), TAGS_BASE_URL.'&section=perm');
$tabsheet->add('pending', l10n('Pendings'), TAGS_BASE_URL.'&section=pending');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => TAGS_BASE_URL
]);

include_once(PHPWG_ROOT_PATH.'admin/tags_'.$page['section'].'.php');

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'tags_'.$page['section'];

$template->assign([
    'F_ACTION' => get_root_url().'admin/index.php?page=tags&amp;section='.$page['section'],
    'PWG_TOKEN' => get_pwg_token(),
]);
