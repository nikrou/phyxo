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


define('PHPWG_ROOT_PATH', '../../');

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');
use Phyxo\DBLayer\DBLayer;

include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
}
defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

include(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/database.inc.php');

include_once(PHPWG_ROOT_PATH . 'include/functions.inc.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upgrade.php');


// +-----------------------------------------------------------------------+
// | Check Access and exit when it is not ok                               |
// +-----------------------------------------------------------------------+

if (!$conf['check_upgrade_feed']) {
    die("upgrade feed is not active");
}

prepare_conf_upgrade();

define('PREFIX_TABLE', $prefixeTable);
define('UPGRADES_PATH', PHPWG_ROOT_PATH . 'install/db');

// +-----------------------------------------------------------------------+
// |                         Database connection                           |
// +-----------------------------------------------------------------------+
try {
    $conn = DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
} catch (Exception $e) {
    my_error(l10n($e->getMessage(), true));
}

// +-----------------------------------------------------------------------+
// |                              Upgrades                                 |
// +-----------------------------------------------------------------------+

// retrieve already applied upgrades
$query = 'SELECT id FROM ' . PREFIX_TABLE . 'upgrade;';
$applied = $conn->query2array($query, null, 'id');

// retrieve existing upgrades
$existing = get_available_upgrade_ids();

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied);

echo '<pre>';
echo count($to_apply) . ' upgrades to apply';

foreach ($to_apply as $upgrade_id) {
    unset($upgrade_description);

    echo "\n\n";
    echo '=== upgrade ' . $upgrade_id . "\n";

    // include & execute upgrade script. Each upgrade script must contain
    // $upgrade_description variable which describe briefly what the upgrade
    // script does.
    include(UPGRADES_PATH . '/' . $upgrade_id . '-database.php');

    // notify upgrade
    $query = 'INSERT INTO ' . PREFIX_TABLE . 'upgrade (id, applied, description)';
    $query .= ' VALUES(\'' . $upgrade_id . '\', NOW(), \'' . $upgrade_description . '\');';
    $conn->db_query($query);
}

echo '</pre>';
