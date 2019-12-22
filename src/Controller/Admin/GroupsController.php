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
use App\Repository\CategoryRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class GroupsController extends AdminCommonController
{
    private $translator;

    public function setTabsheet(string $section = 'list', int $group_id = 0): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('list', $this->translator->trans('Groups', [], 'admin'), $this->generateUrl('admin_groups'), 'fa-group');
        $tabsheet->add('perm', $this->translator->trans('Permissions', [], 'admin'), $group_id !== 0 ? $this->generateUrl('admin_group_perm', ['group_id' => $group_id]) : null, 'fa-lock');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function list(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($groupname = $request->request->get('groupname')) {
                if ($em->getRepository(GroupRepository::class)->isGroupNameExists($groupname)) {
                    $this->addFlash('error', $translator->trans('This name is already used by another group.', [], 'admin'));
                } else {
                    $em->getRepository(GroupRepository::class)->addGroup(['name' => $groupname]);
                    $this->addFlash('info', $translator->trans('group "{group}" added', ['group' => $groupname], 'admin'));
                }
            } else {
                $this->addFlash('error', $translator->trans('The name of a group must not be empty.', [], 'admin'));
            }
            $this->redirectToRoute('admin_groups');
        }

        $groups = [];
        $result = $em->getRepository(GroupRepository::class)->findUsersInGroups();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            if (isset($groups[$row['id']])) {
                if (!empty($row['username'])) {
                    $groups[$row['id']]['MEMBERS'][] = $row['username'];
                }
            } else {
                $group = [
                    'MEMBERS' => [],
                    'ID' => $row['id'],
                    'NAME' => $row['name'],
                    'IS_DEFAULT' => ($em->getConnection()->get_boolean($row['is_default']) ? ' [' . $translator->trans('default', [], 'admin') . ']' : ''),
                    'U_PERM' => $this->generateUrl('admin_group_perm', ['group_id' => $row['id']]),
                ];
                if (!empty($row['username'])) {
                    $group['MEMBERS'][] = $row['username'];
                }
                $groups[$row['id']] = $group;
            }
        }
        $tpl_params['groups'] = $groups;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_groups');
        $tpl_params['F_ACTION_MERGE'] = $this->generateUrl('admin_groups_action', ['action' => 'merge']);
        $tpl_params['F_ACTION_DUPLICATE'] = $this->generateUrl('admin_groups_action', ['action' => 'duplicate']);
        $tpl_params['F_ACTION_DELETE'] = $this->generateUrl('admin_groups_action', ['action' => 'delete']);
        $tpl_params['F_ACTION_RENAME'] = $this->generateUrl('admin_groups_action', ['action' => 'rename']);
        $tpl_params['F_ACTION_TOGGLE_DEFAULT'] = $this->generateUrl('admin_groups_action', ['action' => 'toggle_default']);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Groups', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('list'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_groups');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('groups_list.tpl', $tpl_params);
    }

    public function perm(Request $request, int $group_id, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                        CategoryMapper $categoryMapper, UserMapper $userMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($request->request->get('falsify') && $request->request->get('cat_true') && count($request->request->get('cat_true')) > 0) {
                // if you forbid access to a category, all sub-categories become automatically forbidden
                $subcats = $em->getRepository(CategoryRepository::class)->getSubcatIds($request->request->get('cat_true'));
                $em->getRepository(GroupAccessRepository::class)->deleteByGroupIdsAndCatIds($group_id, $subcats);
            } elseif ($request->request->get('trueify') && $request->request->get('cat_false') && count($request->request->get('cat_false')) > 0) {
                $uppercats = $categoryMapper->getUppercatIds($request->request->get('cat_false'));
                $private_uppercats = [];

                $result = $em->getRepository(CategoryRepository::class)->findByIds($uppercats, 'private');
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $private_uppercats[] = $row['id'];
                }

                // retrying to authorize a category which is already authorized may cause
                // an error (in SQL statement), so we need to know which categories are accesible
                $authorized_ids = [];
                $result = $em->getRepository(GroupAccessRepository::class)->findByGroupId($group_id);
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $authorized_ids[] = $row['cat_id'];
                }

                $inserts = [];
                $to_autorize_ids = array_diff($private_uppercats, $authorized_ids);
                foreach ($to_autorize_ids as $to_autorize_id) {
                    $inserts[] = [
                        'group_id' => $group_id,
                        'cat_id' => $to_autorize_id
                    ];
                }

                $em->getRepository(GroupAccessRepository::class)->massInserts(['group_id', 'cat_id'], $inserts);
                $userMapper->invalidateUserCache();
            }
        }

        $result = $em->getRepository(GroupRepository::class)->findById($group_id);
        if ($em->getConnection()->db_num_rows($result) > 0) {
            $row = $em->getConnection()->db_fetch_assoc($result);

            $groupname = $row['name'];
        } else {
            $groupname = '';
        }

        $tpl_params['TITLE'] = $translator->trans('Manage permissions for group "{group}"', ['group' => $groupname], 'admin');
        $tpl_params['L_CAT_OPTIONS_TRUE'] = $translator->trans('Authorized', [], 'admin');
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $translator->trans('Forbidden', [], 'admin');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_group_perm', ['group_id' => $group_id]);

        // only private categories are listed
        $result = $em->getRepository(CategoryRepository::class)->findWithGroupAccess($group_id);
        $categories = $em->getConnection()->result2array($result);
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_option_true'));

        $authorized_ids = [];
        foreach ($categories as $category) {
            $authorized_ids[] = $category['id'];
        }

        $result = $em->getRepository(CategoryRepository::class)->findUnauthorized($authorized_ids);
        $categories = $em->getConnection()->result2array($result);
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_option_false'));

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_groups');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Groups', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('perm', $group_id), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_groups');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('groups_perm.tpl', $tpl_params);
    }

    public function action(Request $request, string $action, EntityManager $em, TranslatorInterface $translator)
    {
        $groups = $request->request->get('group_selection');
        if (count($groups) === 0) {
            $this->addFlash('error', $translator->trans('Select at least one group', [], 'admin'));

            return $this->redirectToRoute('admin_groups');
        }

        if ($action === 'rename') {
            // is the group not already existing ?
            $result = $em->getRepository(GroupRepository::class)->findAll();
            $group_names = $em->getConnection()->result2array($result, null, 'name');
            foreach ($groups as $group) {
                if (in_array($request->request->get('rename_' . $group), $group_names)) {
                    $this->addFlash('error', $request->request->get('rename_' . $group) . ' | ' . $translator->trans('This name is already used by another group.', [], 'admin'));
                } elseif ($rename_group = $request->request->get('rename_' . $group)) {
                    $em->getRepository(GroupRepository::class)->updateGroup(['name' => $rename_group], $group);
                }
            }

            return $this->redirectToRoute('admin_groups');
        } elseif ($action === 'delete' && $request->request->get('confirm_deletion')) {
            // destruction of the access linked to the group
            $em->getRepository(GroupAccessRepository::class)->deleteByGroupIds($groups);

            // destruction of the users links for this group
            $em->getRepository(UserGroupRepository::class)->deleteByGroupIds($groups);

            $result = $em->getRepository(GroupRepository::class)->findByIds($groups);
            $groupnames = $em->getConnection()->result2array($result, null, 'name');

            // destruction of the group
            $em->getRepository(GroupRepository::class)->deleteByIds($groups);

            $this->addFlash('info', $translator->trans('groups "{groups}" deleted', ['groups' => implode(', ', $groupnames)], 'admin'));

            return $this->redirectToRoute('admin_groups');
        } elseif ($action === 'merge' && count($groups) > 1) {
            if ($em->getRepository(GroupRepository::class)->isGroupNameExists($request->request->get('merge'))) {
                $this->addFlash('error', $translator->trans('This name is already used by another group.', [], 'admin'));

                $this->redirectToRoute('admin_groups');
            } else {
                $group_id = $em->getRepository(GroupRepository::class)->addGroup(['name' => $request->request->get('merge')]);
            }

            $grp_access = [];
            $usr_grp = [];
            $result = $em->getRepository(GroupAccessRepository::class)->findByGroupIds($groups);
            $groups_infos = $em->getConnection()->result2array($result);
            foreach ($groups_infos as $group) {
                $new_grp_access = [
                    'cat_id' => $group['cat_id'],
                    'group_id' => $group_id
                ];
                if (!in_array($new_grp_access, $grp_access)) {
                    $grp_access[] = $new_grp_access;
                }
            }

            $result = $em->getRepository(GroupAccessRepository::class)->findByGroupIds($groups);
            $groups_infos = $em->getConnection()->result2array($result);
            foreach ($groups_infos as $group) {
                $new_grp_access = [
                    'cat_id' => $group['cat_id'],
                    'group_id' => $group_id
                ];
                if (!in_array($new_grp_access, $grp_access)) {
                    $grp_access[] = $new_grp_access;
                }
            }

            $em->getRepository(UserGroupRepository::class)->massInserts(['user_id', 'group_id'], $usr_grp);
            $em->getRepository(GroupAccessRepository::class)->massInserts(['group_id', 'cat_id'], $grp_access);
            $this->addFlash('info', $translator->trans('group "{group}" added', ['group' => $request->request->get('merge')], 'admin'));

            return $this->redirectToRoute('admin_groups');
        } elseif ($action === 'duplicate') {
            // @TODO: avoid query in loop
            foreach ($groups as $group) {
                if (!$request->request->get('duplicate_' . $group)) {
                    break;
                }

                if ($em->getRepository(GroupRepository::class)->isGroupNameExists($request->request->get('duplicate_' . $group))) {
                    $this->addFlash('error', $translator->trans('This name is already used by another group.', [], 'admin'));
                    break;
                }

                $group_id = $em->getRepository(GroupRepository::class)->addGroup(['name' => $request->request->get('duplicate_' . $group)]);

                $grp_access = [];
                $result = $em->getRepository(GroupAccessRepository::class)->findByGroupId($group);
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $grp_access[] = [
                        'cat_id' => $row['cat_id'],
                        'group_id' => $group_id
                    ];
                }
                $em->getRepository(GroupAccessRepository::class)->massInserts(['group_id', 'cat_id'], $grp_access);

                $usr_grp = [];
                $result = $em->getRepository(UserGroupRepository::class)->findByGroupId($group);
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $usr_grp[] = [
                        'user_id' => $row['user_id'],
                        'group_id' => $group_id
                    ];
                }
                $em->getRepository(UserGroupRepository::class)->massInserts(['user_id', 'group_id'], $usr_grp);

                $this->addFlash('info', $translator->trans('group "{group}" added', ['group' => $request->request->get('duplicate_' . $group)], 'admin'));

                return $this->redirectToRoute('admin_groups');
            }
        } elseif ($action === 'toggle_default') {
            $em->getRepository(GroupRepository::class)->toggleIsDefault($groups);

            $this->addFlash('info', $translator->trans('groups "{groups}" updated', ['groups' => implode(', ', $groups)], 'admin'));

            return $this->redirectToRoute('admin_groups');
        }

        return $this->redirectToRoute('admin_groups');
    }
}
