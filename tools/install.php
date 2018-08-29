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
use Phyxo\Conf;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->load(__DIR__ . '/../.env');

define('DEFAULT_PREFIX_TABLE', 'phyxo_');
$prefixeTable = 'phyxo_';
$user = [];
$cache = [];

try {
    $conn = DBLayer::initFromDSN($_SERVER['DATABASE_URL']);
    $conn->db_check_version();
} catch (Exception $e) {
    die($e->getMessage());
}

$conf = new Conf($conn);
$conf->loadFromFile(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
$languages = new Languages($conn, 'utf-8');

require_once(PHPWG_ROOT_PATH . 'include/constants.php');

// services
include(PHPWG_ROOT_PATH . 'include/services.php');

$conn->executeSqlFile(
    PHPWG_ROOT_PATH . 'install/phyxo_structure-' . $conn->getLayer() . '.sql',
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

$conf['phyxo_db_version'] = \Phyxo\Functions\Utils::get_branch_from_version(PHPWG_VERSION);
$conf['gallery_title'] = \Phyxo\Functions\Language::l10n('Just another Phyxo gallery');
$conf['page_banner'] = '<h1>%gallery_title%</h1>' . "\n\n<p>" . \Phyxo\Functions\Language::l10n('Welcome to my photo gallery') . '</p>';

// fill languages table
$languages->setConnection($conn);
foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
    $languages->performAction('activate', $language_code);
}

$conf->loadFromDB();
if (!defined('PWG_CHARSET')) {
    define('PWG_CHARSET', 'utf-8');
}

$themes = new Themes($conn);
foreach ($themes->getFsThemes() as $theme_id => $fs_theme) {
    if (in_array($theme_id, ['elegant'])) {
        $themes->performAction('activate', $theme_id);
    }
}

$insert = ['id' => 1, 'galleries_url' => PHPWG_ROOT_PATH . 'galleries/'];
$conn->mass_inserts(SITES_TABLE, array_keys($insert), [$insert]);
if ($conf['dblayer'] == 'pgsql') {
    $conn->db_query('ALTER SEQUENCE ' . strtolower(SITES_TABLE) . '_id_seq RESTART WITH 2');
}

$inserts = [
    [
        'id' => 1,
        'username' => 'admin',
        'password' => password_hash(openssl_random_pseudo_bytes(15), PASSWORD_BCRYPT), // don't care, don't want access
        'mail_address' => 'nikrou77@gmail.com',
    ],
    [
        'id' => 2,
        'username' => 'guest',
    ],
];
$conn->mass_inserts(USERS_TABLE, array_keys($inserts[0]), $inserts);
if ($conf['dblayer'] == 'pgsql') {
    $conn->db_query('ALTER SEQUENCE ' . strtolower(USERS_TABLE) . '_id_seq RESTART WITH 3');
}

$services['users']->createUserInfos([1, 2], ['language' => 'en']);

list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
define('CURRENT_DATE', $dbnow);
$datas = [];
foreach (\Phyxo\Functions\Upgrade::get_available_upgrade_ids() as $upgrade_id) {
    $datas[] = [
        'id' => $upgrade_id,
        'applied' => CURRENT_DATE,
        'description' => 'upgrade included in installation',
    ];
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
