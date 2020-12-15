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
use App\Repository\GroupRepository;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersController extends AbstractController
{
    private $translator;

    public function setTabsheet(string $section = 'list', int $user_id = 0): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('list', $this->translator->trans('User list', [], 'admin'), $this->generateUrl('admin_users'), 'fa-users');
        $tabsheet->add('perm', $this->translator->trans('Permissions', [], 'admin'), $user_id !== 0 ? $this->generateUrl('admin_user_perm', ['user_id' => $user_id]) : null, 'fa-lock');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function list(Request $request, Conf $conf, UserMapper $userMapper, CsrfTokenManagerInterface $csrfTokenManager, TranslatorInterface $translator,
                        ThemeRepository $themeRepository, LanguageRepository $languageRepository,
                        UserRepository $userRepository, UserInfosRepository $userInfosRepository, GroupRepository $groupRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $groups = [];

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
        $tpl_params['all_users'] = join(',', $user_ids);
        $tpl_params['ACTIVATE_COMMENTS'] = $conf['activate_comments'];
        $tpl_params['Double_Password'] = $conf['double_password_type_in_admin'];

        $guestUser = $userMapper->getDefaultUser();
        $protected_users = [$this->getUser()->getId()];
        $protected_users[] = $guestUser->getId();

        // an admin can't delete other admin/webmaster
        if ($userMapper->isAdmin()) {
            foreach ($userInfosRepository->findBy(['status' => [User::STATUS_WEBMASTER, User::STATUS_ADMIN]]) as $userInfos) {
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

        $dummy_user = 9999;
        $tpl_params = array_merge($tpl_params, [
            'F_ADD_ACTION' => $this->generateUrl('admin_users'),
            'F_USER_PERM' => $this->generateUrl('admin_user_perm', ['user_id' => $dummy_user]),
            'F_USER_PERM_DUMMY_USER' => $dummy_user,
            'NB_IMAGE_PAGE' => $guestUser->getNbImagePage(),
            'RECENT_PERIOD' => $guestUser->getRecentPeriod(),
            'theme_options' => $themes,
            'theme_selected' => $guestUser->getTheme(),
            'language_options' => $languages,
            'language_selected' => $guestUser->getLanguage(),
            'association_options' => $groups,
            'protected_users' => implode(',', array_unique($protected_users)),
            'guest_user' => $guestUser->getUser()->getId(),
        ]);

        // Status options
        foreach (User::ALL_STATUS as $status) {
            $label_of_status[$status] = $translator->trans('user_status_' . $status, [], 'admin');
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
        foreach ($conf['available_permission_levels'] as $level) {
            $level_options[$level] = $translator->trans(sprintf('Level %d', $level), [], 'admin');
        }
        $tpl_params['level_options'] = $level_options;
        $tpl_params['level_selected'] = $guestUser->getLevel();

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_users');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_users');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Users', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('list'), $tpl_params);

        return $this->render('users_list.html.twig', $tpl_params);
    }

    public function perm(Request $request, int $user_id, AlbumMapper $albumMapper, TranslatorInterface $translator, UserRepository $userRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $user = $userRepository->find($user_id);

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->get('cat_true') && count($request->request->get('cat_true')) > 0) {
                // if you forbid access to a category, all sub-categories become automatically forbidden
                foreach ($albumMapper->getRepository()->getSubAlbums($request->request->get('cat_true')) as $album) {
                    $album->removeUserAccess($user);
                }
                $albumMapper->getRepository()->addOrUpdateAlbum($album);
            } elseif ($request->request->get('trueify') && $request->request->get('cat_false') && count($request->request->get('cat_false')) > 0) {
                $albumMapper->addPermissionOnAlbum($request->request->get('cat_false'), [$user_id]);
            }
        }

        $tpl_params['TITLE'] = $translator->trans('Manage permissions for user "{user}"', ['user' => $user->getUsername()], 'admin');
        $tpl_params['L_CAT_OPTIONS_TRUE'] = $translator->trans('Authorized', [], 'admin');
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $translator->trans('Forbidden', [], 'admin');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_user_perm', ['user_id' => $user_id]);

        // retrieve category ids authorized to the groups the user belongs to
        $group_authorized = [];
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
        foreach ($albumMapper->getRepository()->findUnauthorized(array_merge($authorized_ids, $group_authorized)) as $album) {
            $albums[] = $album;
        }
        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, [], 'category_option_false'));

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_user_perm', ['user_id' => $user_id]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_users');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Users', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('perm', $user_id), $tpl_params);

        return $this->render('user_perm.html.twig', $tpl_params);
    }
}
