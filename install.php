<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

//----------------------------------------------------------- include
define('PHPWG_ROOT_PATH','./');

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

use Phyxo\DBLayer\DBLayer;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;
use Phyxo\Template\Template;

//----------------------------------------------------- variable initialization

define('DEFAULT_PREFIX_TABLE', 'phyxo_');

if (isset($_POST['install'])) {
    $prefixeTable = $_POST['prefix'];
} else {
    $prefixeTable = DEFAULT_PREFIX_TABLE;
}

include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH. 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH. 'local/config/config.inc.php');
}
defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

include(PHPWG_ROOT_PATH . 'include/functions.inc.php');

// download database config file if exists
check_input_parameter('dl', $_GET, false, '/^[a-f0-9]{32}$/');

if (!empty($_GET['dl']) && file_exists(PHPWG_ROOT_PATH.$conf['data_location'].'pwg_'.$_GET['dl'])) {
    $filename = PHPWG_ROOT_PATH.$conf['data_location'].'pwg_'.$_GET['dl'];
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Disposition: attachment; filename="database.inc.php"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: '.filesize($filename));
    echo file_get_contents($filename);
    unlink($filename);
    exit();
}

// all available engines
include(PHPWG_ROOT_PATH . 'admin/include/functions_install.inc.php');
$dbengines = available_engines();

// Obtain various vars
$dbhost = (!empty($_POST['dbhost'])) ? $_POST['dbhost'] : 'localhost';
$dbuser = (!empty($_POST['dbuser'])) ? $_POST['dbuser'] : '';
$dbpasswd = (!empty($_POST['dbpasswd'])) ? $_POST['dbpasswd'] : '';
$dbname = (!empty($_POST['dbname'])) ? $_POST['dbname'] : '';
$dblayer = (!empty($_POST['dblayer']) && !empty($dbengines[$_POST['dblayer']]))?$_POST['dblayer']:'mysql';

if ($dblayer=='mysql' && extension_loaded('mysqli')) {
    $dblayer = 'mysqli';
}

$admin_name = (!empty($_POST['admin_name'])) ? $_POST['admin_name'] : '';
$admin_pass1 = (!empty($_POST['admin_pass1'])) ? $_POST['admin_pass1'] : '';
$admin_pass2 = (!empty($_POST['admin_pass2'])) ? $_POST['admin_pass2'] : '';
$admin_mail = (!empty($_POST['admin_mail'])) ? $_POST['admin_mail'] : '';

$infos = array();
$errors = array();

$config_file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR .'config/database.inc.php';
if (is_readable($config_file)) {
    include($config_file);
    // Is Phyxo already installed ?
    if (defined("PHPWG_INSTALLED")) {
        die('Phyxo is already installed');
    }
}

include(PHPWG_ROOT_PATH . 'include/constants.php');
include(PHPWG_ROOT_PATH . 'admin/include/functions.php');

$languages = new Languages(null, 'utf-8');

if (isset($_GET['language'])) {
    $language = strip_tags($_GET['language']);

    if (!in_array($language, array_keys($languages->fs_languages))) {
        $language = PHPWG_DEFAULT_LANGUAGE;
    }
} else {
    $language = 'en_UK';
    // Try to get browser language
    foreach ($languages->fs_languages as $language_code => $fs_language) {
        if (substr($language_code,0,2) == @substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,2)) {
            $language = $language_code;
            break;
        }
    }
}

load_language('common.lang', '', array('language' => $language, 'target_charset'=>'utf-8'));
load_language('admin.lang', '', array('language' => $language, 'target_charset'=>'utf-8'));
load_language('install.lang', '', array('language' => $language, 'target_charset'=>'utf-8'));

header('Content-Type: text/html; charset=UTF-8');
//------------------------------------------------- check php version
if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
    include(PHPWG_ROOT_PATH.'install/php5_apache_configuration.php');
}

//----------------------------------------------------- template initialization
$template = new Template(PHPWG_ROOT_PATH.'admin/themes', 'clear');
$template->set_filenames( array('install' => 'install.tpl') );
if (!isset($step)) {
    $step = 1;
}

//---------------------------------------------------------------- form analyze
include(PHPWG_ROOT_PATH . 'admin/include/functions_upgrade.php');

if (isset($_POST['install'])) {
    if ($dblayer != 'sqlite') {
        if (empty($dbuser)) {
            $errors[] = l10n('Database user is mandatory');
        }
        if (empty($dbpasswd)) {
            $errors[] = l10n('Database password is mandatory');
        }
        if (empty($dbname)) {
            $errors[] = l10n('Database name is mandatory');
        }
    }
    if (empty($errors)) {
        try {
            $conn = DBLayer::init($_POST['dblayer'], $_POST['dbhost'], $_POST['dbuser'], $_POST['dbpasswd'], $_POST['dbname']);
            $conn->db_check_version();

            include(PHPWG_ROOT_PATH . 'include/services.php');
        } catch (Exception $e) {
            $errors[] = l10n($e->getMessage());
        }
    }

    $webmaster = trim(preg_replace('/\s{2,}/', ' ', $admin_name));
    if (empty($webmaster)) {
        $errors[] = l10n('enter a login for webmaster');
    } elseif (preg_match( '/[\'"]/', $webmaster)) {
        $errors[] = l10n('webmaster login can\'t contain characters \' or "');
    }
    if ($admin_pass1 != $admin_pass2 || empty($admin_pass1)) {
        $errors[] = l10n('please enter your password again');
    }
    if (empty($admin_mail)) {
        $errors[] = l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
    } else {
        $error_mail_address = $services['users']->validateMailAddress(null, $admin_mail);
        if (!empty($error_mail_address)) {
            $errors[] = $error_mail_address;
        }
    }

    if ( count( $errors ) == 0 ) {
        $step = 2;
        $file_content = '<?php
$conf[\'dblayer\'] = \''.$dblayer.'\';
$conf[\'db_base\'] = \''.$dbname.'\';
$conf[\'db_user\'] = \''.$dbuser.'\';
$conf[\'db_password\'] = \''.$dbpasswd.'\';
$conf[\'db_host\'] = \''.$dbhost.'\';

$prefixeTable = \''.$prefixeTable.'\';

define(\'PHPWG_INSTALLED\', true);
define(\'PWG_CHARSET\', \'utf-8\');
define(\'DB_CHARSET\', \'utf8\');
define(\'DB_COLLATE\', \'\');';

        @umask(0111);
        // writing the configuration file
        if (!($fp = @fopen( $config_file, 'w' ))) {
            // make sure nobody can list files of _data directory
            secure_directory(PHPWG_ROOT_PATH.$conf['data_location']);

            $tmp_filename = md5(uniqid(time()));
            $fh = @fopen( PHPWG_ROOT_PATH.$conf['data_location'] . 'pwg_' . $tmp_filename, 'w' );
            @fputs($fh, $file_content, strlen($file_content));
            @fclose($fh);

            $template->assign(
                array(
                    'config_creation_failed' => true,
                    'config_url' => 'install.php?dl='.$tmp_filename,
                    'config_file_content' => $file_content,
                )
            );
        }
        @fputs($fp, $file_content, strlen($file_content));
        @fclose($fp);

        // tables creation, based on phyxo_structure.sql
        execute_sqlfile(
            PHPWG_ROOT_PATH.'install/phyxo_structure-'.$dblayer.'.sql',
            DEFAULT_PREFIX_TABLE,
            $prefixeTable,
            $dblayer
        );
        // We fill the tables with basic informations
        execute_sqlfile(
            PHPWG_ROOT_PATH.'install/config.sql',
            DEFAULT_PREFIX_TABLE,
            $prefixeTable,
            $dblayer
        );

        conf_update_param(
            'secret_key',
            'md5('.$conn->db_cast_to_text($conn::RANDOM_FUNCTION.'()').')',
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

        // fill $conf global array
        load_conf_from_db();

        // PWG_CHARSET is required for building the fs_themes array in the
        // themes class
        if (!defined('PWG_CHARSET')) {
            define('PWG_CHARSET', 'utf-8');
        }

        /**
         * Automatically activate all core themes in the "themes" directory.
         */
        $themes = new Themes($conn);
        foreach ($themes->fs_themes as $theme_id => $fs_theme) {
            if ($theme_id == 'elegant') {
                $themes->perform_action('activate', $theme_id);
            }
        }

        $insert = array('id' => 1, 'galleries_url' => PHPWG_ROOT_PATH.'galleries/');
        $conn->mass_inserts(SITES_TABLE, array_keys($insert), array($insert));

        // webmaster admin user
        $inserts = array(
            array(
                'id'           => 1,
                'username'     => $admin_name,
                'password'     => md5($admin_pass1),
                'mail_address' => $admin_mail,
            ),
            array(
                'id'           => 2,
                'username'     => 'guest',
            ),
        );
        $conn->mass_inserts(USERS_TABLE, array_keys($inserts[0]), $inserts);
        if ($dblayer=='pgsql') {
            $conn->db_query('ALTER SEQUENCE '.strtolower(USERS_TABLE).'_id_seq RESTART WITH 3');
        }

        $services['users']->createUserInfos(array(1,2), array('language' => $language));

        // Available upgrades must be ignored after a fresh installation. To
        // make PWG avoid upgrading, we must tell it upgrades have already been
        // made.
        list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
        define('CURRENT_DATE', $dbnow);
        $datas = array();
        foreach (get_available_upgrade_ids() as $upgrade_id) {
            $datas[] = array(
                'id'          => $upgrade_id,
                'applied'     => CURRENT_DATE,
                'description' => 'upgrade included in installation',
            );
        }
        $conn->mass_inserts(
            UPGRADE_TABLE,
            array_keys($datas[0]),
            $datas
        );
    }
}

//------------------------------------------------------ start template output
foreach ($languages->fs_languages as $language_code => $fs_language) {
    if ($language == $language_code) {
        $template->assign('language_selection', $language_code);
    }
    $languages_options[$language_code] = $fs_language['name'];
}
$template->assign('language_options', $languages_options);

$template->assign(
    array(
        'T_CONTENT_ENCODING' => 'utf-8',
        'RELEASE' => PHPWG_VERSION,
        'F_ACTION' => 'install.php?language=' . $language,
        'F_DB_ENGINES' => $dbengines,
        'F_DB_LAYER' => $dblayer,
        'F_DB_HOST' => $dbhost,
        'F_DB_USER' => $dbuser,
        'F_DB_NAME' => $dbname,
        'F_DB_PREFIX' => $prefixeTable,
        'F_ADMIN' => $admin_name,
        'F_ADMIN_EMAIL' => $admin_mail,
        'EMAIL' => '<span class="adminEmail">'.$admin_mail.'</span>'
    )
);

//------------------------------------------------------ errors & infos display
if ($step == 1) {
    $template->assign('install', true);
} else {
    $infos[] = l10n('Congratulations, Phyxo installation is completed');

    if (isset($error_copy)) {
        $errors[] = $error_copy;
    } else {
        session_set_save_handler('pwg_session_open',
        'pwg_session_close',
        'pwg_session_read',
        'pwg_session_write',
        'pwg_session_destroy',
        'pwg_session_gc'
        );
        if (function_exists('ini_set')) {
            ini_set('session.use_cookies', $conf['session_use_cookies']);
            ini_set('session.use_only_cookies', $conf['session_use_only_cookies']);
            ini_set('session.use_trans_sid', intval($conf['session_use_trans_sid']));
            ini_set('session.cookie_httponly', 1);
        }
        session_name($conf['session_name']);
        session_set_cookie_params(0, cookie_path());
        register_shutdown_function('session_write_close');

        $user = $services['users']->buildUser(1, true);
        $services['users']->logUser($user['id'], false);

        // email notification
        if (isset($_POST['send_password_by_mail'])) {
            include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

            $keyargs_content = array(
                get_l10n_args('Hello %s,', $admin_name),
                get_l10n_args('Welcome to your new installation of Phyxo!', ''),
                get_l10n_args('', ''),
                get_l10n_args('Here are your connection settings', ''),
                get_l10n_args('', ''),
                get_l10n_args('Link: %s', get_absolute_root_url()),
                get_l10n_args('Username: %s', $admin_name),
                get_l10n_args('Password: %s', $admin_pass1),
                get_l10n_args('Email: %s', $admin_mail)
            );

            pwg_mail(
                $admin_mail,
                array(
                    'subject' => l10n('Just another Phyxo gallery'),
                    'content' => l10n_args($keyargs_content),
                    'content_format' => 'text/plain',
                )
            );
        }
    }
}

if (count($errors) != 0) {
    $template->assign('errors', $errors);
}

if (count($infos) != 0 ) {
    $template->assign('infos', $infos);
}

//----------------------------------------------------------- html code display
$template->pparse('install');
