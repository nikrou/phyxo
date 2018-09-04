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

use Phyxo\DBLayer\DBLayer;

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!$conf['check_upgrade_feed']) {
    die("upgrade feed is not active");
}

define('PREFIX_TABLE', $prefixeTable);
define('UPGRADES_PATH', PHPWG_ROOT_PATH . 'install/db');

// +-----------------------------------------------------------------------+
// |                              Upgrades                                 |
// +-----------------------------------------------------------------------+

// retrieve already applied upgrades
$query = 'SELECT id FROM ' . PREFIX_TABLE . 'upgrade;';
$applied = $conn->query2array($query, null, 'id');

// retrieve existing upgrades
$existing = \Phyxo\Functions\Upgrade::get_available_upgrade_ids();

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied);

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

foreach ($to_apply as $upgrade_id) {
    unset($upgrade_description);

    printf('<h3>Upgrade %s</h3>', $upgrade_id);

    // include & execute upgrade script. Each upgrade script must contain
    // $upgrade_description variable which describe briefly what the upgrade
    // script does.
    include(UPGRADES_PATH . '/' . $upgrade_id . '-database.php');

    // notify upgrade
    $query = 'INSERT INTO ' . PREFIX_TABLE . 'upgrade (id, applied, description)';
    $query .= ' VALUES(\'' . $upgrade_id . '\', NOW(), \'' . $upgrade_description . '\');';
    $conn->db_query($query);
}

$upgrade_content = ob_get_contents();
ob_end_clean();

$template->assign('upgrade_content', $upgrade_content);

$template_filename = 'upgrade_feed';
