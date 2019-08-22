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
use Phyxo\DBLayer\DBLayer;
use Phyxo\Upgrade;
use Phyxo\EntityManager;
use Phyxo\Conf;

require __DIR__ . '/vendor/autoload.php';

define('PHPWG_ROOT_PATH', './');
define('IN_ADMIN', true);

define('DEFAULT_PREFIX_TABLE', 'phyxo_');
defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
}
include(PHPWG_ROOT_PATH . 'include/constants.php');
include(PHPWG_ROOT_PATH . 'local/config/database.inc.php');

define('PREFIX_TABLE', $prefixeTable);
define('UPGRADES_PATH', __DIR__ . '/install/db');

$conn = DBLayer::init($conf['dblayer'], $conf['db_host'], isset($conf['db_user'])?$conf['db_user']:'', isset($conf['db_password'])?$conf['db_password']:'', $conf['db_base']);
$em = new EntityManager($conn);
$conf = new Conf($conn);

// +-----------------------------------------------------------------------+
// |                              Upgrades                                 |
// +-----------------------------------------------------------------------+

$upgrade = new Upgrade($em, $conf);

// retrieve already applied upgrades
$result = (new UpgradeRepository($conn))->findAll();
$applied = $conn->result2array($result, null, 'id');

// retrieve existing upgrades
$existing = $upgrade->getAvailableUpgradeIds(__DIR__ . '/');

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied);

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
