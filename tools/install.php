<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', __DIR__.'/../');

require_once(PHPWG_ROOT_PATH.'vendor/autoload.php');

// @TODO: refactoring between that script and web install through ../install.php

use Phyxo\DBLayer\DBLayer;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;

require_once(PHPWG_ROOT_PATH.'include/config_default.inc.php');
require_once(PHPWG_ROOT_PATH.'local/config/database.inc.php');
require_once(PHPWG_ROOT_PATH.'admin/include/functions_install.inc.php');
require_once(PHPWG_ROOT_PATH.'admin/include/functions_upgrade.php');
require_once(PHPWG_ROOT_PATH.'local/config/database.inc.php');

require_once(PHPWG_ROOT_PATH.'include/constants.php');
require_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
require_once(PHPWG_ROOT_PATH.'include/dblayer/functions_dblayer.inc.php');
require_once(PHPWG_ROOT_PATH.'include/functions.inc.php');

define('DEFAULT_PREFIX_TABLE', 'phyxo_');

try {
    $conn = DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
    $conn->db_check_version();
} catch (Exception $e) {
    die($e->getMessage());
}

$languages = new Languages($conn, 'utf-8');

execute_sqlfile(
    PHPWG_ROOT_PATH.'install/phyxo_structure-'.$conf['dblayer'].'.sql',
    DEFAULT_PREFIX_TABLE,
    $prefixeTable,
    $conf['dblayer']
);
execute_sqlfile(
    PHPWG_ROOT_PATH.'install/config.sql',
    DEFAULT_PREFIX_TABLE,
    $prefixeTable,
    $conf['dblayer']
);

conf_update_param(
    'secret_key',
    'md5('.pwg_db_cast_to_text($conn::RANDOM_FUNCTION.'()').')',
    'a secret key specific to the gallery for internal use'
);
conf_update_param('phyxo_db_version', get_branch_from_version(PHPWG_VERSION));
conf_update_param('gallery_title', l10n('Just another Phyxo gallery'));
conf_update_param('page_banner', '<h1>%gallery_title%</h1>'."\n\n<p>".l10n('Welcome to my photo gallery').'</p>');

// fill languages table
$languages->setConnection($conn);
foreach ($languages->fs_languages as $language_code => $fs_language) {
    $languages->perform_action('activate', $language_code);
}

load_conf_from_db();
if (!defined('PWG_CHARSET')) {
    define('PWG_CHARSET', 'utf-8');
}

$themes = new Themes($conn);
foreach ($themes->fs_themes as $theme_id => $fs_theme) {
    if (in_array($theme_id, array('elegant'))) {
        $themes->perform_action('activate', $theme_id);
    }
}

$insert = array('id' => 1, 'galleries_url' => PHPWG_ROOT_PATH.'galleries/');
$conn->mass_inserts(SITES_TABLE, array_keys($insert), array($insert));

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

create_user_infos(array(1,2), array('language' => 'en'));

list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
define('CURRENT_DATE', $dbnow);
$datas = array();
foreach (get_available_upgrade_ids() as $upgrade_id) {
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
if (!is_dir(PHPWG_ROOT_PATH.$conf['data_location'])) {
    mkdir(PHPWG_ROOT_PATH.$conf['data_location']);
}
if (!is_dir(PHPWG_ROOT_PATH.$conf['upload_dir'])) {
    mkdir(PHPWG_ROOT_PATH.$conf['upload_dir']);
}
chmod($conf['data_location'], 0777);
chmod($conf['upload_dir'], 0777);
chmod(PHPWG_ROOT_PATH.'db', 0777);
