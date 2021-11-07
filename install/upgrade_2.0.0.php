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

use App\Entity\Upgrade as UpgradeEntity;
use App\Repository\UpgradeRepository;
use Phyxo\Upgrade;

$release_from = '2.0.0';
$release_to = '2.1.0';
$first_id = 150;
$last_id = 150;

$default_prefix = 'phyxo_';

// retrieve existing upgrades
$existing = Upgrade::getAvailableUpgradeIds(dirname(__DIR__));

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied_upgrades);
$inserts = [];
foreach ($to_apply as $upgrade_id) {
    if ($upgrade_id >= $first_id) { // TODO change on each release
        break;
    }

    $inserts[] = [
        'id' => $upgrade_id,
        'applied' => 'now()',
        'description' => sprintf('[migration from %s to %s] not applied', $release_from, $release_to)
    ];
}

if (!empty($inserts)) {
    (new UpgradeRepository($conn))->massInserts(array_keys($inserts[0]), $inserts);
}

echo '<pre>';

for ($upgrade_id = $first_id; $upgrade_id <= $last_id; $upgrade_id++) {
    $upgrade_file = __DIR__ . '/db/' . $upgrade_id . '-database.php';
    if (!is_readable($upgrade_file)) {
        continue;
    }

    // maybe the upgrade task has already been applied in a previous and
    // incomplete upgrade
    if (in_array($upgrade_id, $applied_upgrades)) {
        continue;
    }

    unset($upgrade_description);

    echo "\n\n";
    echo '=== upgrade ' . $upgrade_id . "\n";

    // include & execute upgrade script. Each upgrade script must contain
    // $upgrade_description variable which describe briefly what the upgrade
    // script does.
    include($upgrade_file);

    // notify upgrade (TODO change on each release)
    $upgrade = new UpgradeEntity();
    $upgrade->setId($upgrade_id);
    $upgrade->setApplied(new \DateTime());
    $upgrade->setDescription('[migration from ' . $release_from . ' to ' . $release_to . '] ' . $upgrade_description);
    $upgradeRepository->addUpgrade($upgrade);
}

echo '</pre>';
