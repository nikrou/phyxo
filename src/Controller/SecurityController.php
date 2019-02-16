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
use Phyxo\Model\Repository\Users;
use App\Utils\UserManager;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use App\Security\LoginFormAuthenticator;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Phyxo\DBLayer\DBLayer;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;

class SecurityController extends AbstractController
{
    private $csrfTokenManager, $conf;

    public function __construct(Template $template, Conf $conf, CsrfTokenManagerInterface $csrfTokenManager, $default_language, $phyxoVersion, $phyxoWebsite)
    {
        $this->conf = $conf;

        // default theme
        $template->set_template_dir(__DIR__ . '/../../themes/treflez/template');

        // to be removed
        define('PHPWG_ROOT_PATH', __DIR__ . '/../../');

        $language_load = \Phyxo\Functions\Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
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

    public function logout()
    {
    }
}
