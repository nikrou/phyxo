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

use Symfony\Component\HttpFoundation\Request;
use Phyxo\Language\Languages;
use Phyxo\DBLayer\DBLayer;
use Phyxo\Conf;
use App\Repository\ConfigRepository;
use App\Repository\UpgradeRepository;
use App\Utils\UserManager;
use App\Entity\User;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Repository\ThemeRepository;
use Phyxo\Upgrade;
use Phyxo\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

class InstallController extends AbstractController
{
    private $Steps = [
        'language' => ['label' => 'Choose language'],
        'check' => ['label' => 'Verify requirements'],
        'database' => ['label' => 'Install database'],
        'user' => ['label' => 'Create first user'],
        'success' => ['label' => 'Installation completed']
    ];

    private $languages_options;
    private $passwordEncoder;
    private $phyxoVersion;
    private $default_language;
    private $default_theme;
    private $translationsDir;
    private $default_prefix = 'phyxo_';
    private $translator;
    private $rootProjectDir;
    private $databaseConfigFile;

    public function __construct(string $translationsDir, string $defaultLanguage, string $defaultTheme, string $phyxoVersion,
          string $databaseConfigFile, UserPasswordEncoderInterface $passwordEncoder, TranslatorInterface $translator, string $rootProjectDir)
    {
        $this->translationsDir = $translationsDir;
        $this->databaseConfigFile = $databaseConfigFile;
        $this->default_language = $defaultLanguage;
        $this->default_theme = $defaultTheme;
        $this->phyxoVersion = $phyxoVersion;
        $this->passwordEncoder = $passwordEncoder;
        $this->translator = $translator;
        $this->rootProjectDir = $rootProjectDir;
    }

    public function index(Request $request, string $step = 'language')
    {
        $tpl_params = [];
        $tpl_params['STEPS'] = $this->Steps;

        if (is_readable($this->databaseConfigFile) && ($step !== 'success')) {
            return  $this->redirectToRoute('homepage', []);
        }

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $languages = new Languages(null);
        $languages->setRootPath($this->translationsDir);
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
        $request->getSession()->set('_locale', $language);

        if (!isset($this->languages_options[$language])) {
            $language = $this->default_language;
        }

        $stepMethod = $step . 'Step';
        $tpl_params = array_merge($tpl_params, $this->$stepMethod($request));

        if ($step !== $tpl_params['STEP']) {
            return  $this->redirectToRoute('install', ['step' => $tpl_params['STEP'], 'language' => $language]);
        }
        $tpl_params['lang_info'] = ['code' => preg_replace('`_.*`', '', $language), 'direction' => 'ltr']; // @TODO: retrieve from common place
        $tpl_params['LANGUAGE'] = $language;
        $tpl_params['domain'] = 'install';

        return $this->render('install.html.twig', $tpl_params);
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
                'path' => $this->translationsDir,
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
            $tpl_params['ROOT'] = $this->rootProjectDir;
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
                $errors[] = $this->translator->trans('Database user is mandatory', [], 'install');
            }
            if ($request->request->get('db_password')) {
                $db_params['db_password'] = $request->request->get('db_password');
            } elseif ($db_params['db_layer'] !== 'sqlite') {
                $errors[] = $this->translator->trans('Database password is mandatory', [], 'install');
            }
            if ($request->request->get('db_name')) {
                $db_params['db_name'] = $request->request->get('db_name');
            } elseif ($db_params['db_layer'] !== 'sqlite') {
                $errors[] = $this->translator->trans('Database name is mandatory', [], 'install');
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
                $errors[] = $this->translator->trans('Username is missing. Please enter the username.', [], 'install');
            } else {
                $db_params['username'] = $request->request->get('_username');
                $tpl_params['_username'] = $request->request->get('_username');
            }
            if (!$request->request->get('_password')) {
                $errors[] = $this->translator->trans('Password is missing. Please enter the password.', [], 'install');
            } elseif (!$request->request->get('_password_confirm')) {
                $errors[] = $this->translator->trans('Password confirmation is missing. Please confirm the chosen password.', [], 'install');
            } elseif ($request->request->get('_password') !== $request->request->get('_password_confirm')) {
                $errors[] = $this->translator->trans('The passwords do not match', [], 'install');
            } else {
                $db_params['password'] = $request->request->get('_password');
            }
            if (!$request->request->get('_mail_address')) {
                $errors[] = $this->translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)', [], 'install');
            } else {
                if (filter_var($request->request->get('_mail_address'), FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = $this->translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)', [], 'install');
                } else {
                    $db_params['mail_address'] = $request->request->get('_mail_address');
                    $tpl_params['_mail_address'] = $request->request->get('_mail_address');
                }
            }

            if (empty($errors)) {
                $conn = DBLayer::initFromConfigFile($this->databaseConfigFile . '.tmp');
                $em = new EntityManager($conn);

                $conf = new Conf($conn);
                $conf->loadFromFile($this->rootProjectDir . '/include/config_default.inc.php');
                $conf->loadFromFile($this->rootProjectDir . '/local/config/config.inc.php');

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

                    rename($this->databaseConfigFile . '.tmp', $this->databaseConfigFile);

                    $env_file_content = 'APP_ENV=prod' . "\n";
                    $env_file_content .= 'APP_SECRET=' . hash('sha256', openssl_random_pseudo_bytes(50)) . "\n";
                    file_put_contents($this->rootProjectDir . '/.env', $env_file_content);
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
        $em = new EntityManager($conn);

        $em = new EntityManager($conn);

        // load configuration
        $conf = new Conf($conn);
        $conf->loadFromFile($this->rootProjectDir . '/include/config_default.inc.php');
        $conf->loadFromFile($this->rootProjectDir . '/local/config/config.inc.php');

        // tables creation, based on phyxo_structure.sql
        $conn->executeSqlFile(
            $this->rootProjectDir . '/install/phyxo_structure-' . $db_params['db_layer'] . '.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );
        // We fill the tables with basic informations
        $conn->executeSqlFile(
            $this->rootProjectDir . '/install/config.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );

        (new ConfigRepository($conn))->addParam(
            'secret_key',
            md5(random_bytes(15)),
            '\'a secret key specific to the gallery for internal use\')'
        );

        $conf['phyxo_db_version'] = \Phyxo\Functions\Utils::get_branch_from_version($this->phyxoVersion);
        $conf['gallery_title'] = $this->translator->trans('Just another Phyxo gallery', [], 'install');
        $conf['page_banner'] = '<h1>%gallery_title%</h1><p>' . $this->translator->trans('Welcome to my photo gallery', [], 'install') . '</p>';

        $languages = new Languages($em, null);
        $languages->setRootPath($this->translationsDir);
        foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
            $languages->performAction('activate', $language_code);
        }

        // activate default theme
        (new ThemeRepository($conn))->addTheme($this->default_theme, $this->phyxoVersion, $this->default_theme);

        $conf->loadFromDB();

        // Available upgrades must be ignored after a fresh installation.
        // To make Phyxo avoid upgrading, we must tell it upgrades have already been made.
        list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
        $datas = [];

        $upgrade = new Upgrade(new EntityManager($conn), $conf);
        foreach ($upgrade->getAvailableUpgradeIds($this->rootProjectDir) as $upgrade_id) {
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

        file_put_contents($this->databaseConfigFile . '.tmp', $file_content);
        if (!is_readable($this->databaseConfigFile . '.tmp')) {
            throw new \Exception($this->translator->trans('All tables in database have been created', [], 'install'));
        }
    }
}
