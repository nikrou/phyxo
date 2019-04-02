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

use App\Repository\UpgradeRepository;

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!$conf['check_upgrade_feed']) {
    die("upgrade feed is not active");
}

define('PREFIX_TABLE', $prefixeTable);
define('UPGRADES_PATH', __DIR__ . '/../install/db');

// +-----------------------------------------------------------------------+
// |                              Upgrades                                 |
// +-----------------------------------------------------------------------+

// retrieve already applied upgrades
$result = (new UpgradeRepository($conn))->findAll();
$applied = $conn->result2array($result, null, 'id');

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
    (new UpgradeRepository($conn))->addUpgrade($upgrade_id, $upgrade_description);
}

$upgrade_content = ob_get_contents();
ob_end_clean();

$template->assign('upgrade_content', $upgrade_content);

$template_filename = 'upgrade_feed';
