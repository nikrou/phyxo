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

$release_from = '1.2.0';
$first_id = 145;
$last_id = 145;

// retrieve already applied upgrades
$query = 'SELECT id  FROM ' . PREFIX_TABLE . 'upgrade;';
$applied = $conn->query2array($query, null, 'id');

// retrieve existing upgrades
$existing = \Phyxo\Functions\Upgrade::get_available_upgrade_ids();

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied);
$inserts = [];
foreach ($to_apply as $upgrade_id) {
    if ($upgrade_id >= $first_id) { // TODO change on each release
        break;
    }

    $inserts[] = [
        'id' => $upgrade_id,
        'applied' => CURRENT_DATE,
        'description' => sprintf('[migration from %s to %s] not applied', $release_from, PHPWG_VERSION)
    ];
}

if (!empty($inserts)) {
    (new UpgradeRepository($conn))->massInserts(array_keys($inserts[0]), $inserts);
}

// +-----------------------------------------------------------------------+
// |                          Perform upgrades                             |
// +-----------------------------------------------------------------------+

ob_start();
echo '<pre>';

for ($upgrade_id = $first_id; $upgrade_id <= $last_id; $upgrade_id++) {
    if (!file_exists(UPGRADES_PATH . '/' . $upgrade_id . '-database.php')) {
        continue;
    }

    // maybe the upgrade task has already been applied in a previous and
    // incomplete upgrade
    if (in_array($upgrade_id, $applied)) {
        continue;
    }

    unset($upgrade_description);

    echo "\n\n";
    echo '=== upgrade ' . $upgrade_id . "\n";

    // include & execute upgrade script. Each upgrade script must contain
    // $upgrade_description variable which describe briefly what the upgrade
    // script does.
    include(UPGRADES_PATH . '/' . $upgrade_id . '-database.php');

    $query = "INSERT INTO phyxo_config (param,value) VALUES ('publish_tags_immediately',0)";
    $conn->db_query($query);
    $query = "INSERT INTO phyxo_config (param,value) VALUES ('delete_tags_immediately',0)";
    $conn->db_query($query);
    $query = "INSERT INTO phyxo_config (param,value) VALUES ('show_pending_added_tags',0)";
    $conn->db_query($query);
    $query = "INSERT INTO phyxo_config (param,value) VALUES ('show_pending_deleted_tags',0)";
    $conn->db_query($query);

    // notify upgrade (TODO change on each release)
    $query = 'INSERT INTO ' . PREFIX_TABLE . 'upgrade (id, applied, description)';
    $query .= ' VALUES';
    $query .= '(\'' . $upgrade_id . '\', NOW(), \'[migration from ' . $release_from . ' to ' . PHPWG_VERSION . '] ' . $upgrade_description . '\');';
    $conn->db_query($query);
}

echo '</pre>';
ob_end_clean();
