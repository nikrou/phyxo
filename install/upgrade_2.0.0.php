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

use Phyxo\Upgrade;
use App\Repository\UpgradeRepository;

$release_from = '2.0.0';
$release_to = '2.1.0';
$first_id = 150;
$last_id = 150;

$default_prefix = 'phyxo_';

$load = (function ($path) use ($default_prefix) {
    $conf = [];

    include $path;

    return [
        'dblayer' => $conf['dblayer'],
        'db_host' => isset($conf['db_host']) ? $conf['db_host']: '',
        'db_user' => isset($conf['db_user']) ? $conf['db_user'] : '',
        'db_password' => isset($conf['db_password']) ? $conf['db_password'] : '',
        'db_base' => $conf['db_base'],
        'db_prefix' => isset($conf['db_prefix']) ? $conf['db_prefix'] : $default_prefix,
    ];
});

$db_params = $load(__DIR__ . '/../local/config/database.inc.php');

$file_content = 'parameters:' . "\n";
$file_content .= '  database_driver: \'pdo_' . $db_params['dblayer'] . "'\n";
$file_content .= '  database_name: \'' . $db_params['db_base'] . "'\n";
if ($db_params['dblayer'] !== 'sqlite') {
    $file_content .= '  database_host: \'' . $db_params['db_host'] . "'\n";
    $file_content .= '  database_user: \'' . $db_params['db_user'] . "'\n";
    $file_content .= '  database_password: \'' . $db_params['db_password'] . "'\n";
}
$file_content .= '  database_prefix: \'' . $db_params['db_prefix'] . "'\n\n";

$database_yaml = __DIR__ . '/../config/database.yaml';
file_put_contents($database_yaml, $file_content);

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
    (new UpgradeRepository($conn))->addUpgrade("$upgrade_id", '[migration from ' . $release_from . ' to ' . $release_to . '] ' . $upgrade_description);
}

echo '</pre>';
