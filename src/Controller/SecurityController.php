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
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;
use App\Exception\MissingGuestUserException;
use App\Form\UserRegistrationType;
use App\Repository\UserRepository;
use App\Security\AppUserService;
use App\Security\LoginFormAuthenticator;
use App\Security\UserProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Phyxo\MenuBar;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
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

    public function login(AuthenticationUtils $authenticationUtils, CsrfTokenManagerInterface $csrfTokenManager, Request $request, TranslatorInterface $translator)
    {
        $tpl_params = [];
        try {
            $tpl_params = $this->init();
        } catch (UserNotFoundException $e) {
            throw new MissingGuestUserException("User guest not found in database.");
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $last_username = $authenticationUtils->getLastUsername();

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

    public function register(
        Request $request,
        UserManager $user_manager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ) {
        $tpl_params = $this->init();

        $form = $this->createForm(UserRegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userModel = $form->getData();

            $user = new User();
            $user->setUsername($userModel->getUsername());
            $user->setPassword($passwordHasher->hashPassword($user, $userModel->getPassword()));
            $user->setMailAddress($userModel->getMailAddress());
            $user->addRole('ROLE_NORMAL');

            try {
                $user_manager->register($user);

                return $userAuthenticator->authenticateUser($user, $loginFormAuthenticator, $request);
            } catch (\Exception $e) {
                $tpl_params['errors'] = $e->getMessage();
            }
        }

        $tpl_params['form'] = $form->createView();
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $this->conf));

        return $this->render('register.html.twig', $tpl_params);
    }

    public function profile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        MenuBar $menuBar,
        LanguageRepository $languageRepository,
        TranslatorInterface $translator,
        CsrfTokenManagerInterface $csrfTokenManager,
        ThemeRepository $themeRepository,
        UserInfosRepository $userInfosRepository,
        UserRepository $userRepository,
        AppUserService $appUserService
    ) {
        $tpl_params = $this->init();

        $errors = [];

        $languages = $languageRepository->findAll();
        $themes = $themeRepository->findAll();

        // @TODO: use symfony forms
        if ($request->isMethod('POST')) {
            if ($request->request->get('reset_to_default')) {
                $guestUserInfos = $userInfosRepository->findOneBy(['status' => User::STATUS_GUEST]);
                $appUserService->getUser()->getUserInfos()->fromArray($guestUserInfos->toArray());
                $userRepository->updateUser($appUserService->getUser());
            } else {
                $needFlush = false;

                if ($request->request->get('_password') && $request->request->get('_new_password') && $request->request->get('_new_password_confirm')) {
                    if (!$passwordHasher->isPasswordValid($appUserService->getUser(), $request->request->get('_password'))) {
                        $errors[] = $translator->trans('Current password is wrong');
                    } elseif ($request->request->get('_new_password') !== $request->request->get('_new_password_confirm')) {
                        $errors[] = $translator->trans('The passwords do not match');
                    }

                    if (empty($errors)) {
                        $appUserService->getUser()->setPassword($passwordHasher->hashPassword($appUserService->getUser(), $request->request->get('_new_password')));
                        $needFlush = true;
                    }
                }

                if (empty($errors) && $request->request->get('_mail_address')) {
                    if (filter_var($request->request->get('_mail_address'), FILTER_VALIDATE_EMAIL) === false) {
                        $errors[] = $translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
                    }

                    if ($userRepository->isEmailExistsExceptUser($request->request->get('_mail_address'), $appUserService->getUser()->getId())) {
                        $errors[] = $translator->trans('this email address is already in use');
                    }

                    if (empty($errors)) {
                        $appUserService->getUser()->setMailAddress($request->request->get('_mail_address'));
                        $needFlush = true;
                    }
                }

                if ($request->request->get('nb_image_page')) {
                    if ((int) $request->request->get('nb_image_page') === 0) {
                        $errors[] = $translator->trans('The number of photos per page must be a not null scalar');
                    } else {
                        $appUserService->getUser()->getUserInfos()->setNbImagePage((int) $request->request->get('nb_image_page'));
                        $needFlush = true;
                    }
                }

                // @TODO: check language is in existing language ; same in AdminConfigurationController::default
                if ($request->request->get('language')) {
                    $request->getSession()->set('_locale', $request->request->get('language'));
                    $appUserService->getUser()->getUserInfos()->setLanguage($request->request->get('language'));
                    $needFlush = true;
                }

                if ($request->request->get('theme')) {
                    $appUserService->getUser()->getUserInfos()->setTheme($request->request->get('theme'));
                    $needFlush = true;
                }

                if ($request->request->get('expand')) {
                    $appUserService->getUser()->getUserInfos()->setExpand($request->request->get('expand') === 'true');
                    $needFlush = true;
                }

                if ($request->request->get('show_nb_comments')) {
                    $appUserService->getUser()->getUserInfos()->setShowNbComments($request->request->get('show_nb_comments') === 'true');
                    $needFlush = true;
                }

                if ($request->request->get('show_nb_hits')) {
                    $appUserService->getUser()->getUserInfos()->setShowNbHits($request->request->get('show_nb_hits') === 'true');
                    $needFlush = true;
                }

                if ($request->request->get('recent_period') && $appUserService->getUser()->getUserInfos()->getRecentPeriod() != $request->request->get('recent_period')) {
                    $appUserService->getUser()->getUserInfos()->setRecentPeriod((int) $request->request->get('recent_period'));
                    $needFlush = true;
                }

                if (empty($errors) && $needFlush) {
                    $userRepository->updateUser($appUserService->getUser());

                    return $this->redirectToRoute('profile');
                }
            }
        }

        $userInfos = $appUserService->getUser()->getUserInfos();
        $tpl_params = array_merge($tpl_params, [
            'ALLOW_USER_CUSTOMIZATION' => $this->conf['allow_user_customization'],
            'ACTIVATE_COMMENTS' => $this->conf['activate_comments'],

            'USERNAME' => $appUserService->getUser()->getUserIdentifier(),
            'EMAIL' => $appUserService->getUser()->getMailAddress(),
            'NB_IMAGE_PAGE' => $userInfos->getNbImagePage(),
            'RECENT_PERIOD' => $userInfos->getRecentPeriod(),
            'EXPAND' => $userInfos->wantExpand(),
            'NB_COMMENTS' => $userInfos->getShowNbComments() ? 'true' : 'false',
            'NB_HITS' => $userInfos->getShowNbHits() ? 'true': 'false',
            'THEME' => $userInfos->getTheme(),
            'LANGUAGE' => $userInfos->getLanguage(),
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

    public function forgotPassword(
        Request $request,
        UserManager $user_manager,
        MailerInterface $mailer,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
        UserRepository $userRepository,
        UserInfosRepository $userInfosRepository
    ) {
        $tpl_params = $this->init();

        $errors = [];
        $infos = [];
        $token = $csrfTokenManager->getToken('authenticate');
        $title = $translator->trans('Forgot your password?');

        if ($request->request->get('_username_or_email')) {
            $user = $userRepository->findUserByUsernameOrEmail($request->request->get('_username_or_email'));
            if (is_null($user)) {
                $errors[] = $translator->trans('Invalid username or email');
            } else {
                if (is_null($user->getMailAddress())) {
                    $errors[] = $translator->trans('User "%s" has no email address, password reset is not possible', $user['username']);
                } else {
                    $activation_key = $user_manager->generateActivationKey();
                    $user->getUserInfos()->setActivationKey($activation_key);
                    $user->getUserInfos()->setActivationKeyExpire((new \DateTime())->add(new \DateInterval('PT1H')));
                    $userRepository->updateUser($user);

                    $webmaster = $userInfosRepository->findOneBy(['status' => User::STATUS_WEBMASTER]);
                    $webmaster_mail_address = $webmaster->getUser()->getMailAddress();

                    $mail_params = [
                        'user' => $user,
                        'url' => $this->generateUrl('reset_password', ['activation_key' => $activation_key], UrlGeneratorInterface::ABSOLUTE_URL),
                        'MAIL_TITLE' => $translator->trans('Password Reset'),
                        'MAIL_THEME' => $this->conf['mail_theme'],
                        'GALLERY_URL' => $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        'CONTACT_MAIL' => $webmaster_mail_address,
                        'GALLERY_TITLE' => $this->conf['gallery_title'],
                    ];

                    try {
                        $this->sendActivationKey($mail_params, $mailer, $webmaster_mail_address, $translator);
                        $title = $translator->trans('Password reset');

                        $infos[] = $translator->trans('Check your email for the confirmation link');
                    } catch (\Exception $e) {
                        $errors[] = $translator->trans('Error sending email');
                    }
                }
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

    protected function sendActivationKey(array $params, MailerInterface $mailer, string $webmaster_mail_address, TranslatorInterface $translator): void
    {
        $tpl_params = array_merge([
            'gallery_url' => $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
        ], $params);

        $message = (new TemplatedEmail())
            ->subject('[' . $this->conf['gallery_title'] . '] ' . $translator->trans('Password Reset'))
            ->to($params['user']->getMailAddress())
            ->textTemplate('mail/text/reset_password.text.twig')
            ->htmlTemplate('mail/html/reset_password.html.twig')
            ->context($tpl_params);

        $message->from($webmaster_mail_address);
        $message->replyTo($webmaster_mail_address);

        $mailer->send($message);
    }

    public function resetPassword(
        Request $request,
        string $activation_key,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordHasherInterface $passwordHasher,
        UserProvider $userProvider,
        TranslatorInterface $translator,
        UserRepository $userRepository
    ) {
        $token = $csrfTokenManager->getToken('authenticate');
        $errors = [];
        $infos = [];

        $user = $userProvider->loadByActivationKey($activation_key);
        $tpl_params = $this->init();

        // @TODO: use symfony forms
        if ($request->isMethod('POST')) {
            if ($request->request->get('_password') && $request->request->get('_password_confirmation') &&
                $request->request->get('_password') != $request->request->get('_password_confirm')) {
                $user->setPassword($passwordHasher->hashPassword(new User(), $request->request->get('_password')));
                $user->getUserInfos()->setActivationKey(null);
                $user->getUserInfos()->setActivationKeyExpire(null);
                $userRepository->updateUser($user);
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
