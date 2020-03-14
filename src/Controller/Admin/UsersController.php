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

use App\DataMapper\CategoryMapper;
use App\DataMapper\UserMapper;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\GroupRepository;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserAccessRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersController extends AdminCommonController
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

    public function list(Request $request, EntityManager $em, Conf $conf, UserMapper $userMapper, ParameterBagInterface $params,
                        CsrfTokenManagerInterface $csrfTokenManager, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $groups = [];

        $result = $em->getRepository(GroupRepository::class)->findAll('ORDER BY name ASC');
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $groups[$row['id']] = $row['name'];
        }

        $result = $em->getRepository(UserRepository::class)->getUserInfosList();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $users[] = $row;
            $user_ids[] = $row['id'];
        }

        $tpl_params['users'] = $users;
        $tpl_params['all_users'] = join(',', $user_ids);
        $tpl_params['ACTIVATE_COMMENTS'] = $conf['activate_comments'];
        $tpl_params['Double_Password'] = $conf['double_password_type_in_admin'];

        $default_user = $userMapper->getDefaultUserInfo(true);

        $protected_users = [$this->getUser()->getId(), $conf['guest_id'], $conf['default_user_id'], $conf['webmaster_id']];

        // an admin can't delete other admin/webmaster
        if ($userMapper->isAdmin()) {
            $result = $em->getRepository(UserInfosRepository::class)->findByStatuses(['webmaster', 'admin']);
            $protected_users = array_merge($protected_users, $em->getConnection()->result2array($result, null, 'user_id'));
        }

        $result = $em->getRepository(ThemeRepository::class)->findAll();
        $themes = $em->getConnection()->result2array($result, 'id', 'name');

        $result = $em->getRepository(LanguageRepository::class)->findAll();
        $languages = $em->getConnection()->result2array($result, 'id', 'name');

        $dummy_user = 9999;
        $tpl_params = array_merge($tpl_params, [
            'F_ADD_ACTION' => $this->generateUrl('admin_users'),
            'F_USER_PERM' => $this->generateUrl('admin_user_perm', ['user_id' => $dummy_user]),
            'F_USER_PERM_DUMMY_USER' => $dummy_user,
            'NB_IMAGE_PAGE' => $default_user['nb_image_page'],
            'RECENT_PERIOD' => $default_user['recent_period'],
            'theme_options' => $themes,
            'theme_selected' => $userMapper->getDefaultTheme(),
            'language_options' => $languages,
            'language_selected' => $userMapper->getDefaultLanguage(),
            'association_options' => $groups,
            'protected_users' => implode(',', array_unique($protected_users)),
            'guest_user' => $conf['guest_id'],
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
        foreach ($conf['available_permission_levels'] as $level) {
            $level_options[$level] = $translator->trans(sprintf('Level %d', $level), [], 'admin');
        }
        $tpl_params['level_options'] = $level_options;
        $tpl_params['level_selected'] = $default_user['level'];

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_users');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_users');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Users', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('list'), $tpl_params);
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('users_list.html.twig', $tpl_params);
    }

    public function perm(Request $request, int $user_id, EntityManager $em, UserMapper $userMapper, CategoryMapper $categoryMapper, Conf $conf,
                        ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->get('cat_true') && count($request->request->get('cat_true')) > 0) {
                // if you forbid access to a category, all sub-categories become automatically forbidden
                $subcats = $em->getRepository(CategoryRepository::class)->getSubcatIds($request->request->get('cat_true'));
                $em->getRepository(UserAccessRepository::class)->deleteByUserIdsAndCatIds([$user_id], $subcats);
            } elseif ($request->request->get('trueify') && $request->request->get('cat_false') && count($request->request->get('cat_false')) > 0) {
                $categoryMapper->addPermissionOnCategory($request->request->get('cat_false'), [$user_id]);
            }
        }

        $tpl_params['TITLE'] = $translator->trans('Manage permissions for user "{user}"', ['user' => $userMapper->getUsernameFromId($user_id)], 'admin');
        $tpl_params['L_CAT_OPTIONS_TRUE'] = $translator->trans('Authorized', [], 'admin');
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $translator->trans('Forbidden', [], 'admin');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_user_perm', ['user_id' => $user_id]);

        // retrieve category ids authorized to the groups the user belongs to
        $group_authorized = [];

        $result = $em->getRepository(GroupAccessRepository::class)->findCategoriesAuthorizedToUser($user_id);
        if ($em->getConnection()->db_num_rows($result) > 0) {
            $cats = [];
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $cats[] = $row;
                $group_authorized[] = $row['cat_id'];
            }
            usort($cats, '\Phyxo\Functions\Utils::global_rank_compare');

            foreach ($cats as $category) {
                $tpl_params['categories_because_of_groups'][] = $categoryMapper->getCatDisplayNameCache($category['uppercats']);
            }
        }

        // only private categories are listed
        $result = $em->getRepository(CategoryRepository::class)->findWithUserAccess($user_id, $group_authorized);
        $categories = $em->getConnection()->result2array($result);
        // displaySelectCategoriesWrapper à adapter pour qu'il renvoie un tableau plutotu qu'il modifie le template direct
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_option_true'));
        $authorized_ids = [];
        foreach ($categories as $category) {
            $authorized_ids[] = $category['id'];
        }

        $result = $em->getRepository(CategoryRepository::class)->findUnauthorized(array_merge($authorized_ids, $group_authorized));
        $categories = $em->getConnection()->result2array($result);
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_option_false'));

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_user_perm', ['user_id' => $user_id]);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_users');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Users', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('perm', $user_id), $tpl_params);

        return $this->render('user_perm.html.twig', $tpl_params);
    }
}
