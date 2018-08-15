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

define('PHPWG_ROOT_PATH', __DIR__ . '/../');

require_once(PHPWG_ROOT_PATH . 'vendor/autoload.php');

// @TODO: refactoring between that script and web install through ../install.php

use Phyxo\DBLayer\DBLayer;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;

require_once(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
require_once(PHPWG_ROOT_PATH . 'local/config/database.inc.php');
require_once(PHPWG_ROOT_PATH . 'include/constants.php');

define('DEFAULT_PREFIX_TABLE', 'phyxo_');

try {
    $conn = DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
    $conn->db_check_version();
} catch (Exception $e) {
    die($e->getMessage());
}

// services
include(PHPWG_ROOT_PATH . 'include/services.php');

$languages = new Languages($conn, 'utf-8');

$conn->executeSqlFile(
    PHPWG_ROOT_PATH . 'install/phyxo_structure-' . $conf['dblayer'] . '.sql',
    DEFAULT_PREFIX_TABLE,
    $prefixeTable
);
$conn->executeSqlFile(
    PHPWG_ROOT_PATH . 'install/config.sql',
    DEFAULT_PREFIX_TABLE,
    $prefixeTable
);

$query = 'INSERT INTO ' . CONFIG_TABLE . ' (param,value,comment)';
$query .= 'VALUES (\'secret_key\',';
$query .= 'md5(' . $conn->db_cast_to_text($conn::RANDOM_FUNCTION . '()') . '),';
$query .= '\'a secret key specific to the gallery for internal use\')';
$conn->db_query($query);

\Phyxo\Functions\Conf::conf_update_param('phyxo_db_version', \Phyxo\Functions\Utils::get_branch_from_version(PHPWG_VERSION));
\Phyxo\Functions\Conf::conf_update_param('gallery_title', \Phyxo\Functions\Language::l10n('Just another Phyxo gallery'));
\Phyxo\Functions\Conf::conf_update_param('page_banner', '<h1>%gallery_title%</h1>' . "\n\n<p>" . \Phyxo\Functions\Language::l10n('Welcome to my photo gallery') . '</p>');

// fill languages table
$languages->setConnection($conn);
foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
    $languages->performAction('activate', $language_code);
}

\Phyxo\Functions\Conf::load_conf_from_db();
if (!defined('PWG_CHARSET')) {
    define('PWG_CHARSET', 'utf-8');
}

$themes = new Themes($conn);
foreach ($themes->getFsThemes() as $theme_id => $fs_theme) {
    if (in_array($theme_id, array('elegant'))) {
        $themes->performAction('activate', $theme_id);
    }
}

$insert = array('id' => 1, 'galleries_url' => PHPWG_ROOT_PATH . 'galleries/');
$conn->mass_inserts(SITES_TABLE, array_keys($insert), array($insert));
if ($conf['dblayer'] == 'pgsql') {
    $conn->db_query('ALTER SEQUENCE ' . strtolower(SITES_TABLE) . '_id_seq RESTART WITH 2');
}

$inserts = array(
    array(
        'id' => 1,
        'username' => 'admin',
        'password' => password_hash(openssl_random_pseudo_bytes(15), PASSWORD_BCRYPT), // don't care, don't want access
        'mail_address' => 'nikrou77@gmail.com',
    ),
    array(
        'id' => 2,
        'username' => 'guest',
    ),
);
$conn->mass_inserts(USERS_TABLE, array_keys($inserts[0]), $inserts);
if ($conf['dblayer'] == 'pgsql') {
    $conn->db_query('ALTER SEQUENCE ' . strtolower(USERS_TABLE) . '_id_seq RESTART WITH 3');
}

$services['users']->createUserInfos(array(1, 2), array('language' => 'en'));

list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
define('CURRENT_DATE', $dbnow);
$datas = array();
foreach (\Phyxo\Functions\Upgrade::get_available_upgrade_ids() as $upgrade_id) {
    $datas[] = array(
        'id' => $upgrade_id,
        'applied' => CURRENT_DATE,
        'description' => 'upgrade included in installation',
    );
}
$conn->mass_inserts(
    UPGRADE_TABLE,
    array_keys($datas[0]),
    $datas
);
if (!is_dir(PHPWG_ROOT_PATH . $conf['data_location'])) {
    mkdir(PHPWG_ROOT_PATH . $conf['data_location']);
}
if (!is_dir(PHPWG_ROOT_PATH . $conf['upload_dir'])) {
    mkdir(PHPWG_ROOT_PATH . $conf['upload_dir']);
}
chmod($conf['data_location'], 0777);
chmod($conf['upload_dir'], 0777);
chmod(PHPWG_ROOT_PATH . 'db', 0777);
