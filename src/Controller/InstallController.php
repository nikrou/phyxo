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

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Template\Template;
use Phyxo\Language\Languages;
use Phyxo\DBLayer\DBLayer;
use Phyxo\Conf;
use App\Repository\ConfigRepository;
use App\Repository\UpgradeRepository;
use Phyxo\Theme\Themes;
use App\Utils\UserManager;
use App\Entity\User;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Phyxo\Extension\Theme;
use App\Repository\BaseRepository;
use Phyxo\Upgrade;
use Phyxo\EntityManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Event\CoreInstalledEvent;

class InstallController extends Controller
{
    private $Steps = [
        'language' => ['label' => 'Choose language'],
        'check' => ['label' => 'Verify requirements'],
        'database' => ['label' => 'Install database'],
        'user' => ['label' => 'Create first user'],
        'success' => ['label' => 'Installation completed']
    ];

    private $eventDispatcher;
    private $languages_options;
    private $passwordEncoder;
    private $phyxoVersion;
    private $databaseConfigFile;
    private $default_language;
    private $default_theme;
    private $default_prefix = 'phyxo_';

    public function __construct(Template $template, string $defaultLanguage, string $defaultTheme, string $phyxoVersion, string $phyxoWebsite, string $databaseConfigFile,
                                UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->default_language = $defaultLanguage;
        $this->default_theme = $defaultTheme;
        $this->phyxoVersion = $phyxoVersion;
        $this->databaseConfigFile = $databaseConfigFile;
        $this->passwordEncoder = $passwordEncoder;

        $template->setTheme(new Theme(__DIR__ . '/../../admin/theme', '.'));
        $template->assign([
            'RELEASE' => $phyxoVersion,
            'PHPWG_URL' => $phyxoWebsite,
            'GALLERY_TITLE' => 'Phyxo',
            'PAGE_TITLE' => \Phyxo\Functions\Language::l10n('Installation'),
            'STEPS' => $this->Steps,
        ]);
        $template->postConstruct();
    }

    public function index(Request $request, Template $template, string $step = 'language', EventDispatcherInterface $eventDispatcher)
    {
        $tpl_params = [];

        if (is_readable($this->databaseConfigFile) && ($step !== 'success')) {
            return  $this->redirectToRoute('homepage', []);
        }

        $this->eventDispatcher = $eventDispatcher;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $languages = new Languages(null);
        $languages->setLanguagesRootPath(__DIR__ . '/../../language');
        foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
            $this->languages_options[$language_code] = $fs_language['name'];
        }

        if ($request->get('language')) {
            $language = $request->get('language');
        } elseif ($request->isMethod('POST') && $request->request->get('language')) {
            $language = $request->request->get('language');
        } else {
            $language = $this->default_language;
        }

        if (!isset($this->languages_options[$language])) {
            $language = $this->default_language;
        }

        $translations_common = \Phyxo\Functions\Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $language, 'return_vars' => true]
        );
        $translations_install = \Phyxo\Functions\Language::load_language(
            'install.lang',
            __DIR__ . '/../../',
            ['language' => $language, 'return_vars' => true]
        );

        $template->setLang(array_merge($translations_common['lang'], $translations_install['lang']));
        $template->setLangInfo(array_merge($translations_common['lang_info'], $translations_install['lang_info']));

        $stepMethod = $step . 'Step';
        $tpl_params = array_merge($tpl_params, $this->$stepMethod($request));

        if ($step !== $tpl_params['STEP']) {
            return  $this->redirectToRoute('install', ['step' => $tpl_params['STEP'], 'language' => $language]);
        }
        $tpl_params['LANGUAGE'] = $language;

        return $this->render('install.tpl', $tpl_params);
    }

    protected function languageStep(Request $request)
    {
        $tpl_params = [
            'LANGUAGES' => $this->languages_options,
            'INSTALL_ACTION' => $this->generateUrl('install', ['step' => 'language']),
        ];

        if ($request->isMethod('POST') && $request->request->get('install_language') && ($language = $request->request->get('language'))) {
            $tpl_params['STEP'] = 'check';
        } else {
            $tpl_params['STEP'] = 'language';
        }

        return $tpl_params;
    }

    protected function checkStep(Request $request)
    {
        $tpl_params = [
            'INSTALL_ACTION' => $this->generateUrl('install', ['step' => 'check']),
        ];

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
                'path' => realpath(__DIR__ . '/../../_data'),
            ],
            'local' => [
                'readable' => false,
                'writable' => false,
                'path' => realpath(__DIR__ . '/../../local'),
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

        if ($check_directories && $request->isMethod('POST') && $request->request->get('install_check')) {
            $tpl_params['STEP'] = 'database';
        } else {
            $tpl_params['STEP'] = 'check';
            $tpl_params['ROOT'] = $this->get('kernel')->getProjectDir();
            $tpl_params['READ_DIRECTORIES'] = $read_directories;
            $tpl_params['WRITE_DIRECTORIES'] = $write_directories;
        }

        return $tpl_params;
    }

    protected function databaseStep(Request $request)
    {
        $errors = [];
        $tpl_params = [
            'INSTALL_ACTION' => $this->generateUrl('install', ['step' => 'database']),
            'DB_ENGINES' => DBLayer::availableEngines(),
        ];

        $db_params = [
            'db_layer' => array_keys($tpl_params['DB_ENGINES'])[0],
            'db_host' => 'localhost',
            'db_name' => '',
            'db_user' => '',
            'db_password' => '',
            'db_prefix' => $this->default_prefix,
        ];

        if ($request->isMethod('POST')) {
            if ($request->request->get('db_layer')) {
                $db_params['db_layer'] = $request->request->get('db_layer');
            }
            if ($request->request->get('db_host')) {
                $db_params['db_host'] = $request->request->get('db_host');
            }
            if ($request->request->get('db_user')) {
                $db_params['db_user'] = $request->request->get('db_user');
            } elseif ($db_params['db_layer'] !== 'sqlite') {
                $errors[] = \Phyxo\Functions\Language::l10n('Database user is mandatory');
            }
            if ($request->request->get('db_password')) {
                $db_params['db_password'] = $request->request->get('db_password');
            } elseif ($db_params['db_layer'] !== 'sqlite') {
                $errors[] = \Phyxo\Functions\Language::l10n('Database password is mandatory');
            }
            if ($request->request->get('db_name')) {
                $db_params['db_name'] = $request->request->get('db_name');
            } elseif ($db_params['db_layer'] !== 'sqlite') {
                $errors[] = \Phyxo\Functions\Language::l10n('Database name is mandatory');
            }
            if ($request->request->get('db_prefix')) {
                $db_params['db_prefix'] = $request->request->get('db_prefix');
            }

            if (empty($errors)) {
                try {
                    $this->installDatabase($db_params);
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $tpl_params = array_merge($tpl_params, $db_params);

        if (empty($errors) && $request->isMethod('POST') && $request->request->get('install_database')) {
            $tpl_params['STEP'] = 'user';
        } else {
            $tpl_params['errors'] = $errors;
            $tpl_params['STEP'] = 'database';
        }

        return $tpl_params;
    }

    protected function userStep(Request $request)
    {
        $errors = [];
        $tpl_params = [
            'INSTALL_ACTION' => $this->generateUrl('install', ['step' => 'user']),
        ];

        if ($request->isMethod('POST')) {
            if (!$request->request->get('_username')) {
                $errors[] = \Phyxo\Functions\Language::l10n('Username is missing. Please enter the username.');
            } else {
                $db_params['username'] = $request->request->get('_username');
                $tpl_params['_username'] = $request->request->get('_username');
            }
            if (!$request->request->get('_password')) {
                $errors[] = \Phyxo\Functions\Language::l10n('Password is missing. Please enter the password.');
            } elseif (!$request->request->get('_password_confirm')) {
                $errors[] = \Phyxo\Functions\Language::l10n('Password confirmation is missing. Please confirm the chosen password.');
            } elseif ($request->request->get('_password') !== $request->request->get('_password_confirm')) {
                $errors[] = \Phyxo\Functions\Language::l10n('The passwords do not match');
            } else {
                $db_params['password'] = $request->request->get('_password');
            }
            if (!$request->request->get('_mail_address')) {
                $errors[] = \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
            } else {
                if (filter_var($request->request->get('_mail_address'), FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
                } else {
                    $db_params['mail_address'] = $request->request->get('_mail_address');
                    $tpl_params['_mail_address'] = $request->request->get('_mail_address');
                }
            }

            if (empty($errors)) {
                $conn = DBLayer::initFromConfigFile($this->get('kernel')->getProjectDir() . '/local/config/database.inc.tmp.php');
                $em = new EntityManager($conn);
                $conf = new Conf($conn);
                $conf->loadFromFile($this->get('kernel')->getProjectDir() . '/include/config_default.inc.php');
                $conf->loadFromFile($this->get('kernel')->getProjectDir() . '/local/config/config.inc.php');

                $webmaster = new User();
                $webmaster->setId($conf['webmaster_id']);
                $webmaster->setUsername($db_params['username']);
                $webmaster->setMailAddress($db_params['mail_address']);
                $webmaster->setPassword($this->passwordEncoder->encodePassword($webmaster, $db_params['password']));
                $webmaster->setStatus(User::STATUS_WEBMASTER);

                $guest = new User();
                $guest->setId($conf['guest_id']);
                $guest->setUsername('guest');
                $guest->setStatus(User::STATUS_GUEST);
                $user_manager = new UserManager($em, $conf);

                try {
                    $user_manager->register($webmaster);
                    $user_manager->register($guest);

                    rename($this->get('kernel')->getProjectDir() . '/local/config/database.inc.tmp.php', $this->get('kernel')->getProjectDir() . '/local/config/database.inc.php');
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if (empty($errors) && $request->isMethod('POST') && $request->request->get('install_user')) {
            $tpl_params['STEP'] = 'success';
        } else {
            $tpl_params['STEP'] = 'user';
            $tpl_params['errors'] = $errors;
        }

        return $tpl_params;
    }

    public function successStep(Request $request)
    {
        $tpl_params['STEP'] = 'success';
        $tpl_params['HOMEPAGE_URL'] = $this->generateUrl('homepage');

        $request->attributes->set('core.installed', true);

        return $tpl_params;
    }

    protected function installDatabase(array $db_params = [])
    {
        $conn = DBLayer::init($db_params['db_layer'], $db_params['db_host'], $db_params['db_user'], $db_params['db_password'], $db_params['db_name'], $db_params['db_prefix']);

        // load configuration
        $conf = new Conf($conn);
        $conf->loadFromFile($this->get('kernel')->getProjectDir() . '/include/config_default.inc.php');
        $conf->loadFromFile($this->get('kernel')->getProjectDir() . '/local/config/config.inc.php');

        // tables creation, based on phyxo_structure.sql
        $conn->executeSqlFile(
            $this->get('kernel')->getProjectDir() . '/install/phyxo_structure-' . $db_params['db_layer'] . '.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );
        // We fill the tables with basic informations
        $conn->executeSqlFile(
            $this->get('kernel')->getProjectDir() . '/install/config.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );

        (new ConfigRepository($conn))->addParam(
            'secret_key',
            md5(random_bytes(15)),
            '\'a secret key specific to the gallery for internal use\')'
        );

        $conf['phyxo_db_version'] = \Phyxo\Functions\Utils::get_branch_from_version($this->phyxoVersion);
        $conf['gallery_title'] = \Phyxo\Functions\Language::l10n('Just another Phyxo gallery');
        $conf['page_banner'] = '<h1>%gallery_title%</h1><p>' . \Phyxo\Functions\Language::l10n('Welcome to my photo gallery') . '</p>';

        $languages = new Languages($conn);
        $languages->setLanguagesRootPath($this->get('kernel')->getProjectDir() . '/language');
        foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
            $languages->performAction('activate', $language_code);
        }

        $conf->loadFromDB();

        $themes = new Themes($conn);
        $themes->setThemesRootPath($this->get('kernel')->getProjectDir() . '/themes');
        foreach ($themes->getFsThemes() as $theme_id => $fs_theme) {
            if ($theme_id === $this->default_theme) {
                $themes->performAction('activate', $theme_id);
            }
        }

        // Available upgrades must be ignored after a fresh installation.
        // To make Phyxo avoid upgrading, we must tell it upgrades have already been made.
        list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
        $datas = [];

        $upgrade = new Upgrade(new EntityManager($conn), $conf);
        foreach ($upgrade->getAvailableUpgradeIds($this->get('kernel')->getProjectDir()) as $upgrade_id) {
            $datas[] = [
                'id' => $upgrade_id,
                'applied' => $dbnow,
                'description' => 'upgrade included in installation',
            ];
        }
        (new UpgradeRepository($conn))->massInserts(array_keys($datas[0]), $datas);

        $file_content = '<?php' . "\n";
        $file_content .= '$conf[\'dblayer\'] = \'' . $db_params['db_layer'] . "';\n";
        $file_content .= '$conf[\'db_base\'] = \'' . $db_params['db_name'] . "';\n";
        if ($db_params['db_layer'] !== 'sqlite') {
            $file_content .= '$conf[\'db_host\'] = \'' . $db_params['db_host'] . "';\n";
            $file_content .= '$conf[\'db_user\'] = \'' . $db_params['db_user'] . "';\n";
            $file_content .= '$conf[\'db_password\'] = \'' . $db_params['db_password'] . "';\n";
        }
        $file_content .= '$conf[\'db_prefix\'] = \'' . $db_params['db_prefix'] . "';\n\n";

        file_put_contents($this->get('kernel')->getProjectDir() . '/local/config/database.inc.tmp.php', $file_content);
        if (!is_readable($this->get('kernel')->getProjectDir() . '/local/config/database.inc.tmp.php')) {
            throw new \Exception(\Phyxo\Functions\Language::l10n('All tables in database have been created'));
        }
    }
}
