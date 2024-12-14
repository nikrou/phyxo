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

use Exception;
use Doctrine\DBAL\Configuration;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Language\Languages;
use App\Entity\User;
use App\Install\PhyxoInstaller;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InstallController extends AbstractController
{
    /** @var array<string, array{label: string}> */
    private array $Steps = [
        'language' => ['label' => 'Choose language'],
        'check' => ['label' => 'Verify requirements'],
        'database' => ['label' => 'Install database'],
        'user' => ['label' => 'Create first user'],
        'success' => ['label' => 'Installation completed']
    ];

    /** @var array<string, string> */
    private ?array $languages_options = null;
    private string $localEnvFile = '';

    public function __construct(
        private readonly string $translationsDir,
        private readonly string $defaultLanguage,
        private readonly string $defaultTheme,
        private readonly PhyxoInstaller $phyxoInstaller,
        private readonly string $mediaCacheDir,
        private readonly string $themesDir,
        private readonly string $pluginsDir,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $uploadDir,
        private readonly TranslatorInterface $translator,
        private readonly string $rootProjectDir,
        private readonly string $varDir,
        private readonly string $localDir,
        private readonly string $prefix
    ) {
        $this->localEnvFile = sprintf('%s/.env.local', $this->rootProjectDir);
    }

    public function index(Request $request, string $step = 'language'): Response
    {
        $tpl_params = [];
        $tpl_params['STEPS'] = $this->Steps;

        if (is_readable($this->localEnvFile) && ($step !== 'success')) {
            return $this->redirectToRoute('homepage', []);
        }

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
            $language = $this->defaultLanguage;
        }
        $request->getSession()->set('_locale', $language);

        if (!isset($this->languages_options[$language])) {
            $language = $this->defaultLanguage;
        }

        $stepMethod = $step . 'Step';
        $tpl_params = array_merge($tpl_params, $this->$stepMethod($request));

        if ($step !== $tpl_params['STEP']) {
            return $this->redirectToRoute('install', ['step' => $tpl_params['STEP'], 'language' => $language]);
        }
        $tpl_params['lang_info'] = ['code' => preg_replace('`_.*`', '', (string) $language), 'direction' => 'ltr']; // @TODO: retrieve from common place
        $tpl_params['LANGUAGE'] = $language;
        $tpl_params['domain'] = 'install';

        return $this->render('install.html.twig', $tpl_params);
    }

    /**
     * @return array<string, array<string, string>|string>
     */
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

    /**
     * @return array<string, array<string, array<string, bool|string>>|string>
     */
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
                'path' => $this->pluginsDir,
            ],
            'themes' => [
                'readable' => false,
                'writable' => false,
                'path' => $this->themesDir,
            ],
        ];

        $write_directories = [
            'media' => [
                'readable' => false,
                'writable' => false,
                'path' => $this->mediaCacheDir,
            ],
            'local' => [
                'readable' => false,
                'writable' => false,
                'path' => $this->localDir,
            ],
            'upload' => [
                'readable' => false,
                'writable' => false,
                'path' => $this->uploadDir,
            ],
            'var' => [
                'readable' => false,
                'writable' => false,
                'path' => $this->varDir,
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

    /**
     * @return array<string, array<string, string>|string>
     */
    protected function databaseStep(Request $request)
    {
        $errors = [];
        $tpl_params = [
            'INSTALL_ACTION' => $this->generateUrl('install', ['step' => 'database']),
            'DB_ENGINES' => $this->phyxoInstaller->availableEngines(),
        ];

        $db_params = [
            'db_layer' => array_keys($tpl_params['DB_ENGINES'])[0],
            'db_host' => 'localhost',
            'db_name' => '',
            'db_user' => '',
            'db_password' => '',
            'db_prefix' => $this->prefix,
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

            if ($errors === []) {
                try {
                    $this->phyxoInstaller->installDatabase($db_params);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $tpl_params = array_merge($tpl_params, $db_params);

        if ($errors === [] && $request->isMethod('POST') && $request->request->get('install_database')) {
            $tpl_params['STEP'] = 'user';
        } else {
            if ($errors !== []) {
                $tpl_params['errors'] = $errors;
            }
            $tpl_params['STEP'] = 'database';
        }

        return $tpl_params;
    }

    /**
     * @return array<string, array<int, string>|float|int|string|bool>
     */
    protected function userStep(Request $request)
    {
        $errors = [];
        $db_params = ['username' => '', 'password' => '', 'mail_address' => ''];
        $tpl_params = [
            '_username' => '',
            '_mail_address' => '',
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
            } elseif (filter_var($request->request->get('_mail_address'), FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = $this->translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)', [], 'install');
            } else {
                $db_params['mail_address'] = $request->request->get('_mail_address');
                $tpl_params['_mail_address'] = $request->request->get('_mail_address');
            }

            if ($errors === []) {
                $envParams = (new Dotenv())->parse(file_get_contents($this->localEnvFile . '.tmp'));
                $_params = parse_url((string) $envParams['DATABASE_URL']);
                $connectionParams = [
                    'dsn' => $envParams['DATABASE_URL'],
                    'dbname' => substr($_params['path'], 1),
                    'user' => $_params['user'],
                    'password' => $_params['pass'],
                    'host' => $_params['host'],
                    'driver' => 'pdo_' . $_params['scheme'],
                    'path' => ''
                ];
                unset($_params);

                if ($connectionParams['driver'] === 'pdo_sqlite') {
                    $connectionParams['path'] = sprintf('%s/db/%s', $this->rootProjectDir, $connectionParams['dbname']);
                }

                $databasePrefix = $envParams['DATABASE_PREFIX'];

                $config = new Configuration();
                $conn = DriverManager::getConnection($connectionParams, $config);

                $now = new DateTime();
                $raw_query_user = 'INSERT INTO phyxo_users (username, mail_address, password) VALUES(:username, :mail_address, :password)';
                $raw_query_user = str_replace($this->prefix, $databasePrefix, $raw_query_user);

                $raw_query_user_infos = 'INSERT INTO phyxo_user_infos (user_id, status, nb_image_page, language, expand, show_nb_comments, show_nb_hits, recent_period,';
                $raw_query_user_infos .= ' theme, enabled_high, level, registration_date)';
                $raw_query_user_infos .= ' VALUES(:user_id, :status, :nb_image_page, :language, :expand, :show_nb_comments, :show_nb_hits, :recent_period,';
                $raw_query_user_infos .= ' :theme, :enabled_high, :level, :registration_date)';
                $raw_query_user_infos = str_replace($this->prefix, $databasePrefix, $raw_query_user_infos);

                $statement = $conn->prepare($raw_query_user);
                $statement->bindValue('username', $db_params['username']);
                $statement->bindValue('mail_address', $db_params['mail_address']);
                $statement->bindValue('password', $this->passwordHasher->hashPassword(new User(), $db_params['password']));
                $statement->executeQuery();
                $user_id = $conn->lastInsertId();

                $statement = $conn->prepare($raw_query_user_infos);
                $statement->bindValue('user_id', $user_id);
                $statement->bindValue('status', User::STATUS_WEBMASTER);
                $statement->bindValue('nb_image_page', 15);
                $statement->bindValue('language', $request->get('language'));
                $statement->bindValue('expand', 0);
                $statement->bindValue('show_nb_comments', 0);
                $statement->bindValue('show_nb_hits', 0);
                $statement->bindValue('recent_period', 7);
                $statement->bindValue('theme', $this->defaultTheme);
                $statement->bindValue('enabled_high', 1);
                $statement->bindValue('level', 10); // @FIX: find a way to only inject that param instead of conf ; max($this->conf['available_permission_levels']);
                $statement->bindValue('registration_date', $now->format('Y-m-d H:m:i'));
                $statement->executeQuery();

                $statement = $conn->prepare($raw_query_user);
                $statement->bindValue('username', 'guest');
                $statement->bindValue('mail_address', null);
                $statement->bindValue('password', null);
                $statement->executeQuery();
                $user_id = $conn->lastInsertId();

                $statement = $conn->prepare($raw_query_user_infos);
                $statement->bindValue('user_id', $user_id);
                $statement->bindValue('status', User::STATUS_GUEST);
                $statement->bindValue('nb_image_page', 15);
                $statement->bindValue('language', $request->get('language'));
                $statement->bindValue('expand', 0);
                $statement->bindValue('show_nb_comments', 0);
                $statement->bindValue('show_nb_hits', 0);
                $statement->bindValue('recent_period', 7);
                $statement->bindValue('theme', $this->defaultTheme);
                $statement->bindValue('enabled_high', 1);
                $statement->bindValue('level', 0);
                $statement->bindValue('registration_date', $now->format('Y-m-d H:m:i'));
                $statement->executeQuery();

                try {
                    $env_file_content = 'APP_ENV=prod' . "\n";
                    $env_file_content .= 'APP_SECRET=' . hash('sha256', openssl_random_pseudo_bytes(50)) . "\n";
                    file_put_contents($this->localEnvFile . '.tmp', $env_file_content, FILE_APPEND);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if ($errors === [] && $request->isMethod('POST') && $request->request->get('install_user')) {
            $tpl_params['STEP'] = 'success';
        } else {
            $tpl_params['STEP'] = 'user';
            if ($errors !== []) {
                $tpl_params['errors'] = $errors;
            }
        }

        return $tpl_params;
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    public function successStep(Request $request)
    {
        $tpl_params = [];
        rename($this->localEnvFile . '.tmp', $this->localEnvFile);

        $tpl_params['STEP'] = 'success';
        $tpl_params['HOMEPAGE_URL'] = $this->generateUrl('homepage');

        $request->attributes->set('core.installed', true);

        return $tpl_params;
    }
}
