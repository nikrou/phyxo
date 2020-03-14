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
use Symfony\Component\HttpFoundation\Request;
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
use App\Exception\MissingGuestUserException;
use App\Repository\UserRepository;
use App\Security\UserProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Phyxo\MenuBar;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends CommonController
{
    protected function init(): array
    {
        return [
            'CONTENT_ENCODING' => 'utf-8',
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
            'U_HOME' => $this->generateUrl('homepage'),
        ];
    }

    public function login(AuthenticationUtils $authenticationUtils, CsrfTokenManagerInterface $csrfTokenManager, Request $request, UserProvider $userProvider, TranslatorInterface $translator)
    {
        $tpl_params = [];
        try {
            $tpl_params = $this->init();
        } catch (UsernameNotFoundException $e) {
            throw new MissingGuestUserException("User guest not found in database.");
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $last_username = $authenticationUtils->getLastUsername();

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $token = $csrfTokenManager->getToken('authenticate');

        $tpl_params = array_merge($tpl_params, [
            'AUTHORIZE_REMEMBERING' => $this->conf['authorize_remembering'],
            'login_route' => $this->generateUrl('login'),
            'register_route' => $this->generateUrl('register'),
            'password_route' => $this->generateUrl('forgot_password'),
            'last_username' => $last_username,
            'csrf_token' => $token,
            'errors' => $error ? $translator->trans('Invalid credentials') : '',
        ]);

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $this->conf));

        $status_code = 200;
        if ($request->getSession()->has('_redirect')) {
            $request->getSession()->remove('_redirect');
            $status_code = 403;
            $tpl_params['errors'] = $translator->trans('You are not authorized to access the requested page');
        }

        return $this->render('identification.html.twig', $tpl_params, new Response('', $status_code));
    }

    public function register(Request $request, UserManager $user_manager, UserPasswordEncoderInterface $passwordEncoder, LoginFormAuthenticator $loginAuthenticator,
                                CsrfTokenManagerInterface $csrfTokenManager, GuardAuthenticatorHandler $guardHandler, UserProvider $userProvider, TranslatorInterface $translator)
    {
        $tpl_params = $this->init();

        $errors = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $token = $csrfTokenManager->getToken('authenticate');

        $tpl_params['register_action'] = $this->generateUrl('register');
        $tpl_params['last_username'] = '';
        $tpl_params['mail_address'] = '';
        $tpl_params['csrf_token'] = $token;

        if ($request->isMethod('POST')) {
            if (!$request->request->get('_username')) {
                $errors[] = $translator->trans('Username is missing. Please enter the username.');
            } else {
                $tpl_params['last_username'] = $request->request->get('_username');
            }

            if ($request->request->get('_mail_address')) {
                $tpl_params['mail_address'] = $request->request->get('_mail_address');
            }

            if (!$request->request->get('_password')) {
                $errors[] = $translator->trans('Password is missing. Please enter the password.');
            } elseif (!$request->request->get('_password_confirm')) {
                $errors[] = $translator->trans('Password confirmation is missing. Please confirm the chosen password.');
            } elseif ($request->request->get('_password') != $request->request->get('_password_confirm')) {
                $errors[] = $translator->trans('The passwords do not match');
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
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $this->conf));

        return $this->render('register.html.twig', $tpl_params);
    }

    public function profile(Request $request, iDBLayer $conn, UserPasswordEncoderInterface $passwordEncoder, UserManager $user_manager, MenuBar $menuBar,
                            UserProvider $userProvider, TranslatorInterface $translator, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->userProvider = $userProvider;
        $tpl_params = $this->init();

        $errors = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

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
                        $errors[] = $translator->trans('Current password is wrong');
                    } elseif ($request->request->get('_new_password') !== $request->request->get('_new_password_confirm')) {
                        $errors[] = $translator->trans('The passwords do not match');
                    }

                    if (empty($errors)) {
                        $userdata['password'] = $passwordEncoder->encodePassword($this->getUser(), $request->request->get('_new_password'));
                    }
                }

                if (empty($errors) && $request->request->get('_mail_address')) {
                    if (filter_var($request->request->get('_mail_address'), FILTER_VALIDATE_EMAIL) === false) {
                        $errors[] = $translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
                    }

                    if ((new UserRepository($conn))->isEmailExistsExceptUser($request->request->get('_mail_address'), $this->getUser()->getId())) {
                        $errors[] = $translator->trans('this email address is already in use');
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
                        $errors[] = $translator->trans('The number of photos per page must be a not null scalar');
                        unset($data['nb_image_page']);
                    }
                }

                if ($request->request->get('language')) {
                    if (!isset($languages[$request->request->get('language')])) {
                        $errors[] = $translator->trans('Incorrect language value');
                    } else {
                        $data['language'] = $request->request->get('language');
                    }
                }

                if ($request->request->get('theme')) {
                    if (!isset($themes[$request->request->get('theme')])) {
                        $errors[] = $translator->trans('Incorrect theme value');
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

        $tpl_params = array_merge($tpl_params, [
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
                'true' => $translator->trans('Yes'),
                'false' => $translator->trans('No'),
            ],
            'errors' => $errors,
            'U_HOME' => $this->generateUrl('homepage'),
            'GALLERY_TITLE' => $this->conf['gallery_title']
        ]);

        $tpl_params['themes'] = $themes;
        $tpl_params['languages'] = $languages;
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $this->conf));

        return $this->render('profile.html.twig', $tpl_params);
    }

    public function forgotPassword(Request $request, iDBLayer $conn, UserManager $user_manager, \Swift_Mailer $mailer, CsrfTokenManagerInterface $csrfTokenManager,
                                    TranslatorInterface $translator)
    {
        $tpl_params = $this->init();

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $errors = [];
        $infos = [];
        $token = $csrfTokenManager->getToken('authenticate');
        $title = $translator->trans('Forgot your password?');

        if ($request->request->get('_username_or_email')) {
            if ($user = $user_manager->findUserByUsernameOrEmail($request->request->get('_username_or_email'))) {
                if (empty($user['mail_address'])) {
                    $errors[] = $translator->trans('User "%s" has no email address, password reset is not possible', $user['username']);
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
                        'MAIL_TITLE' => $translator->trans('Password Reset'),
                        'MAIL_THEME' => $this->conf['mail_theme'],
                        'GALLERY_URL' => $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        'CONTACT_MAIL' => $webmaster_mail_address,
                        'GALLERY_TITLE' => $this->conf['gallery_title'],
                    ];

                    if ($this->sendActivationKey($mail_params, $mailer, $webmaster_mail_address, $translator)) {
                        $title = $translator->trans('Password reset');

                        $infos[] = $translator->trans('Check your email for the confirmation link');
                    } else {
                        $errors[] = $translator->trans('Error sending email');
                    }
                }
            } else {
                $errors[] = $translator->trans('Invalid username or email');
            }
        }

        $tpl_params = array_merge($tpl_params, [
            'forgot_password_action' => $this->generateUrl('forgot_password'),
            'title' => $title,
            'csrf_token' => $token,
            'errors' => $errors,
            'infos' => $infos,
        ]);

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $this->conf));

        return $this->render('forgot_password.html.twig', $tpl_params);
    }

    protected function sendActivationKey(array $params, \Swift_Mailer $mailer, string $webmaster_mail_address, TranslatorInterface $translator)
    {
        $tpl_params = array_merge([
            'gallery_url' => $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
        ], $params);

        $message = (new \Swift_Message('[' . $this->conf['gallery_title'] . '] ' . $translator->trans('Password Reset')))
            ->addTo($params['user']['mail_address'])
            ->setBody($this->renderView('mail/text/reset_password.text.twig', $tpl_params), 'text/plain')
            ->addPart($this->renderView('mail/html/reset_password.html.twig', $tpl_params), 'text/html');

        $message->setFrom($webmaster_mail_address);
        $message->setReplyTo($webmaster_mail_address);

        return $mailer->send($message);
    }

    public function resetPassword(Request $request, iDBLayer $conn, string $activation_key, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder,
                                    UserProvider $userProvider, TranslatorInterface $translator)
    {
        $token = $csrfTokenManager->getToken('authenticate');
        $errors = [];
        $infos = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $user = $userProvider->loadByActivationKey($activation_key);
        $tpl_params = $this->init();

        // @TODO: use symfony forms
        if ($request->isMethod('POST')) {
            if ($request->request->get('_password') && $request->request->get('_password_confirmation') &&
                $request->request->get('_password') != $request->request->get('_password_confirm')) {
                (new UserRepository($conn))->updateUser(
                    ['password' => $passwordEncoder->encodePassword(new User(), $request->request->get('_password'))], $user->getId()
                );
                (new UserInfosRepository($conn))->updateUserInfos(['activation_key' => null, 'activation_key_expire' => null], $user->getId());
                $infos[] = $translator->trans('Your password has been reset');
            } else {
                $errors[] = $translator->trans('The passwords do not match');
            }
        }

        $tpl_params = array_merge($tpl_params, [
            'reset_password_action' => $this->generateUrl('reset_password', ['activation_key' => $activation_key]),
            'csrf_token' => $token,
            'infos' => $infos,
            'errors' => $errors,
        ]);

        $tpl_params['title'] = $translator->trans('Password reset');
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $this->conf));

        return $this->render('reset_password.html.twig', $tpl_params);
    }

    public function logout()
    {
    }
}
