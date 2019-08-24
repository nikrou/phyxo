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

use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Template\Template;
use Phyxo\Template\AdminTemplate;
use Phyxo\Conf;
use App\Entity\User;
use App\Utils\UserManager;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use App\Security\LoginFormAuthenticator;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Phyxo\DBLayer\iDBLayer;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;
use App\Entity\UserInfos;
use App\Repository\UserRepository;
use App\Security\UserProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Phyxo\MenuBar;
use Phyxo\Extension\Theme;
use Symfony\Component\Routing\RouterInterface;

class SecurityController extends AbstractController
{
    private $template, $router, $conf;
    private $language_load = [];
    private $defaultLanguage, $defaultTheme, $phyxoVersion, $phyxoWebsite;

    public function __construct(Template $template, RouterInterface $router, Conf $conf, string $defaultLanguage, string $defaultTheme, string $phyxoVersion, string $phyxoWebsite) {
        $this->template = $template;
        $this->router = $router;
        $this->conf = $conf;

        $this->defaultLanguage = $defaultLanguage;
        $this->defaultTheme = $defaultTheme;
        $this->phyxoVersion = $phyxoVersion;
        $this->phyxoWebsite = $phyxoWebsite;
    }

    protected function init(User $user)
    {
        $this->language_load = \Phyxo\Functions\Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $user->getLanguage(), 'return_vars' => true]
        );

        $this->template->setUser($user);
        $this->template->setRouter($this->router);
        $this->template->setConf($this->conf);
        $this->template->setLang($this->language_load['lang']);
        $this->template->setLangInfo($this->language_load['lang_info']);
        $this->template->postConstruct();

        // default theme
        $this->template->setTheme(new Theme(__DIR__ . '/../../themes', $user->getTheme()));

        $this->template->assign([
            'CONTENT_ENCODING' => 'utf-8',
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
            'U_HOME' => $this->generateUrl('homepage'),
        ]);
    }

    public function login(AuthenticationUtils $authenticationUtils, CsrfTokenManagerInterface $csrfTokenManager, Request $request, UserProvider $userProvider)
    {
        $this->init($userProvider->loadUserByUsername('guest'));

        $error = $authenticationUtils->getLastAuthenticationError();
        $last_username = $authenticationUtils->getLastUsername();

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $token = $csrfTokenManager->getToken('authenticate');

        $tpl_params = [
            'login_action' => $this->generateUrl('login'),
            'register_route' => $this->generateUrl('register'),
            'password_route' => $this->generateUrl('forgot_password'),
            'last_username' => $last_username,
            'csrf_token' => $token,
            'errors' => $error ? \Phyxo\Functions\Language::l10n('Invalid credentials') : '',
        ];

        return $this->render('identification.tpl', $tpl_params);
    }

    public function register(Request $request, UserManager $user_manager, UserPasswordEncoderInterface $passwordEncoder, LoginFormAuthenticator $loginAuthenticator,
                                CsrfTokenManagerInterface $csrfTokenManager, GuardAuthenticatorHandler $guardHandler, UserProvider $userProvider)
    {
        $this->init($userProvider->loadUserByUsername('guest'));

        $errors = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $token = $csrfTokenManager->getToken('authenticate');

        $tpl_params = [
            'register_action' => $this->generateUrl('register'),
            'csrf_token' => $token,
        ];

        if ($request->isMethod('POST')) {
            if (!$request->request->get('_username')) {
                $errors[] = \Phyxo\Functions\Language::l10n('Username is missing. Please enter the username.');
            } else {
                $tpl_params['last_username'] = $request->request->get('_username');
            }

            if (!$request->request->get('_password')) {
                $errors[] = \Phyxo\Functions\Language::l10n('Password is missing. Please enter the password.');
            } elseif (!$request->request->get('_password_confirm')) {
                $errors[] = \Phyxo\Functions\Language::l10n('Password confirmation is missing. Please confirm the chosen password.');
            } elseif ($request->request->get('_password') != $request->request->get('_password_confirm')) {
                $errors[] = \Phyxo\Functions\Language::l10n('The passwords do not match');
            }

            if (count($errors) === 0) {
                $user = new User();
                $user->setUsername($request->request->get('_username'));
                $user->setMailAddress($request->request->get('_mail_address'));
                $user->setPassword($passwordEncoder->encodePassword($user, $request->request->get('_password')));

                try {
                    $user_manager->register($user);

                    return $guardHandler->authenticateUserAndHandleSuccess($user, $request, $loginAuthenticator, 'main');
                } catch (\Exception $e) {
                    $tpl_params['errors'] = $e->getMessage();
                }
            } else {
                $tpl_params['errors'] = $errors;
            }
        }

        return $this->render('register.tpl', $tpl_params);
    }

    public function profile(Request $request, iDBLayer $conn, UserPasswordEncoderInterface $passwordEncoder, UserManager $user_manager, MenuBar $menuBar)
    {
        $this->init($this->getUser());

        $errors = [];

        $this->language_load = \Phyxo\Functions\Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $this->getUser()->getLanguage(), 'return_vars' => true]
        );

        $languages = $conn->result2array((new LanguageRepository($conn))->findAll(), 'id', 'name');
        $themes = $conn->result2array((new ThemeRepository($conn))->findAll(), 'id', 'name');

        $custom_fields = ['nb_image_page', 'language', 'expand', 'show_nb_hits', 'recent_period', 'theme'];

        // @TODO: use symfony forms
        if ($request->isMethod('POST')) {
            if ($request->request->get('reset_to_default')) {
                $userdata = $user_manager->getDefaultUserInfo();
                $user_infos = $this->getUser()->getInfos();

                $userdata = array_merge($user_infos, $userdata);
                $userdata['user_id'] = $this->getUser()->getId();

                (new UserInfosRepository($conn))->massUpdates(['primary' => ['user_id'], 'update' => $custom_fields], [$userdata]);
                $this->getUser()->setInfos(new UserInfos($userdata));
            } else {
                $userdata = [];

                if ($request->request->get('_password') && $request->request->get('_new_password') && $request->request->get('_new_password_confirm')) {
                    if (!$passwordEncoder->isPasswordValid($this->getUser(), $request->request->get('_password'))) {
                        $errors[] = \Phyxo\Functions\Language::l10n('Current password is wrong');
                    } elseif ($request->request->get('_new_password') !== $request->request->get('_new_password_confirm')) {
                        $errors[] = \Phyxo\Functions\Language::l10n('The passwords do not match');
                    }

                    if (empty($errors)) {
                        $userdata['password'] = $passwordEncoder->encodePassword($this->getUser(), $request->request->get('_new_password'));
                    }
                }

                if (empty($errors) && $request->request->get('_mail_address')) {
                    if (filter_var($request->request->get('_mail_address'), FILTER_VALIDATE_EMAIL) === false) {
                        $errors[] = \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
                    }

                    if ((new UserRepository($conn))->isEmailExistsExceptUser($request->request->get('_mail_address'), $this->getUser()->getId())) {
                        $errors[] = \Phyxo\Functions\Language::l10n('this email address is already in use');
                    }

                    if (empty($errors)) {
                        $userdata['mail_address'] = $request->request->get('_mail_address');
                    }
                }

                if (empty($errors) && !empty($userdata)) {
                    // @TODO: use User entity instead of array for updateUser method
                    (new UserRepository($conn))->updateUser($userdata, $this->getUser()->getId());
                    if (!empty($userdata['password'])) {
                        $this->getUser()->setPassword($userdata['password']);
                    }

                    if (!empty($userdata['mail_address'])) {
                        $this->getUser()->setMailAddress($userdata['mail_address']);
                    }
                }

                $data = [];
                if ($request->request->get('nb_image_page')) {
                    if (($data['nb_image_page'] = (int) $request->request->get('nb_image_page')) === 0) {
                        $errors[] = \Phyxo\Functions\Language::l10n('The number of photos per page must be a not null scalar');
                        unset($data['nb_image_page']);
                    }
                }

                if ($request->request->get('language')) {
                    if (!isset($languages[$request->request->get('language')])) {
                        $errors[] = \Phyxo\Functions\Language::l10n('Incorrect language value');
                    } else {
                        $data['language'] = $request->request->get('language');
                    }
                }

                if ($request->request->get('theme')) {
                    if (!isset($themes[$request->request->get('theme')])) {
                        $errors[] = \Phyxo\Functions\Language::l10n('Incorrect theme value');
                    } else {
                        $data['theme'] = $request->request->get('theme');
                    }
                }

                if ($request->request->get('expand')) {
                    $data['expand'] = $request->request->get('expand');
                }

                if ($request->request->get('show_nb_comments')) {
                    $data['show_nb_comments'] = $request->request->get('show_nb_comments');
                }

                if ($request->request->get('show_nb_hits')) {
                    $data['show_nb_hits'] = $request->request->get('show_nb_hits');
                }

                if ($request->request->get('recent_period')) {
                    $data['recent_period'] = $request->request->get('recent_period');
                }

                if (empty($errors) && !empty($data)) {
                    // @TODO: use UserInfos entity instead of array for updateUserInfos method
                    (new UserInfosRepository($conn))->updateUserInfos($data, $this->getUser()->getId());

                    $this->getUser()->setInfos(new UserInfos(array_merge($this->getUser()->getInfos(), $data)));
                }
            }
        }

        $tpl_params = [
            'ALLOW_USER_CUSTOMIZATION' => $this->conf['allow_user_customization'],
            'ACTIVATE_COMMENTS' => $this->conf['activate_comments'],

            'USERNAME' => $this->getUser()->getUsername(),
            'EMAIL' => $this->getUser()->getMailAddress(),
            'NB_IMAGE_PAGE' => $this->getUser()->getNbImagePage(),
            'RECENT_PERIOD' => $this->getUser()->getRecentPeriod(),
            'EXPAND' => $conn->boolean_to_string($this->getUser()->wantExpand()),
            'NB_COMMENTS' => $conn->boolean_to_string($this->getUser()->getShowNbComments()),
            'NB_HITS' => $conn->boolean_to_string($this->getUser()->getShowNbHits()),
            'THEME' => $this->getUser()->getTheme(),
            'LANGUAGE' => $this->getUser()->getLanguage(),
            'radio_options' => [
                'true' => \Phyxo\Functions\Language::l10n('Yes'),
                'false' => \Phyxo\Functions\Language::l10n('No'),
            ],
            'errors' => $errors,
            'U_HOME' => $this->generateUrl('homepage'),
            'GALLERY_TITLE' => $this->conf['gallery_title']
        ];

        $tpl_params['themes'] = $themes;
        $tpl_params['languages'] = $languages;

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        \Phyxo\Functions\Plugin::trigger_notify('init');

        return $this->render('profile.tpl', $tpl_params);
    }

    public function forgotPassword(Request $request, iDBLayer $conn, UserManager $user_manager, \Swift_Mailer $mailer, CsrfTokenManagerInterface $csrfTokenManager,
                                    AdminTemplate $admin_template, UserProvider $userProvider)
    {
        $this->init($userProvider->loadUserByUsername('guest'));

        $tpl_params = [];

        $errors = [];
        $infos = [];
        $token = $csrfTokenManager->getToken('authenticate');
        $title = \Phyxo\Functions\Language::l10n('Forgot your password?');

        if ($request->request->get('_username_or_email')) {
            if ($user = $user_manager->findUserByUsernameOrEmail($request->request->get('_username_or_email'))) {
                if (empty($user['mail_address'])) {
                    $errors[] = \Phyxo\Functions\Language::l10n('User "%s" has no email address, password reset is not possible', $user['username']);
                } else {
                    $activation_key = $user_manager->generateActivationKey();
                    (new UserInfosRepository($conn))->updateUserInfos(
                        [
                            'activation_key' => $activation_key,
                            'activation_key_expire' => (new \DateTime())->add(new \DateInterval('PT1H'))->format('c'),
                        ],
                        $user['id']
                    );

                    $result = (new UserRepository($conn))->findById($this->conf['webmaster_id']);
                    $row = $conn->db_fetch_assoc($result);
                    $webmaster_mail_address = $row['mail_address'];

                    $mail_params = [
                        'user' => $user,
                        'url' => $this->generateUrl('reset_password', ['activation_key' => $activation_key], UrlGeneratorInterface::ABSOLUTE_URL),
                        'MAIL_TITLE' => \Phyxo\Functions\Language::l10n('Password Reset'),
                        'MAIL_THEME' => $this->conf['mail_theme'],
                        'GALLERY_URL' => $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        'CONTACT_MAIL' => $webmaster_mail_address,
                        'GALLERY_TITLE' => $this->conf['gallery_title'],
                    ];

                    if ($this->sendActivationKey($admin_template, $mail_params, $mailer, $webmaster_mail_address, $userProvider)) {
                        $title = \Phyxo\Functions\Language::l10n('Password reset');

                        $infos[] = \Phyxo\Functions\Language::l10n('Check your email for the confirmation link');
                    } else {
                        $errors[] = \Phyxo\Functions\Language::l10n('Error sending email');
                    }
                }
            } else {
                $errors[] = \Phyxo\Functions\Language::l10n('Invalid username or email');
            }
        }

        $tpl_params = [
            'U_HOME' => $this->generateUrl('homepage'),
            'forgot_password_action' => $this->generateUrl('forgot_password'),
            'title' => $title,
            'csrf_token' => $token,
            'errors' => $errors,
            'infos' => $infos,
        ];

        return $this->render('forgot_password.tpl', $tpl_params);
    }

    protected function sendActivationKey(AdminTemplate $template, array $params, \Swift_Mailer $mailer, string $webmaster_mail_address, UserProvider $userProvider)
    {
        $user = $userProvider->loadUserByUsername('guest');

        $language_load = \Phyxo\Functions\Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $user->getLanguage(), 'return_vars' => true]
        );

        $template->setLang($language_load['lang']);
        $template->setLangInfo($language_load['lang_info']);

        $template->assign([
            'gallery_url' => $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'lang_info' => $language_load['lang_info'],
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
        ]);

        $message = (new \Swift_Message('[' . $this->conf['gallery_title'] . '] ' . \Phyxo\Functions\Language::l10n('Password Reset')))
            ->addTo($params['user']['mail_address'])
            ->setBody($template->render('mail/reset_password.txt.tpl', $params), 'text/plain')
            ->addPart($template->render('mail/reset_password.html.tpl', $params), 'text/html');

        $message->setFrom($webmaster_mail_address);
        $message->setReplyTo($webmaster_mail_address);

        return $mailer->send($message);
    }

    public function resetPassword(Request $request, iDBLayer $conn, string $activation_key, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder, UserProvider $userProvider)
    {
        $token = $csrfTokenManager->getToken('authenticate');
        $errors = [];
        $infos = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $user = $userProvider->loadByActivationKey($activation_key);
        $this->init($user);

        // @TODO: use symfony forms
        if ($request->isMethod('POST')) {
            if ($request->request->get('_password') && $request->request->get('_password_confirmation') &&
                $request->request->get('_password') != $request->request->get('_password_confirm')) {
                (new UserRepository($conn))->updateUser(
                    ['password' => $passwordEncoder->encodePassword(new User(), $request->request->get('_password'))], $user->getId()
                );
                (new UserInfosRepository($conn))->updateUserInfos(['activation_key' => null, 'activation_key_expire' => null], $user->getId());
                $infos[] = \Phyxo\Functions\Language::l10n('Your password has been reset');
            } else {
                $errors[] = \Phyxo\Functions\Language::l10n('The passwords do not match');
            }
        }

        $tpl_params = [
            'reset_password_action' => $this->generateUrl('reset_password', ['activation_key' => $activation_key]),
            'csrf_token' => $token,
            'infos' => $infos,
            'errors' => $errors,
        ];

        return $this->render('reset_password.tpl', $tpl_params);
    }

    public function logout()
    {
    }
}
