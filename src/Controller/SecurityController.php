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
use Phyxo\Conf;
use App\Entity\User;
use App\Utils\UserManager;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use App\Security\LoginFormAuthenticator;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Phyxo\DBLayer\DBLayer;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;
use App\Entity\UserInfos;
use App\Repository\UserRepository;

class SecurityController extends AbstractController
{
    private $csrfTokenManager;
    private $conf;

    public function __construct(Template $template, Conf $conf, CsrfTokenManagerInterface $csrfTokenManager, $default_language, $phyxoVersion, $phyxoWebsite)
    {
        $this->conf = $conf;

        // default theme
        $template->set_template_dir(__DIR__.'/../../themes/treflez/template');

        // to be removed
        define('PHPWG_ROOT_PATH', __DIR__.'/../../');

        $language_load = \Phyxo\Functions\Language::load_language(
            'common.lang',
            __DIR__.'/../../',
            ['language' => $default_language, 'return_vars' => true]
        );
        $template->setLang($language_load['lang']);
        $template->setLangInfo($language_load['lang_info']);
        $template->postConstruct();

        $template->assign('VERSION', $conf['show_version'] ? $phyxoVersion : '');
        $template->assign('PHYXO_URL', $phyxoWebsite);

        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function login(AuthenticationUtils $authenticationUtils, Request $request)
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $last_username = $authenticationUtils->getLastUsername();

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $token = $this->csrfTokenManager->getToken('authenticate');

        $tpl_params = [
            'login_action' => $this->generateUrl('login'),
            'register_route' => $this->generateUrl('register'),
            'password_route' => $this->generateUrl('password'),
            'last_username' => $last_username,
            'csrf_token' => $token,
            'errors' => $error ? $error->getMessage() : '',
        ];

        return $this->render('identification.tpl', $tpl_params);
    }

    public function register(
        Request $request,
        UserManager $user_manager,
        UserPasswordEncoderInterface $passwordEncoder,
        LoginFormAuthenticator $loginAuthenticator,
        GuardAuthenticatorHandler $guardHandler
    ) {
        $errors = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $token = $this->csrfTokenManager->getToken('authenticate');

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

    public function profile(Request $request, DBLayer $conn, UserPasswordEncoderInterface $passwordEncoder, UserManager $user_manager)
    {
        $errors = [];

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

                if ($request->request->get('expand')) {
                    $data['expand'] = $request->request->get('expand');
                }

                if ($request->request->get('show_nb_comments')) {
                    $data['show_nb_comments'] = $request->request->get('show_nb_comments');
                }

                if ($request->request->get('show_nb_hits')) {
                    $data['show_nb_hits'] = $request->request->get('show_nb_hits');
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
        ];

        $tpl_params['themes'] = $themes;
        $tpl_params['languages'] = $languages;

        return $this->render('profile.tpl', $tpl_params);
    }

    public function logout()
    {
    }
}
