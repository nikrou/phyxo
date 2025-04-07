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

namespace App\Controller\Admin;

use App\DataMapper\AlbumMapper;
use App\DataMapper\UserMapper;
use App\Entity\User;
use App\Enum\UserPrivacyLevelType;
use App\Enum\UserStatusType;
use App\Form\Model\UserProfileModel;
use App\Form\UserCreationType;
use App\Form\UserProfileType;
use App\Repository\GroupRepository;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserRepository;
use App\Security\AppUserService;
use Exception;
use Phyxo\Conf;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminUsersController extends AbstractController
{
    private TranslatorInterface $translator;
    public function setTabsheet(string $section = 'list', int $user_id = 0): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('list', $this->translator->trans('User list', [], 'admin'), $this->generateUrl('admin_users'), 'fa-users');
        $tabsheet->add('perm', $this->translator->trans('Permissions', [], 'admin'), $user_id !== 0 ? $this->generateUrl('admin_user_perm', ['user_id' => $user_id]) : '', 'fa-lock');
        $tabsheet->select($section);

        return $tabsheet;
    }
    #[Route('/admin/users', name: 'admin_users')]
    public function list(
        Conf $conf,
        AppUserService $appUserService,
        UserMapper $userMapper,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
        ThemeRepository $themeRepository,
        LanguageRepository $languageRepository,
        UserRepository $userRepository,
        UserInfosRepository $userInfosRepository,
        GroupRepository $groupRepository
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $groups = [];
        foreach ($groupRepository->findAll() as $group) {
            $groups[$group->getId()] = $group->getName();
        }

        $users = [];
        $user_ids = [];
        foreach ($userRepository->findAll() as $user) {
            $users[] = $user;
            $user_ids[] = $user->getId();
        }

        $tpl_params['users'] = $users;
        $tpl_params['all_users'] = implode(',', $user_ids);
        $tpl_params['ACTIVATE_COMMENTS'] = $conf['activate_comments'];
        $tpl_params['Double_Password'] = $conf['double_password_type_in_admin'];

        $guestUser = $userMapper->getDefaultUser();
        $protected_users = [$appUserService->getUser()->getId()];
        $protected_users[] = $guestUser->getId();

        // an admin can't delete other admin/webmaster
        if ($userMapper->isAdmin()) {
            foreach ($userInfosRepository->findBy(['status' => [UserStatusType::WEBMASTER, UserStatusType::ADMIN]]) as $userInfos) {
                $protected_users[] = $userInfos->getUser()->getId();
            }
        }

        $themes = [];
        foreach ($themeRepository->findAll() as $theme) {
            $themes[$theme->getId()] = $theme->getName();
        }

        $languages = [];
        foreach ($languageRepository->findAll() as $language) {
            $languages[$language->getId()] = $language->getName();
        }

        $guestUserInfos = $guestUser->getUserInfos();
        $dummy_user = 9999;
        $tpl_params = array_merge($tpl_params, [
            'F_ADD_ACTION' => $this->generateUrl('admin_users'),
            'F_USER_PERM' => $this->generateUrl('admin_user_perm', ['user_id' => $dummy_user]),
            'F_DUMMY_USER' => $dummy_user,
            'F_EDIT_USER' => $this->generateUrl('admin_user_edit', ['user_id' => $dummy_user]),
            'NB_IMAGE_PAGE' => $guestUserInfos->getNbImagePage(),
            'RECENT_PERIOD' => $guestUserInfos->getRecentPeriod(),
            'theme_options' => $themes,
            'theme_selected' => $guestUserInfos->getTheme(),
            'language_options' => $languages,
            'language_selected' => $guestUserInfos->getLanguage(),
            'association_options' => $groups,
            'protected_users' => implode(',', array_unique($protected_users)),
            'guest_user' => $guestUser->getId(),
        ]);

        // Status options
        foreach (UserStatusType::cases() as $status) {
            $label_of_status[$status->value] = $translator->trans('user_status_' . $status->value, [], 'admin');
        }

        $pref_status_options = $label_of_status;

        if (!$userMapper->isWebmaster()) {
            unset($pref_status_options['webmaster']);

            if ($userMapper->isAdmin()) {
                unset($pref_status_options['admin']);
            }
        }

        $tpl_params['label_of_status'] = $label_of_status;
        $tpl_params['pref_status_options'] = $pref_status_options;
        $tpl_params['pref_status_selected'] = 'normal';

        // user level options
        $level_options = [];
        foreach (UserPrivacyLevelType::cases() as $level) {
            $level_options[$level->value] = $translator->trans(sprintf('Level %d', $level->value), [], 'admin');
        }

        $tpl_params['level_options'] = $level_options;
        $tpl_params['level_selected'] = $guestUser->getUserInfos()->getLevel()->value;

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_users');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_users');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Users', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('list');

        return $this->render('users_list.html.twig', $tpl_params);
    }
    #[Route('/admin/users/{user_id}/perm', name: 'admin_user_perm', requirements: ['user_id' => '\d+'])]
    public function perm(Request $request, int $user_id, AlbumMapper $albumMapper, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        $user = $userRepository->find($user_id);

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->has('cat_true')) {
                // if you forbid access to a category, all sub-categories become automatically forbidden
                foreach ($albumMapper->getRepository()->getSubAlbums($request->request->all('cat_true')) as $album) {
                    $album->removeUserAccess($user);
                    $albumMapper->getRepository()->addOrUpdateAlbum($album);
                }
            } elseif ($request->request->get('trueify') && $request->request->has('cat_false')) {
                $albumMapper->addPermissionOnAlbum($request->request->all('cat_false'), [$user_id]);
            }
        }

        $tpl_params['TITLE'] = $translator->trans('Manage permissions for user "{user}"', ['user' => $user->getUserIdentifier()], 'admin');
        $tpl_params['L_CAT_OPTIONS_TRUE'] = $translator->trans('Authorized', [], 'admin');
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $translator->trans('Forbidden', [], 'admin');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_user_perm', ['user_id' => $user_id]);

        // retrieve category ids authorized to the groups the user belongs to
        $group_authorized = [];
        $authorized_ids = [];
        foreach ($user->getUserAccess() as $album) {
            $tpl_params['categories_because_of_groups'][] = $albumMapper->getAlbumsDisplayNameCache($album->getUppercats());
            $group_authorized[] = $album->getId();
        }

        // only private categories are listed
        $albums = [];
        foreach ($albumMapper->getRepository()->findPrivateWithUserAccessAndNotExclude($user_id, $group_authorized) as $album) {
            $albums[] = $album;
            $authorized_ids[] = $album->getId();
        }

        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, [], 'category_option_true'));

        $albums = [];
        foreach ($albumMapper->getRepository()->findUnauthorized([...$authorized_ids, ...$group_authorized]) as $album) {
            $albums[] = $album;
        }

        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, [], 'category_option_false'));

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_user_perm', ['user_id' => $user_id]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_users');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Users', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('perm', $user_id);

        return $this->render('user_perm.html.twig', $tpl_params);
    }
    #[Route('/admin/users/add', name: 'admin_user_add')]
    public function add(Request $request, UserPasswordHasherInterface $passwordHasher, AppUserService $appUserService, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        $form = $this->createForm(UserCreationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UserProfileModel $userProfileModel */
            $userProfileModel = $form->getData();

            $user = new User();
            $user->setUsername($userProfileModel->getUsername());
            if ($userProfileModel->getCurrentPassword()) {
                $user->setPassword($passwordHasher->hashPassword($user, $userProfileModel->getCurrentPassword()));
            }

            $user->setMailAddress($userProfileModel->getMailAddress());
            $user->addRole('ROLE_NORMAL');

            try {
                $appUserService->register($user);

                $this->addFlash('info', $translator->trans('User has been added'));
                return $this->redirectToRoute('admin_users');
            } catch (Exception $e) {
                $tpl_params['errors'] = $e->getMessage();
            }
        }

        $tpl_params['form'] = $form->createView();
        $tpl_params['tabsheet'] = $this->setTabsheet('edit');
        $tpl_params['add_user'] = true;

        return $this->render('user_form.html.twig', $tpl_params);
    }
    #[Route('/admin/users/{user_id}/edit', name: 'admin_user_edit', requirements: ['user_id' => '\d+'])]
    public function edit(
        int $user_id,
        Request $request,
        UserRepository $userRepository,
        TranslatorInterface $translator,
        UserPasswordHasherInterface $passwordHasher,
        AppUserService $appUserService,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $user = $userRepository->find($user_id);
        if (is_null($user)) {
            return new Response('User not found ', Response::HTTP_NOT_FOUND);
        }

        /** @var Form $form */
        $form = $this->createForm(UserProfileType::class, $user, [UserProfileType::IN_ADMIN_OPTION => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            if ($form->getClickedButton()->getName() === 'resetToDefault') {
                $guestUser = $appUserService->getDefaultUser();
                $user->getUserInfos()->fromArray($guestUser->getUserInfos()->toArray());
                $userRepository->updateUser($user);
                $request->getSession()->set('_theme', $guestUser->getTheme());

                $this->addFlash('info', $translator->trans('User settings are now the default ones'));
            } else {
                if (!is_null($user->getPlainPassword())) {
                    $user->setPassword($passwordHasher->hashPassword($user, $user->getPlainPassword()));
                }

                $userRepository->updateUser($user);
                $request->getSession()->set('_theme', $user->getTheme());

                $this->addFlash('info', $translator->trans('User settings have been updated'));
            }

            return $this->redirectToRoute('admin_users');
        }

        $tpl_params['form'] = $form->createView();
        $tpl_params['tabsheet'] = $this->setTabsheet('edit');

        return $this->render('user_form.html.twig', $tpl_params);
    }
}
