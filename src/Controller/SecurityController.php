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

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Exception;
use DateTime;
use DateInterval;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Events\ActivationKeyEvent;
use App\Utils\UserManager;
use App\Form\ForgotPasswordType;
use App\Form\PasswordResetType;
use App\Form\UserProfileType;
use App\Form\UserRegistrationType;
use App\Repository\UserRepository;
use App\Security\AppUserService;
use App\Security\LoginFormAuthenticator;
use App\Security\UserProvider;
use Phyxo\Conf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    public function login(
        AuthenticationUtils $authenticationUtils,
        CsrfTokenManagerInterface $csrfTokenManager,
        Request $request,
        Conf $conf,
        TranslatorInterface $translator
    ): Response {
        $tpl_params = [];

        $error = $authenticationUtils->getLastAuthenticationError();
        $last_username = $authenticationUtils->getLastUsername();

        $token = $csrfTokenManager->getToken('authenticate');

        $tpl_params = array_merge($tpl_params, [
            'AUTHORIZE_REMEMBERING' => $conf['authorize_remembering'],
            'login_route' => $this->generateUrl('login'),
            'register_route' => $this->generateUrl('register'),
            'password_route' => $this->generateUrl('forgot_password'),
            'last_username' => $last_username,
            'csrf_token' => $token,
            'errors' => $error instanceof AuthenticationException ? $translator->trans('Invalid credentials') : '',
        ]);

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
    ): Response {
        $tpl_params = [];
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
            } catch (Exception $e) {
                $tpl_params['errors'] = $e->getMessage();
            }
        }

        $tpl_params['form'] = $form->createView();

        return $this->render('register.html.twig', $tpl_params);
    }

    public function profile(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        AppUserService $appUserService,
        TranslatorInterface $translator
    ): Response {
        $tpl_params = [];
        /** @var Form $form */
        $form = $this->createForm(UserProfileType::class, $appUserService->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $clickedButton = $form->getClickedButton();
            if ($clickedButton->getName() === 'resetToDefault') {
                $guestUser = $appUserService->getDefaultUser();
                $appUserService->getUser()->getUserInfos()->fromArray($guestUser->getUserInfos()->toArray());
                $userRepository->updateUser($appUserService->getUser());
                $request->getSession()->set('_theme', $guestUser->getTheme());

                $this->addFlash('info', $translator->trans('User settings are now the default ones'));
            } else {
                $user = $form->getData();
                if (!is_null($user->getPlainPassword())) {
                    $user->setPassword($passwordHasher->hashPassword($user, $user->getPlainPassword()));
                }

                $userRepository->updateUser($user);
                $request->getSession()->set('_theme', $user->getTheme());

                $this->addFlash('info', $translator->trans('User settings have been updated'));
            }

            return $this->redirectToRoute('profile');
        }

        $tpl_params['form'] = $form->createView();

        return $this->render('profile.html.twig', $tpl_params);
    }

    public function forgotPassword(Request $request, UserManager $user_manager, UserRepository $userRepository, EventDispatcherInterface $dispatcher, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            if (is_null($user)) {
                $this->addFlash('error', $translator->trans('Invalid username or email'));
            } elseif (is_null($user->getMailAddress())) {
                $this->addFlash('error', $translator->trans('User "%username%" has no email address, password reset is not possible', ['%username%' => $user->getUserIdentifier()]));
            } else {
                $activation_key = $user_manager->generateActivationKey();
                $user->getUserInfos()->setActivationKey($activation_key);
                $user->getUserInfos()->setActivationKeyExpire((new DateTime())->add(new DateInterval('PT1H')));
                $userRepository->updateUser($user);

                $dispatcher->dispatch(new ActivationKeyEvent($activation_key, $user));

                $this->addFlash('info', $translator->trans('Check your email for the confirmation link'));
            }
        }

        $tpl_params['form'] = $form->createView();

        return $this->render('forgot_password.html.twig', $tpl_params);
    }

    public function resetPassword(
        Request $request,
        string $activation_key,
        UserPasswordHasherInterface $passwordHasher,
        UserProvider $userProvider,
        TranslatorInterface $translator,
        UserRepository $userRepository
    ): Response {
        try {
            $tpl_params = [];

            $user = $userProvider->loadByActivationKey($activation_key);

            $form = $this->createForm(PasswordResetType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $data->getNewPassword()));
                $userRepository->updateUser($user);

                $this->addFlash('info', $translator->trans('Your password has been updated'));
            }

            $tpl_params['form'] = $form->createView();
        } catch (Exception) {
            $this->addFlash('error', 'Activation key does not exist');
        }

        return $this->render('reset_password.html.twig', $tpl_params);
    }

    public function logout(): void
    {
    }
}
