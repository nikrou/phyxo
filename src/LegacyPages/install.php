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

//----------------------------------------------------------- include
define('PHPWG_ROOT_PATH', '../../');
define('IN_ADMIN', true);

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

use Phyxo\DBLayer\DBLayer;
use Phyxo\Conf;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;
use Phyxo\Template\Template;
use Phyxo\Session\SessionDbHandler;
use App\Repository\SiteRepository;
use App\Repository\ConfigRepository;
use App\Repository\UpgradeRepository;
use App\Repository\UserRepository;
use Phyxo\Model\Repository\Users;

$infos = [];
$errors = [];
$warnings = [];

if (\Phyxo\Functions\Utils::phyxoInstalled($container->getParameter('database_config_file'))) {
    header('Location: ' . \Phyxo\Functions\URL::get_root_url());
    exit();
}

define('DEFAULT_PREFIX_TABLE', 'phyxo_');
defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
}
include(PHPWG_ROOT_PATH . 'include/constants.php');

// all available engines
$dbengines = DBLayer::availableEngines();

$Steps = [
    'language' => ['label' => 'Choose language', 'done' => false],
    'check' => ['label' => 'Verify requirements', 'done' => false],
    'database' => ['label' => 'Install database', 'done' => false],
    'user' => ['label' => 'Create first user', 'done' => false],
];

$action = 'install.php';

if (!isset($step)) {
    $step = array_keys($Steps)[0];
}

$languages = new Languages(null, 'utf-8');
if (isset($_POST['language'])) {
    if (in_array($_POST['language'], array_keys($languages->getFsLanguages()))) {
        $language = $_POST['language'];
    } else {
        $language = PHPWG_DEFAULT_LANGUAGE;
    }
    $Steps['language']['done'] = true;
    $step = 'check';
} elseif (isset($_GET['language'])) {
    if (in_array($_GET['language'], array_keys($languages->getFsLanguages()))) {
        $language = $_GET['language'];
    } else {
        $language = PHPWG_DEFAULT_LANGUAGE;
    }
    $Steps['language']['done'] = true;
    $step = 'check';
} else {
    $language = 'en_GB';
    // Try to get browser language
    foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) && substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2) === substr($language_code, 0, 2)) {
            $language = $language_code;
            break;
        }
    }
}
$action .= '?language=' . $language;

\Phyxo\Functions\Language::load_language('common.lang', '', ['language' => $language, 'target_charset' => 'utf-8']);
\Phyxo\Functions\Language::load_language('admin.lang', '', ['language' => $language, 'target_charset' => 'utf-8']);
\Phyxo\Functions\Language::load_language('install.lang', '', ['language' => $language, 'target_charset' => 'utf-8']);

$template = new Template(['conf' => $conf, 'lang' => $lang, 'lang_info' => $lang_info]);
$template->postConstruct();
$template->setCompileDir($container->getParameter('kernel.cache_dir') . '/smarty');
$template->set_theme(PHPWG_ROOT_PATH . 'admin/theme', '.');
$template->set_filename('install', 'install.tpl');

if ($step === 'language') {
    foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
        $languages_options[$language_code] = $fs_language['name'];
    }
    $template->assign('LANGUAGES', $languages_options);
}

if ($step === 'check') {
    $read_directories = [
        'languages' => [
            'readable' => false,
            'writable' => false,
            'path' => realpath(__DIR__ . '/../../language'),
        ],
        'plugins' => [
            'readable' => false,
            'writable' => false,
            'path' => realpath(__DIR__ . '/../../plugins'),
        ],
        'themes' => [
            'readable' => false,
            'writable' => false,
            'path' => realpath(__DIR__ . '/../../themes'),
        ],
    ];

    $write_directories = [
        '_data' => [
            'readable' => false,
            'writable' => false,
            'path' => realpath(__DIR__ . '/../../' . $conf['data_location']),
        ],
        'local' => [
            'readable' => false,
            'writable' => false,
            'path' => realpath(__DIR__ . '/../../' . PWG_LOCAL_DIR),
        ],
        'upload' => [
            'readable' => false,
            'writable' => false,
            'path' => realpath(__DIR__ . '/../../upload'),
        ],
        'var' => [
            'readable' => false,
            'writable' => false,
            'path' => realpath(__DIR__ . '/../../var'),
        ],
    ];

    $check_directories = true;
    foreach ($read_directories as &$directory) {
        if (is_readable($directory['path'])) {
            $directory['readable'] = true;
        } else {
            $check_directories = false;
        }
        if (is_writable($directory['path'])) {
            $directory['writable'] = true;
        }
    }
    foreach ($write_directories as &$directory) {
        if (is_readable($directory['path'])) {
            $directory['readable'] = true;
        } else {
            $check_directories = false;
        }

        if (is_writable($directory['path'])) {
            $directory['writable'] = true;
        } else {
            $check_directories = false;
        }
    }

    if ($check_directories) {
        $Steps['check']['done'] = true;
        $action .= '&check=done';
    }

    $template->assign('ROOT', realpath(__DIR__ . '/../..'));
    $template->assign('READ_DIRECTORIES', $read_directories);
    $template->assign('WRITE_DIRECTORIES', $write_directories);
}

if (!empty($_GET['check'])) {
    $step = 'database';
}

if ($step === 'database' && is_readable(PHPWG_ROOT_PATH . 'local/config/database.inc.tmp.php')) {
    $Steps['database']['done'] = true;
    $step = 'user';
}

if ($step === 'database') {
    $dbhost = (!empty($_POST['dbhost'])) ? $_POST['dbhost'] : 'localhost';
    $dbuser = (!empty($_POST['dbuser'])) ? $_POST['dbuser'] : '';
    $dbpasswd = (!empty($_POST['dbpasswd'])) ? $_POST['dbpasswd'] : '';
    $dbname = (!empty($_POST['dbname'])) ? $_POST['dbname'] : '';
    $dblayer = (!empty($_POST['dblayer']) && !empty($dbengines[$_POST['dblayer']])) ? $_POST['dblayer'] : 'mysql';
    $prefixeTable = (!empty($_POST['prefix'])) ? $_POST['prefix'] : DEFAULT_PREFIX_TABLE;

    if (isset($_POST['install_database'])) {
        if (empty($dbuser)) {
            $errors[] = \Phyxo\Functions\Language::l10n('Database user is mandatory');
        }
        if (empty($dbpasswd)) {
            $errors[] = \Phyxo\Functions\Language::l10n('Database password is mandatory');
        }
        if (empty($dbname)) {
            $errors[] = \Phyxo\Functions\Language::l10n('Database name is mandatory');
        }
    }

    $template->assign([
        'F_DB_LAYER' => $dblayer,
        'F_DB_HOST' => $dbhost,
        'F_DB_USER' => $dbuser,
        'F_DB_NAME' => $dbname,
        'F_DB_PASSWORD_MISSING' => empty($dbpasswd),
        'F_DB_PREFIX' => $prefixeTable,
        'F_DB_ENGINES' => $dbengines
    ]);

    $field_missing = false;
    if (empty($dbhost) || empty($dbname) || empty($dblayer)) {
        $field_missing = true;
    }
    if (!empty($dblayer) && $dblayer !== 'sqlite') {
        if (empty($dbuser) || empty($dbpasswd) || empty($dbname)) {
            $field_missing = true;
        }
    }

    if (!$field_missing) {
        try {
            $conn = DBLayer::init($dblayer, $dbhost, $dbuser, $dbpasswd, $dbname);

            $conf = new Conf($conn);
            $conf->loadFromFile(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
            $conf->loadFromFile(PHPWG_ROOT_PATH . 'local/config/config.inc.php');

            // tables creation, based on phyxo_structure.sql
            $conn->executeSqlFile(
                PHPWG_ROOT_PATH . 'install/phyxo_structure-' . $dblayer . '.sql',
                DEFAULT_PREFIX_TABLE,
                $prefixeTable
            );
           // We fill the tables with basic informations
            $conn->executeSqlFile(
                PHPWG_ROOT_PATH . 'install/config.sql',
                DEFAULT_PREFIX_TABLE,
                $prefixeTable
            );

            // load configuration
            $conf = new Conf($conn);
            $conf->loadFromFile(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
            $conf->loadFromFile(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
            $conf['dblayer'] = $dblayer;

            (new ConfigRepository($conn))->addParam(
                'secret_key',
                md5(openssl_random_pseudo_bytes(15)),
                '\'a secret key specific to the gallery for internal use\')'
            );

            $conf['phyxo_db_version'] = \Phyxo\Functions\Utils::get_branch_from_version(PHPWG_VERSION);
            $conf['gallery_title'] = \Phyxo\Functions\Language::l10n('Just another Phyxo gallery');
            $conf['page_banner'] = '<h1>%gallery_title%</h1>' . "\n\n<p>" . \Phyxo\Functions\Language::l10n('Welcome to my photo gallery') . '</p>';

            $languages->setConnection($conn);
            foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
                $languages->performAction('activate', $language_code);
            }

            $conf->loadFromDB();

            $themes = new Themes($conn);
            foreach ($themes->getFsThemes() as $theme_id => $fs_theme) {
                if ($theme_id == 'elegant') {
                    $themes->performAction('activate', $theme_id);
                }
            }

            // Available upgrades must be ignored after a fresh installation.
            // To make Phyxo avoid upgrading, we must tell it upgrades have already been made.
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
            (new UpgradeRepository($conn))->massInserts(array_keys($datas[0]), $datas);

            $file_content = '<?php' . "\n";
            $file_content .= '$conf[\'dblayer\'] = \'' . $dblayer . "';\n";
            $file_content .= '$conf[\'db_base\'] = \'' . $dbname . "';\n";
            $file_content .= '$conf[\'db_user\'] = \'' . $dbuser . "';\n";
            $file_content .= '$conf[\'db_password\'] = \'' . $dbpasswd . "';\n";
            $file_content .= '$conf[\'db_host\'] = \'' . $dbhost . "';\n\n";

            $file_content .= '$prefixeTable = \'' . $prefixeTable . "';\n\n";

            $file_content .= 'define(\'PHPWG_INSTALLED\', true);' . "\n";
            $file_content .= 'define(\'PWG_CHARSET\', \'utf-8\');' . "\n";
            $file_content .= 'define(\'DB_CHARSET\', \'utf8\');' . "\n";
            $file_content .= 'define(\'DB_COLLATE\', \'\');' . "\n";

            file_put_contents(PHPWG_ROOT_PATH . 'local/config/database.inc.tmp.php', $file_content);

            if (is_readable(PHPWG_ROOT_PATH . 'local/config/database.inc.tmp.php')) {
                $Steps['database']['done'] = true;
                $step = 'user';
                $action .= '&database=done';
                $infos[] = \Phyxo\Functions\Language::l10n('All tables in database have been created');
            } else {
                $errors[] = \Phyxo\Functions\Language::l10n('All tables in database have been created');
            }
        } catch (\Exception $e) {
            $errors[] = \Phyxo\Functions\Language::l10n($e->getMessage());
        }
    }
}

if (!empty($_GET['database'])) {
    $step = 'user';
}

if ($step === 'user') {
    $admin_name = (!empty($_POST['admin_name'])) ? $_POST['admin_name'] : '';
    $admin_pass1 = (!empty($_POST['admin_pass1'])) ? $_POST['admin_pass1'] : '';
    $admin_pass2 = (!empty($_POST['admin_pass2'])) ? $_POST['admin_pass2'] : '';
    $admin_mail = (!empty($_POST['admin_mail'])) ? $_POST['admin_mail'] : '';

    if (isset($_POST['create_user'])) {
        $webmaster = trim(preg_replace('/\s{2,}/', ' ', $admin_name));
        if (empty($webmaster)) {
            $errors[] = \Phyxo\Functions\Language::l10n('enter a login for webmaster');
        } elseif (preg_match('/[\'"]/', $webmaster)) {
            $errors[] = \Phyxo\Functions\Language::l10n('webmaster login can\'t contain characters \' or "');
        }
        if ($admin_pass1 != $admin_pass2 || empty($admin_pass1)) {
            $errors[] = \Phyxo\Functions\Language::l10n('please enter your password again');
        }
        if (empty($admin_mail)) {
            $errors[] = \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
        } else {
            if (!\Phyxo\Functions\Utils::email_check_format($admin_mail)) {
                $errors[] = \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
            }
        }
    }

    if (!empty($admin_name) && !empty($admin_pass1) && !empty($admin_pass2) && $admin_pass1 === $admin_pass2) {
        try {
            $conn = DBLayer::initFromConfigFile(PHPWG_ROOT_PATH . 'local/config/database.inc.tmp.php');
            $conf = new Conf($conn);
            $conf->loadFromFile(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
            $conf->loadFromFile(PHPWG_ROOT_PATH . 'local/config/config.inc.php');

            $user = [];
            $cache = [];
            $services = [];
            $services['users'] = new Users($conn, $conf, $user, $cache);

            // webmaster admin user
            $inserts = [
                [
                    'id' => 1,
                    'username' => $admin_name,
                    'password' => md5($admin_pass1),
                    'mail_address' => $admin_mail,
                ],
                [
                    'id' => 2,
                    'username' => 'guest',
                ],
            ];
            (new UserRepository($conn))->massInserts(array_keys($inserts[0]), $inserts);
            if ($conn->getLayer() === 'pgsql') {
                $conn->db_query('ALTER SEQUENCE ' . $conn->getPrefix() . strtolower(USERS_TABLE) . '_id_seq RESTART WITH 3');
            }

            $services['users']->createUserInfos([1, 2], ['language' => $language]);
            $Steps['user']['done'] = true;
            $step = 'success';

            rename(PHPWG_ROOT_PATH . 'local/config/database.inc.tmp.php', PHPWG_ROOT_PATH . 'local/config/database.inc.php');
            $infos[] = \Phyxo\Functions\Language::l10n('Congratulations, Phyxo installation is completed');
        } catch (\Exception $e) {
            $errors[] = \Phyxo\Functions\Language::l10n($e->getMessage());
        }
    }

    $template->assign([
        'F_ADMIN' => $admin_name,
        'F_ADMIN_EMAIL' => $admin_mail,
    ]);
}

$template->assign(
    [
        'GALLERY_TITLE' => 'Phyxo',
        'PAGE_TITLE' => \Phyxo\Functions\Language::l10n('Installation'),
        'T_CONTENT_ENCODING' => 'utf-8',
        'RELEASE' => PHPWG_VERSION,
        'F_ACTION' => $action,
        'LANGUAGE' => $language,
        'STEPS' => $Steps,
        'STEP' => $step,
        'errors' => $errors,
        'infos' => $infos,
        'warnings' => $warnings,
    ]
);

$template->parse('install');
