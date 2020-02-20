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
use App\DataMapper\ImageMapper;
use App\DataMapper\UserMapper;
use App\Events\GroupEvent;
use App\Repository\CategoryRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\GroupRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\SiteRepository;
use App\Repository\UserAccessRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'properties', int $album_id, int $parent_id = null): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('properties', $this->translator->trans('Properties', [], 'admin'), $this->generateUrl('admin_album', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-pencil');
        $tabsheet->add('sort_order', $this->translator->trans('Manage photo ranks', [], 'admin'), $this->generateUrl('admin_album_sort_order', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-random');
        $tabsheet->add('permissions', $this->translator->trans('Permissions', [], 'admin'), $this->generateUrl('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-lock');
        $tabsheet->add('notification', $this->translator->trans('Notification', [], 'admin'), $this->generateUrl('admin_album_notification', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-envelope');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function properties(Request $request, int $album_id, int $parent_id = null, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                                ImageStandardParams $image_std_params, CategoryMapper $categoryMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($request->request->get('submit')) {
                $data = ['id' => $album_id, 'name' => '', 'comment' => ''];

                if ($request->request->get('name')) {
                    $data['name'] = $request->request->get('name');
                }

                if ($request->request->get('comment')) {
                    $data['comment'] = $conf['allow_html_descriptions'] ? $request->request->get('comment') : htmlentities($request->request->get('comment'), ENT_QUOTES, 'utf-8');
                }

                if ($conf['activate_comments']) {
                    $data['commentable'] = $request->request->get('commentable') ? $em->getConnection()->get_boolean($request->request->get('commentable')) : false;
                }

                $em->getRepository(CategoryRepository::class)->updateCategory($data, $data['id']);
                if ($request->request->get('apply_commentable_on_sub')) {
                    $subcats = $em->getRepository(CategoryRepository::class)->getSubcatIds(['id' => $data['id']]);
                    $em->getRepository(CategoryRepository::class)->updateCategories(['commentable' => $data['commentable']], $subcats);
                }

                // retrieve cat infos before continuing (following updates are expensive)
                $cat_info = $categoryMapper->getCatInfo($album_id);

                if ($request->request->get('visible')) {
                    if ($request->request->get('visible') === 'true_sub') {
                        $categoryMapper->setCatVisible([$album_id], true, true);
                    } elseif ($cat_info['visible'] != $em->getConnection()->get_boolean($request->request->get('visible'))) {
                        $categoryMapper->setCatVisible([$album_id], $em->getConnection()->get_boolean($request->request->get('visible')));
                    }
                }

                $parent = $request->request->get('parent');
                // only move virtual albums
                if (empty($cat_info['dir']) && $cat_info['id_uppercat'] !== $parent) {
                    $categoryMapper->moveCategories([$album_id], $parent);
                }

                $this->addFlash('info', $translator->trans('Album updated successfully', [], 'admin'));
            } elseif ($request->request->get('set_random_representant')) {
                $categoryMapper->setRandomRepresentant([$album_id]);
            } elseif ($request->request->get('delete_representant')) {
                $em->getRepository(CategoryRepository::class)->updateCategory(['representative_picture_id' => null], $album_id);
            }

            return $this->redirectToRoute('admin_album', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        $category = $em->getRepository(CategoryRepository::class)->findById($album_id);
        foreach ($category as $k => $v) {
            if (!is_null($v) && $em->getConnection()->is_boolean($v)) {
                $category[$k] = $em->getConnection()->get_boolean($v);
            }
        }

        $category['is_virtual'] = empty($category['dir']) ? true : false;
        $result = $em->getRepository(ImageCategoryRepository::class)->findDistinctCategoryId($album_id);
        $category['has_images'] = $em->getConnection()->db_num_rows($result) > 0 ? true : false;

        $tpl_params['CATEGORIES_NAV'] = $categoryMapper->getAlbumsDisplayName($category['uppercats'], 'admin_album', ['parent_id' => $parent_id]);
        $tpl_params['CAT_ID'] = $album_id;
        $tpl_params['CAT_NAME'] = $category['name'];
        $tpl_params['CAT_COMMENT'] = $category['comment'];
        $tpl_params['CAT_VISIBLE'] = $em->getConnection()->boolean_to_string($category['visible']);
        $tpl_params['U_JUMPTO'] = $this->generateUrl('album', ['category_id' => $category['id']]);
        $tpl_params['U_ADD_PHOTOS_ALBUM'] = $this->generateUrl('admin_photos_add', ['album_id' => $category['id']]);
        $tpl_params['U_CHILDREN'] = $this->generateUrl('admin_albums', ['parent_id' => $category['id']]);

        if ($conf['activate_comments']) {
            $tpl_params['CAT_COMMENTABLE'] = $em->getConnection()->boolean_to_string($category['commentable']);
        }

        if ($category['has_images']) {
            $tpl_params['U_MANAGE_ELEMENTS'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'album', 'value' => $category['id']]);

            $result = $em->getRepository(ImageRepository::class)->getImagesInfosInCategory($category['id']);
            list($image_count, $min_date, $max_date) = $em->getConnection()->db_fetch_row($result);

            if ($min_date === $max_date) {
                $tpl_params['INTRO'] = $translator->trans(
                    'This album contains {count} photos, added on {date}.',
                    [
                        'count' => $image_count,
                        'date' => (new \DateTime($min_date))->format('l d M Y')
                    ]
                );
            } else {
                $tpl_params['INTRO'] = $translator->trans(
                    'This album contains {count} photos, added between {min_date} and {max_date}.',
                    [
                        'count' => $image_count,
                        'min_date' => (new \DateTime($min_date))->format('l d M Y'),
                        'max_date' => (new \DateTime($max_date))->format('l d M Y')
                    ],
                    'admin'
                    );
            }
        } else {
            $tpl_params['INTRO'] = $translator->trans('This album contains no photo.', [], 'admin');
        }

        if ($category['is_virtual']) {
            $tpl_params['U_DELETE'] = $this->generateUrl('admin_album_delete', ['album_id' => $category['id'], 'parent_id' => $parent_id]);
            $tpl_params['parent_category'] = empty($category['id_uppercat']) ? [] : [$category['id_uppercat']];
        } else {
            $result = $em->getRepository(SiteRepository::class)->getSiteUrl($album_id);
            $row = $em->getConnection()->db_fetch_assoc($result);

            $uppercats = '';
            $local_dir = '';
            $result = $em->getRepository(CategoryRepository::class)->findById($album_id);
            $row = $em->getConnection()->db_fetch_assoc($result);
            $uppercats = $row['uppercats'];
            $upper_array = explode(',', $uppercats);
            $database_dirs = [];
            $result = $em->getRepository(CategoryRepository::class)->findByIds($uppercats);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $database_dirs[$row['id']] = $row['dir'];
            }
            foreach ($upper_array as $id) {
                $local_dir .= $database_dirs[$id] . '/';
            }

            $category['cat_full_dir'] = $row['galleries_url'] . $local_dir;
            $tpl_params['CAT_FULL_DIR'] = preg_replace('/\/$/', '', $category['cat_full_dir']);

            if ($conf['enable_synchronization']) {
                $tpl_params['U_SYNC'] = $this->generateUrl('admin_site_update', ['album_id' => $category['id']]);
            }
        }

        if ($category['has_images'] || !empty($category['representative_picture_id'])) {
            $tpl_params['representant'] = [];

            // picture to display : the identified representant or the generic random representant ?
            if (!empty($category['representative_picture_id'])) {
                $result = $em->getRepository(ImageRepository::class)->findById($this->getUser(), [], $category['representative_picture_id']);
                $row = $em->getConnection()->db_fetch_assoc($result);
                $src = (new DerivativeImage(new SrcImage($row, $conf['picture_ext']), $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl();
                $url = $this->generateUrl('admin_photo', ['image_id' => $category['representative_picture_id']]);

                $tpl_params['representant']['picture'] =
                    [
                        'SRC' => $src,
                        'URL' => $url
                    ];
            }

            // can the admin choose to set a new random representant ?
            $tpl_params['representant']['ALLOW_SET_RANDOM'] = $category['has_images'] && $conf['allow_random_representative'];

            // can the admin delete the current representant ?
            if (($category['has_images'] && $conf['allow_random_representative']) || (!$category['has_images'] && !empty($category['representative_picture_id']))) {
                $tpl_params['representant']['ALLOW_DELETE'] = true;
            }
        }

        $tpl_params['CATEGORIES_NAV'] = $categoryMapper->getAlbumsDisplayName($category['uppercats'], 'admin_album', ['parent_id' => $parent_id]);

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_album', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('properties', $album_id, $parent_id), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('album_properties.tpl', $tpl_params);
    }

    public function sort_order(Request $request, int $album_id, int $parent_id = null, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                                CategoryMapper $categoryMapper, ImageStandardParams $image_std_params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $image_order_choices = ['default', 'rank', 'user_define'];
        $image_order_choice = 'default';

        if ($request->isMethod('POST')) {
            if ($request->request->get('rank_of_image') && $request->request->get('image_order_choice') === 'manual') {
                $rank_of_image = $request->request->get('rank_of_image');
                asort($rank_of_image, SORT_NUMERIC);

                $current_rank = 0;
                $datas = [];
                print_r($rank_of_image);
                foreach (array_keys($rank_of_image) as $id) {
                    $datas[] = [
                        'category_id' => $album_id,
                        'image_id' => $id,
                        'rank' => ++$current_rank,
                    ];
                }

                $fields = [
                    'primary' => ['image_id', 'category_id'],
                    'update' => ['rank']
                ];

                $em->getRepository(ImageCategoryRepository::class)->massUpdates($fields, $datas);

                $this->addFlash('info', $translator->trans('Images manual order was saved', [], 'admin'));

                return $this->redirectToRoute('admin_album_sort_order', ['album_id' => $album_id, 'parent_id' => $parent_id]);
            }

            if ($request->request->get('image_order_choice') && in_array($request->request->get('image_order_choice'), $image_order_choices)) {
                $image_order_choice = $request->request->get('image_order_choice');
            }

            $image_order = null;
            if ($image_order_choice === 'user_define') {
                for ($i = 0; $i < 3; $i++) {
                    if ($request->request->get('image_order')[$i]) {
                        if (!empty($image_order)) {
                            $image_order .= ',';
                        }
                        $image_order .= $request->request->get('image_order')[$i];
                    }
                }
            } elseif ($image_order_choice === 'rank') {
                $image_order = 'rank ASC';
            }
            $em->getRepository(CategoryRepository::class)->updateCategory(['image_order' => $image_order], $album_id);

            if ($request->request->get('image_order_subcats')) {
                $cat_info = $categoryMapper->getCatInfo($album_id);

                $em->getRepository(CategoryRepository::class)->updateByUppercats(['image_order' => $image_order], $cat_info['uppercats']);
            }

            $this->addFlash('info', $translator->trans('Your configuration settings are saved', [], 'admin'));

            return $this->redirectToRoute('admin_album_sort_order', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        $category = $em->getRepository(CategoryRepository::class)->findById($album_id);

        if ($category['image_order'] === 'rank ASC') {
            $image_order_choice = 'rank';
        } elseif ($category['image_order'] != '') {
            $image_order_choice = 'user_define';
        }

        $tpl_params['CATEGORIES_NAV'] = $categoryMapper->getAlbumsDisplayName($category['uppercats'], 'admin_album', ['parent_id' => $parent_id]);

        $result = $em->getRepository(ImageRepository::class)->findImagesInCategory($album_id, 'ORDER BY RANK');
        if ($em->getConnection()->db_num_rows($result) > 0) {
            // template thumbnail initialization
            $current_rank = 1;
            $derivativeParams = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $derivative = new DerivativeImage(new SrcImage($row, $conf['picture_ext']), $derivativeParams, $image_std_params);

                if (!empty($row['name'])) {
                    $thumbnail_name = $row['name'];
                } else {
                    $file_wo_ext = \Phyxo\Functions\Utils::get_filename_wo_extension($row['file']);
                    $thumbnail_name = str_replace('_', ' ', $file_wo_ext);
                }
                $current_rank++;
                $tpl_params['thumbnails'][] = [
                    'ID' => $row['id'],
                    'NAME' => $thumbnail_name,
                    'TN_SRC' => $derivative->getUrl(),
                    'RANK' => $current_rank * 10,
                    'SIZE' => $derivative->get_size(),
                ];
            }
        }

        $tpl_params['image_order_options'] = [
            '' => '',
            'file ASC' => $translator->trans('File name, A &rarr; Z', [], 'admin'),
            'file DESC' => $translator->trans('File name, Z &rarr; A', [], 'admin'),
            'name ASC' => $translator->trans('Photo title, A &rarr; Z', [], 'admin'),
            'name DESC' => $translator->trans('Photo title, Z &rarr; A', [], 'admin'),
            'date_creation DESC' => $translator->trans('Date created, new &rarr; old', [], 'admin'),
            'date_creation ASC' => $translator->trans('Date created, old &rarr; new', [], 'admin'),
            'date_available DESC' => $translator->trans('Date posted, new &rarr; old', [], 'admin'),
            'date_available ASC' => $translator->trans('Date posted, old &rarr; new', [], 'admin'),
            'rating_score DESC' => $translator->trans('Rating score, high &rarr; low', [], 'admin'),
            'rating_score ASC' => $translator->trans('Rating score, low &rarr; high', [], 'admin'),
            'hit DESC' => $translator->trans('Visits, high &rarr; low', [], 'admin'),
            'hit ASC' => $translator->trans('Visits, low &rarr; high', [], 'admin'),
            'id ASC' => $translator->trans('Numeric identifier, 1 &rarr; 9', [], 'admin'),
            'id DESC' => $translator->trans('Numeric identifier, 9 &rarr; 1', [], 'admin'),
            'rank ASC' => $translator->trans('Manual sort order', [], 'admin'),
        ];

        $image_order = explode(',', $category['image_order']);
        for ($i = 0; $i < 3; $i++) { // 3 fields
            if (isset($image_order[$i])) {
                $tpl_params['image_order'][] = $image_order[$i];
            } else {
                $tpl_params['image_order'][] = '';
            }
        }

        $tpl_params['image_order_choice'] = $image_order_choice;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('sort_order', $album_id, $parent_id), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('album_sort_order.tpl', $tpl_params);
    }

    public function permissions(Request $request, int $album_id, int $parent_id = null, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                                CategoryMapper $categoryMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $category = $em->getRepository(CategoryRepository::class)->findById($album_id);
        foreach ($category as $k => $v) {
            if (!is_null($v) && $em->getConnection()->is_boolean($v)) {
                $category[$k] = $em->getConnection()->get_boolean($v);
            }
        }

        if ($request->isMethod('POST')) {
            if ($category['status'] !== $request->request->get('status')) {
                $categoryMapper->setCatStatus([$album_id], $request->request->get('status'));
                $category['status'] = $request->request->get('status');
            }

            if ($request->request->get('status') === 'private') {
                // manage groups
                $result = $em->getRepository(GroupAccessRepository::class)->findByCatId($album_id);
                $groups_granted = $em->getConnection()->result2array($result, null, 'group_id');

                if (!$request->request->get('groups')) {
                    $groups = [];
                } else {
                    $groups = $request->request->get('groups');
                }

                // remove permissions to groups
                $deny_groups = array_diff($groups_granted, $groups);
                if (count($deny_groups) > 0) {
                    // if you forbid access to an album, all sub-albums become
                    // automatically forbidden
                    $em->getRepository(GroupAccessRepository::class)->deleteByGroupIdsAndCatIds($deny_groups, $em->getRepository(CategoryRepository::class)->getSubcatIds([$album_id]));
                }

                // add permissions to groups
                $grant_groups = $groups;
                if (count($grant_groups) > 0) {
                    $cat_ids = $categoryMapper->getUppercatIds([$album_id]);
                    if ($request->request->get('apply_on_sub')) {
                        $cat_ids = array_merge($cat_ids, $em->getRepository(CategoryRepository::class)->getSubcatIds([$album_id]));
                    }

                    $result = $em->getRepository(CategoryRepository::class)->findByIds($cat_ids, 'private');
                    $private_cats = $em->getConnection()->result2array($result, null, 'id');

                    $inserts = [];
                    foreach ($private_cats as $cat_id) {
                        foreach ($grant_groups as $group_id) {
                            $inserts[] = [
                                'group_id' => $group_id,
                                'cat_id' => $cat_id
                            ];
                        }
                    }

                    $em->getRepository(GroupAccessRepository::class)->massInserts(['group_id', 'cat_id'], $inserts, ['ignore' => true]);
                }

                // users
                $result = $em->getRepository(UserAccessRepository::class)->findByCatId($album_id);
                $users_granted = $em->getConnection()->result2array($result, null, 'user_id');

                if (!$request->request->get('users')) {
                    $users = [];
                } else {
                    $users = $request->request->get('users');
                }

                // remove permissions to users
                $deny_users = array_diff($users_granted, $users);
                if (count($deny_users) > 0) {
                    // if you forbid access to an album, all sub-album become automatically forbidden
                    $em->getRepository(UserAccessRepository::class)->deleteByUserIdsAndCatIds($deny_users, $em->getRepository(CategoryRepository::class)->getSubcatIds([$album_id]));
                }

                // add permissions to users
                $grant_users = $users;
                if (count($grant_users) > 0) {
                    $categoryMapper->addPermissionOnCategory([$album_id], $grant_users);
                }
            }

            $this->addFlash('info', $translator->trans('Album updated successfully', [], 'admin'));

            return $this->redirectToRoute('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        $result = $em->getRepository(GroupRepository::class)->findAll('ORDER BY name ASC');
        $tpl_params['groups'] = $em->getConnection()->result2array($result, 'id', 'name');

        // groups granted to access the category
        $result = $em->getRepository(GroupAccessRepository::class)->findByCatId($album_id);
        $tpl_params['groups_selected'] = $em->getConnection()->result2array($result, null, 'group_id');

        // users...
        $result = $em->getRepository(UserRepository::class)->findAll();
        $tpl_params['users'] = $em->getConnection()->result2array($result, 'id', 'username');

        $result = $em->getRepository(UserAccessRepository::class)->findByCatId($album_id);
        $tpl_params['users_selected'] = $em->getConnection()->result2array($result, null, 'user_id');

        $user_granted_indirect_ids = [];
        if (count($tpl_params['groups_selected']) > 0) {
            $granted_groups = [];

            $result = $em->getRepository(UserGroupRepository::class)->findByGroupIds($tpl_params['groups_selected']);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                if (!isset($granted_groups[$row['group_id']])) {
                    $granted_groups[$row['group_id']] = [];
                }
                $granted_groups[$row['group_id']][] = $row['user_id'];
            }

            $user_granted_by_group_ids = [];

            foreach ($granted_groups as $group_users) {
                $user_granted_by_group_ids = array_merge($user_granted_by_group_ids, $group_users);
            }

            $user_granted_by_group_ids = array_unique($user_granted_by_group_ids);
            $user_granted_indirect_ids = array_diff($user_granted_by_group_ids, $tpl_params['users_selected']);

            $tpl_params['nb_users_granted_indirect'] = count($user_granted_indirect_ids);
            foreach ($granted_groups as $group_id => $group_users) {
                $group_usernames = [];
                foreach ($group_users as $user_id) {
                    if (in_array($user_id, $user_granted_indirect_ids)) {
                        $group_usernames[] = $tpl_params['users'][$user_id];
                    }
                }

                $tpl_params['user_granted_indirect_groups'][] = [
                    'group_name' => $tpl_params['groups'][$group_id],
                    'group_users' => implode(', ', $group_usernames),
                ];
            }
        }

        $tpl_params['CATEGORIES_NAV'] = $categoryMapper->getAlbumsDisplayName($category['uppercats'], 'admin_album', ['parent_id' => $parent_id]);
        $tpl_params['U_GROUPS'] = $this->generateUrl('admin_groups');
        $tpl_params['CACHE_KEYS'] = \Phyxo\Functions\Utils::getAdminClientCacheKeys(['groups', 'users'], $em, $this->generateUrl('homepage'));

        $tpl_params['private'] = ($category['status'] === 'private');
        $tpl_params['INHERIT'] = $conf['inheritance_by_default'];
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('permissions', $album_id, $parent_id), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('album_permissions.tpl', $tpl_params);
    }

    public function notification(Request $request, int $album_id, int $parent_id = null, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                                CategoryMapper $categoryMapper, ImageStandardParams $image_std_params, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $category = $em->getRepository(CategoryRepository::class)->findById($album_id);
        foreach ($category as $k => $v) {
            if (!is_null($v) && $em->getConnection()->is_boolean($v)) {
                $category[$k] = $em->getConnection()->get_boolean($v);
            }
        }

        if ($request->isMethod('POST')) {
            // @TODO: if $category['representative_picture_id'] is empty find child representative_picture_id
            if (!empty($category['representative_picture_id'])) {
                $result = $em->getRepository(ImageRepository::class)->findById($this->getUser(), [], $category['representative_picture_id']);
                if ($em->getConnection()->db_num_rows($result) > 0) {
                    $element = $em->getConnection()->db_fetch_assoc($result);
                    $src_image = new SrcImage($element, $conf['picture_ext']);

                    $img_url = '<a href="' . $this->generateUrl('picture', ['image_id' => $element['id'], 'type' => 'category', 'element_id' => $category['id']]);
                    $img_url .= '" class="thumblnk"><img src="' . (new DerivativeImage($src_image, $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl() . '"></a>';
                }
            }

            if (!isset($img_url)) {
                $img_url = '';
            }

            $eventDispatcher->dispatch(new GroupEvent((int) $request->request->get('group'), ['id' => $category['id'], 'name' => $category['name']], $img_url, $request->request->get('mail_content')));

            $result = $em->getRepository(GroupRepository::class)->findById($request->request->get('group'));
            $row = $em->getConnection()->db_fetch_assoc($result);

            $this->addFlash('info', $translator->trans('An information email was sent to group "{group}"', ['group' => $row['name']], 'admin'));

            return $this->redirectToRoute('admin_album_notification', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        $result = $em->getRepository(GroupRepository::class)->findAll();
        $all_group_ids = $em->getConnection()->result2array($result, null, 'id');

        if (count($all_group_ids) === 0) {
            $tpl_params['no_group_in_gallery'] = true;
        } else {
            if ($category['status'] === 'private') {
                $result = $em->getRepository(GroupAccessRepository::class)->findByCatId($album_id);
                $group_ids = $em->getConnection()->result2array($result, null, 'group_id');

                if (count($group_ids) === 0) {
                    $tpl_params['permission_url'] = $this->generateUrl('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]);
                }
            } else {
                $group_ids = $all_group_ids;
            }

            if (count($group_ids) > 0) {
                $result = $em->getRepository(GroupRepository::class)->findByIds($group_ids, 'ORDER BY name ASC');
                $tpl_params['group_mail_options'] = $em->getConnection()->result2array($result, 'id', 'name');
            }
        }

        $tpl_params['CATEGORIES_NAV'] = $categoryMapper->getAlbumsDisplayName($category['uppercats'], 'admin_album', ['parent_id' => $parent_id]);
        $tpl_params['U_GROUPS'] = $this->generateUrl('admin_groups');

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('notification', $album_id, $parent_id), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('album_notification.tpl', $tpl_params);
    }

    public function create(Request $request, int $parent_id = null, CategoryMapper $categoryMapper, UserMapper $userMapper)
    {
        if ($request->isMethod('POST')) {
            $virtual_name = $request->request->get('virtual_name');

            $output_create = $categoryMapper->createVirtualCategory($virtual_name, $parent_id, $this->getUser()->getId());

            $userMapper->invalidateUserCache();
            if (isset($output_create['error'])) {
                $this->addFlash('error', $output_create['error']);
            } else {
                $this->addFlash('info', $output_create['info']);
            }
        }

        return  $this->redirectToRoute('admin_albums', ['parent_id' => $parent_id]);
    }

    public function delete(int $album_id, int $parent_id = null, EntityManager $em, CategoryMapper $categoryMapper, ImageMapper $imageMapper, UserMapper $userMapper, TranslatorInterface $translator)
    {
        $categoryMapper->deleteCategories([$album_id]);

        // destruction of all photos physically linked to the category
        $result = $em->getRepository(ImageRepository::class)->findByFields('storage_category_id', [$album_id]);
        $element_ids = $em->getConnection()->result2array($result, null, 'id');
        $imageMapper->deleteElements($element_ids);

        $this->addFlash('info', $translator->trans('Virtual album deleted'));
        $categoryMapper->updateGlobalRank();
        $userMapper->invalidateUserCache();

        return $this->redirectToRoute('admin_albums', ['parent_id' => $parent_id]);
    }
}
