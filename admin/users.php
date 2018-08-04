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

/**
 * Add users and manage users list
 */

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

use Phyxo\TabSheet\TabSheet;

define('USERS_BASE_URL', get_root_url() . 'admin/index.php?page=users');

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'list';
}

$tabsheet = new TabSheet();
$tabsheet->add('list', \Phyxo\Functions\Language::l10n('User list'), USERS_BASE_URL . '&amp;section=list', 'fa-users');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => USERS_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'users_' . $page['section'];

include(PHPWG_ROOT_PATH . 'admin/users_' . $page['section'] . '.php');
