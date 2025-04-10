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
use App\DataMapper\ImageMapper;
use App\DataMapper\UserMapper;
use App\Entity\Album;
use App\Enum\ImageSizeType;
use App\Enum\UserStatusType;
use App\Events\GroupEvent;
use App\Repository\GroupRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\UserCacheRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserRepository;
use App\Security\AppUserService;
use Doctrine\Persistence\ManagerRegistry;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminAlbumController extends AbstractController
{
    private TranslatorInterface $translator;

    protected function setTabsheet(int $album_id, string $section = 'properties', ?int $parent_id = null): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('properties', $this->translator->trans('Properties', [], 'admin'), $this->generateUrl('admin_album', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-pencil');
        $tabsheet->add('sort_order', $this->translator->trans('Manage photo ranks', [], 'admin'), $this->generateUrl('admin_album_sort_order', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-random');
        $tabsheet->add('permissions', $this->translator->trans('Permissions', [], 'admin'), $this->generateUrl('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-lock');
        $tabsheet->add('notification', $this->translator->trans('Notification', [], 'admin'), $this->generateUrl('admin_album_notification', ['album_id' => $album_id, 'parent_id' => $parent_id]), 'fa-envelope');
        $tabsheet->select($section);

        return $tabsheet;
    }

    #[Route('/admin/album/{album_id}/edit/{parent_id}', name: 'admin_album', defaults: ['parent_id' => null], requirements: ['parent_id' => '\d+', 'album_id' => '\d+'])]
    public function properties(
        Request $request,
        int $album_id,
        Conf $conf,
        ImageStandardParams $image_std_params,
        AlbumMapper $albumMapper,
        TranslatorInterface $translator,
        ImageAlbumRepository $imageAlbumRepository,
        ImageMapper $imageMapper,
        ManagerRegistry $managerRegistry,
        ?int $parent_id = null,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $album = $albumMapper->getRepository()->find($album_id);

        $tpl_params['albums_options'] = [
            ['id' => 'true', 'label' => $translator->trans('Yes', [], 'admin')],
            ['id' => 'true_sub', 'label' => $translator->trans('No and unlock sub-albums', [], 'admin')],
            ['id' => 'false', 'label' => $translator->trans('No', [], 'admin')],
        ];

        if ($request->isMethod('POST')) {
            if ($request->request->get('submit')) {
                $need_update = false;

                if ($request->request->get('name')) {
                    $album->setName($request->request->get('name'));
                    $need_update = true;
                }

                if ($request->request->get('comment')) {
                    $album->setComment($conf['allow_html_descriptions'] ? $request->request->get('comment') : htmlentities($request->request->get('comment'), ENT_QUOTES, 'utf-8'));
                    $need_update = true;
                }

                if ($conf['activate_comments']) {
                    $album->setCommentable($request->request->get('commentable') === 'true');
                    $need_update = true;
                }

                if ($request->request->get('apply_commentable_on_sub')) {
                    $subcats = $albumMapper->getRepository()->getSubcatIds(['id' => $album_id]);
                    $albumMapper->getRepository()->updateAlbums(['commentable' => $album->isCommentable()], $subcats);
                }

                if ($request->request->get('visible')) {
                    if ($request->request->get('visible') === 'true_sub') {
                        $albumMapper->setAlbumsVisibility([$album_id], $visible = true, $unlock_child = true);
                    } else {
                        $albumMapper->setAlbumsVisibility([$album_id], $request->request->get('visible') !== 'true');
                    }
                }

                if ($need_update) {
                    $albumMapper->getRepository()->addOrUpdateAlbum($album);
                }

                $parent = $request->request->get('parent');
                if ($album->getParent() && $album->getParent()->getId() !== $parent) {
                    $albumMapper->moveAlbums([$album_id], $parent == 0 ? null : $parent);
                }

                $this->addFlash('success', $translator->trans('Album updated successfully', [], 'admin'));
            } elseif ($request->request->get('set_random_representant')) {
                $albumMapper->setRandomRepresentant([$album_id]);
            } elseif ($request->request->get('delete_representant')) {
                $albumMapper->getRepository()->updateAlbums(['representative_picture_id' => null], [$album_id]);
            }

            return $this->redirectToRoute('admin_album', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        $album_has_images = $imageAlbumRepository->count(['album' => $album_id]) > 0;

        $tpl_params['CATEGORIES_NAV'] = $albumMapper->getAlbumsDisplayName($album->getUppercats(), 'admin_album', ['parent_id' => $parent_id]);
        $tpl_params['CAT_ID'] = $album_id;
        $tpl_params['CAT_NAME'] = $album->getName();
        $tpl_params['CAT_COMMENT'] = $album->getComment();
        $tpl_params['CAT_LOCK'] = $album->isVisible() ? 'false' : 'true';
        $tpl_params['U_JUMPTO'] = $this->generateUrl('album', ['album_id' => $album_id]);
        $tpl_params['U_ADD_PHOTOS_ALBUM'] = $this->generateUrl('admin_photos_add', ['album_id' => $album_id]);
        $tpl_params['U_CHILDREN'] = $this->generateUrl('admin_albums', ['parent_id' => $album_id]);
        $tpl_params['ws'] = $this->generateUrl('ws');

        if ($conf['activate_comments']) {
            $tpl_params['CAT_COMMENTABLE'] = $album->isCommentable() ? 'true' : 'false';
        }

        if ($album_has_images) {
            $tpl_params['U_MANAGE_ELEMENTS'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'album', 'value' => $album_id]);

            [$image_count, $min_date, $max_date] = $imageMapper->getRepository()->getImagesInfosInAlbum($album_id);

            if ($min_date->format('Y-m-d') === $max_date->format('Y-m-d')) {
                $tpl_params['INTRO'] = $translator->trans(
                    'This album contains {count} photos, added on {date}.',
                    [
                        'count' => $image_count,
                        'date' => $min_date->format('l d M Y'),
                    ],
                    'admin'
                );
            } else {
                $tpl_params['INTRO'] = $translator->trans(
                    'This album contains {count} photos, added between {min_date} and {max_date}.',
                    [
                        'count' => $image_count,
                        'min_date' => $min_date->format('l d M Y'),
                        'max_date' => $max_date->format('l d M Y'),
                    ],
                    'admin'
                );
            }
        } else {
            $tpl_params['INTRO'] = $translator->trans('This album contains no photo.', [], 'admin');
        }

        $tpl_params['U_DELETE'] = $this->generateUrl('admin_album_delete', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        $tpl_params['parent_category'] = $album->getParent() ? [$album->getParent()->getId()] : [];

        if ($album_has_images || $album->getRepresentativePictureId()) {
            $tpl_params['representant'] = [];

            // picture to display : the identified representant or the generic random representant ?
            if ($album->getRepresentativePictureId()) {
                $representative_picture = $imageMapper->getRepository()->find($album->getRepresentativePictureId());
                if (!is_null($representative_picture)) {
                    $derivative = new DerivativeImage($representative_picture, $image_std_params->getByType(ImageSizeType::THUMB), $image_std_params);
                    $src = $this->generateUrl('admin_media', ['path' => $representative_picture->getPathBasename(), 'derivative' => $derivative->getUrlType(), 'image_extension' => $representative_picture->getExtension()]);
                    $url = $this->generateUrl('admin_photo', ['image_id' => $album->getRepresentativePictureId()]);

                    $tpl_params['representant']['picture'] = [
                        'SRC' => $src,
                        'URL' => $url,
                    ];
                }
            }

            // can the admin choose to set a new random representant ?
            $tpl_params['representant']['ALLOW_SET_RANDOM'] = $album_has_images && $conf['allow_random_representative'];

            // can the admin delete the current representant ?
            if (($album_has_images && $conf['allow_random_representative']) || (!$album_has_images && $album->getRepresentativePictureId())) {
                $tpl_params['representant']['ALLOW_DELETE'] = true;
            }
        }

        $tpl_params['CATEGORIES_NAV'] = $albumMapper->getAlbumsDisplayName($album->getUppercats(), 'admin_album', ['parent_id' => $parent_id]);

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_album', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['CACHE_KEYS'] = Utils::getAdminClientCacheKeys($managerRegistry, ['categories'], $this->generateUrl('homepage'));
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet($album_id, 'properties', $parent_id);

        return $this->render('album_properties.html.twig', $tpl_params);
    }

    #[Route('/admin/album/{album_id}/sort_order/{parent_id}', name: 'admin_album_sort_order', defaults: ['parent_id' => null], requirements: ['parent_id' => '\d+', 'album_id' => '\d+'])]
    public function sort_order(
        Request $request,
        int $album_id,
        ImageMapper $imageMapper,
        AlbumMapper $albumMapper,
        ImageStandardParams $image_std_params,
        TranslatorInterface $translator,
        ?int $parent_id = null,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $album = $albumMapper->getRepository()->find($album_id);

        $image_order_choices = ['default', 'rank', 'user_define'];
        $image_order_choice = 'default';

        if ($request->isMethod('POST')) {
            if ($request->request->has('rank_of_image')) {
                $rank_of_image = $request->request->all('rank_of_image');
                asort($rank_of_image, SORT_NUMERIC);

                foreach ($album->getImageAlbums() as $image_album) {
                    $image_album->setRank($rank_of_image[$image_album->getImage()->getId()]);
                }

                $albumMapper->getRepository()->addOrUpdateAlbum($album);

                $this->addFlash('success', $translator->trans('Images manual order was saved', [], 'admin'));

                return $this->redirectToRoute('admin_album_sort_order', ['album_id' => $album_id, 'parent_id' => $parent_id]);
            }

            if ($request->request->get('image_order_choice') && in_array($request->request->get('image_order_choice'), $image_order_choices)) {
                $image_order_choice = $request->request->get('image_order_choice');
            }

            $image_order = '';
            if ($image_order_choice === 'user_define') {
                for ($i = 0; $i < 3; $i++) {
                    /* @phpstan-ignore-next-line */
                    if ($request->request->get('image_order')[$i] !== '' && $request->request->get('image_order')[$i] !== '0') {
                        if ($image_order !== '') {
                            $image_order .= ',';
                        }

                        $image_order .= $request->request->get('image_order')[$i];
                    }
                }
            } elseif ($image_order_choice === 'rank') {
                $image_order = 'rank ASC';
            }

            $albumMapper->getRepository()->updateAlbums(['image_order' => $image_order], [$album_id]);

            if ($request->request->get('image_order_subcats')) {
                $children_ids = [];
                foreach ($album->getChildren() as $child) {
                    $children_ids[] = $child->getId();
                }

                $albumMapper->getRepository()->updateAlbums(['image_order' => $image_order], $children_ids);
            }

            $this->addFlash('success', $translator->trans('Your configuration settings have been saved', [], 'admin'));

            return $this->redirectToRoute('admin_album_sort_order', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        if ($album->getImageOrder() === 'rank ASC') {
            $image_order_choice = 'rank';
        } elseif ($album->getImageOrder() != '') {
            $image_order_choice = 'user_define';
        }

        $tpl_params['CATEGORIES_NAV'] = $albumMapper->getAlbumsDisplayName($album->getUppercats(), 'admin_album', ['parent_id' => $parent_id]);

        // template thumbnail initialization
        $current_rank = 1;
        $derivativeParams = $image_std_params->getByType(ImageSizeType::SQUARE);
        foreach ($imageMapper->getRepository()->findImagesInAlbum($album_id, [['rank', 'asc']]) as $image) {
            $derivative = new DerivativeImage($image, $derivativeParams, $image_std_params);

            if ($image->getName()) {
                $thumbnail_name = $image->getName();
            } else {
                $file_wo_ext = Utils::getFilenameWithoutExtension($image->getFile());
                $thumbnail_name = str_replace('_', ' ', $file_wo_ext);
            }

            $current_rank++;
            $tpl_params['thumbnails'][] = [
                'ID' => $image->getId(),
                'NAME' => $thumbnail_name,
                'TN_SRC' => $this->generateUrl('admin_media', ['path' => $image->getPathBasename(), 'derivative' => $derivative->getUrlType(), 'image_extension' => $image->getExtension()]),
                'RANK' => $current_rank * 10,
                'SIZE' => $derivative->getSize(),
            ];
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

        $image_order = explode(',', (string) $album->getImageOrder());
        for ($i = 0; $i < 3; $i++) { // 3 fields
            $tpl_params['image_order'][] = $image_order[$i] ?? '';
        }

        $tpl_params['image_order_choice'] = $image_order_choice;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params['ALBUM_ID'] = $album_id;
        $tpl_params['tabsheet'] = $this->setTabsheet($album_id, 'sort_order', $parent_id);

        return $this->render('album_sort_order.html.twig', $tpl_params);
    }

    #[Route('/admin/album/{album_id}/permissions/{parent_id}', name: 'admin_album_permissions', defaults: ['parent_id' => null], requirements: ['parent_id' => '\d+', 'album_id' => '\d+'])]
    public function permissions(
        Request $request,
        int $album_id,
        Conf $conf,
        UserCacheRepository $userCacheRepository,
        CsrfTokenManagerInterface $tokenManager,
        AlbumMapper $albumMapper,
        TranslatorInterface $translator,
        UserRepository $userRepository,
        GroupRepository $groupRepository,
        ManagerRegistry $managerRegistry,
        ?int $parent_id = null,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $album = $albumMapper->getRepository()->find($album_id);

        if ($request->isMethod('POST')) {
            if ($album->getStatus() !== $request->request->get('status')) {
                $albumMapper->setAlbumsStatus([$album_id], $request->request->get('status'));
            }

            if ($request->request->get('status') === Album::STATUS_PRIVATE) {
                // manage groups
                if (!$request->request->has('groups')) {
                    $album->clearAllGroupAccess();

                // need to clear group access for sub-albums
                // $subcats = $albumRepository->getSubcatIds([$album_id]);
                } else {
                    $sub_albums = null;
                    if ($request->request->get('apply_on_sub')) {
                        $sub_albums = $albumMapper->getRepository()->getSubAlbums([$album_id]);
                    }

                    foreach ($groupRepository->findBy(['id' => $request->request->all('groups')]) as $group) {
                        $album->addGroupAccess($group);
                        if (!is_null($sub_albums)) {
                            foreach ($sub_albums as $sub_album) {
                                $sub_album->addGroupAccess($group);
                            }
                        }
                    }
                }

                // users
                if (!$request->request->has('users')) {
                    $album->clearAllUserAccess();

                // need to clear user access for sub albums
                } else {
                    $sub_albums = null;
                    if ($request->request->get('apply_on_sub')) {
                        $sub_albums = $albumMapper->getRepository()->getSubAlbums([$album_id]);
                    }

                    foreach ($userRepository->findBy(['id' => $request->request->all('users')]) as $user) {
                        $album->addUserAccess($user);
                        if (!is_null($sub_albums)) {
                            foreach ($sub_albums as $sub_album) {
                                $sub_album->addUserAccess($user);
                            }
                        }
                    }
                }

                $albumMapper->getRepository()->addOrUpdateAlbum($album);

                $userCacheRepository->deleteAll();
            }

            $this->addFlash('success', $translator->trans('Album updated successfully', [], 'admin'));

            return $this->redirectToRoute('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        $tpl_params['groups'] = [];
        foreach ($groupRepository->findAll() as $group) {
            $tpl_params['groups'][$group->getId()] = $group->getName();
        }

        // groups granted to access the category
        $tpl_params['groups_selected'] = [];
        foreach ($album->getGroupAccess() as $group) {
            $tpl_params['groups_selected'][] = $group->getId();
        }

        // users...
        $tpl_params['users'] = [];
        foreach ($userRepository->findAll() as $user) {
            $tpl_params['users'][$user->getId()] = $user;
        }

        $tpl_params['users_selected'] = [];
        foreach ($album->getUserAccess() as $user) {
            $tpl_params['users_selected'][] = $user->getId();
        }

        $user_granted_indirect_ids = [];
        if ($tpl_params['groups_selected'] !== []) {
            $granted_groups = [];

            foreach ($groupRepository->findBy(['id' => $tpl_params['groups_selected']]) as $group) {
                if (!isset($granted_groups[$group->getId()])) {
                    $granted_groups[$group->getId()] = [];
                }

                foreach ($group->getUsers() as $user) {
                    $granted_groups[$group->getId()][] = $user->getId();
                }
            }

            $user_granted_by_group_ids = [];

            foreach ($granted_groups as $group_users) {
                $user_granted_by_group_ids = [...$user_granted_by_group_ids, ...$group_users];
            }

            $user_granted_by_group_ids = array_unique($user_granted_by_group_ids);
            $user_granted_indirect_ids = array_diff($user_granted_by_group_ids, $tpl_params['users_selected']);

            $tpl_params['nb_users_granted_indirect'] = count($user_granted_indirect_ids);
            foreach ($granted_groups as $group_id => $group_users) {
                $group_usernames = [];
                foreach ($group_users as $user_id) {
                    if (in_array($user_id, $user_granted_indirect_ids)) {
                        $group_usernames[] = $tpl_params['users'][$user_id]->getUsername();
                    }
                }

                $tpl_params['user_granted_indirect_groups'][] = [
                    'group_name' => $tpl_params['groups'][$group_id],
                    'group_users' => implode(', ', $group_usernames),
                ];
            }
        }

        $tpl_params['csrf_token'] = $tokenManager->getToken('authenticate');
        $tpl_params['CATEGORIES_NAV'] = $albumMapper->getAlbumsDisplayName($album->getUppercats(), 'admin_album', ['parent_id' => $parent_id]);
        $tpl_params['U_GROUPS'] = $this->generateUrl('admin_groups');
        $tpl_params['CACHE_KEYS'] = Utils::getAdminClientCacheKeys($managerRegistry, ['groups', 'users'], $this->generateUrl('homepage'));
        $tpl_params['ws'] = $this->generateUrl('ws');

        $tpl_params['private'] = $album->getStatus() === Album::STATUS_PRIVATE;
        $tpl_params['INHERIT'] = $conf['inheritance_by_default'];
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet($album_id, 'permissions', $parent_id);

        return $this->render('album_permissions.html.twig', $tpl_params);
    }

    #[Route('/admin/album/{album_id}/notification/{parent_id}', name: 'admin_album_notification', defaults: ['parent_id' => null], requirements: ['parent_id' => '\d+', 'album_id' => '\d+'])]
    public function notification(
        Request $request,
        int $album_id,
        Conf $conf,
        AlbumMapper $albumMapper,
        ImageStandardParams $image_std_params,
        EventDispatcherInterface $eventDispatcher,
        GroupRepository $groupRepository,
        ImageMapper $imageMapper,
        TranslatorInterface $translator,
        ?int $parent_id = null,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $album = $albumMapper->getRepository()->find($album_id);

        if ($request->isMethod('POST')) {
            // @TODO: if $category['representative_picture_id'] is empty find child representative_picture_id
            if ($album->getRepresentativePictureId()) {
                $element = $imageMapper->getRepository()->find($album->getRepresentativePictureId());
                if (!is_null($element)) {
                    $derivative = new DerivativeImage($element, $image_std_params->getByType(ImageSizeType::THUMB), $image_std_params);
                    $img_src = $this->generateUrl('admin_media', [
                        'path' => $element->getPathBasename(),
                        'derivative' => $derivative->getUrlType(),
                        'image_extension' => $element->getExtension(),
                    ]);
                    $img_url = '<a href="' . $this->generateUrl('picture', ['image_id' => $element->getId(), 'type' => 'album', 'element_id' => $album_id], UrlGeneratorInterface::ABSOLUTE_URL);
                    $img_url .= '"><img src="' . $img_src . '" alt="X"></a>';
                }
            }

            if (!isset($img_url)) {
                $img_url = '';
            }

            $eventDispatcher->dispatch(new GroupEvent((int) $request->request->get('group'), ['id' => $album_id, 'name' => $album->getName()], $img_url, $request->request->get('mail_content')));

            $group = $groupRepository->find($request->request->get('group'));

            $this->addFlash('success', $translator->trans('An information email was sent to group "{group}"', ['group' => $group->getName()], 'admin'));

            return $this->redirectToRoute('admin_album_notification', ['album_id' => $album_id, 'parent_id' => $parent_id]);
        }

        $all_groups = [];
        foreach ($groupRepository->findAll() as $group) {
            $all_groups[$group->getId()] = $group->getName();
        }

        if ($all_groups === []) {
            $tpl_params['no_group_in_gallery'] = true;
        } else {
            if ($album->getStatus() === Album::STATUS_PRIVATE) {
                $group_ids = [];
                foreach ($album->getGroupAccess() as $group) {
                    $group_ids[] = $group->getId();
                }

                if ($group_ids === []) {
                    $tpl_params['U_PERMISSIONS'] = $this->generateUrl('admin_album_permissions', ['album_id' => $album_id, 'parent_id' => $parent_id]);
                }
            } else {
                $group_ids = array_keys($all_groups);
            }

            if ($group_ids !== []) {
                $tpl_params['group_mail_options'] = array_filter($all_groups, fn ($key): bool => in_array($key, $group_ids), ARRAY_FILTER_USE_KEY);
            }
        }

        $tpl_params['CATEGORIES_NAV'] = $albumMapper->getAlbumsDisplayName($album->getUppercats(), 'admin_album', ['parent_id' => $parent_id]);
        $tpl_params['U_GROUPS'] = $this->generateUrl('admin_groups');
        $tpl_params['COMPLEMENTARY_MAIL_CONTENT'] = $conf['nbm_complementary_mail_content'];

        $tpl_params['ALBUM_ID'] = $album_id;
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_albums');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_albums_options');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Album', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet($album_id, 'notification', $parent_id);

        return $this->render('album_notification.html.twig', $tpl_params);
    }

    #[Route('/admin/album/create/{parent_id}', name: 'admin_album_create', defaults: ['parent_id' => null], requirements: ['parent_id' => '\d+'])]
    public function create(
        Request $request,
        AppUserService $appUserService,
        AlbumMapper $albumMapper,
        UserMapper $userMapper,
        UserInfosRepository $userInfosRepository,
        TranslatorInterface $translator,
        ?int $parent_id = null,
    ): Response {
        if ($request->isMethod('POST')) {
            $album_name = $request->request->get('album_name');

            // is the given category name only containing blank spaces ?
            if (preg_match('/^\s*$/', $album_name)) {
                $this->addFlash('error', $translator->trans('The name of an album must not be empty', [], 'admin'));
            } else {
                $admin_ids = [];
                foreach ($userInfosRepository->findBy(['status' => [UserStatusType::WEBMASTER, UserStatusType::ADMIN]]) as $userInfos) {
                    $admin_ids[] = $userInfos->getUser()->getId();
                }

                $params = ['apply_on_sub' => false];
                if ($request->request->get('apply_on_sub')) {
                    $params['apply_on_sub'] = true;
                }

                $parent = null;
                if (!is_null($parent_id)) {
                    $parent = $albumMapper->getRepository()->find($parent_id);
                }

                $albumMapper->createAlbum($album_name, $appUserService->getUser()->getId(), $parent, $admin_ids, $params);
                $userMapper->invalidateUserCache();

                $this->addFlash('success', $translator->trans('Album added', [], 'admin'));
            }
        }

        return $this->redirectToRoute('admin_albums', ['parent_id' => $parent_id]);
    }

    #[Route('/admin/album/{album_id}/delete/{parent_id}', name: 'admin_album_delete', defaults: ['parent_id' => null], requirements: ['parent_id' => '\d+', 'album_id' => '\d+'])]
    public function delete(int $album_id, AlbumMapper $albumMapper, ImageMapper $imageMapper, UserMapper $userMapper, TranslatorInterface $translator, ?int $parent_id = null): Response
    {
        $albumMapper->deleteAlbums([$album_id]);

        // destruction of all photos physically linked to the category
        $element_ids = [];
        foreach ($imageMapper->getRepository()->findBy(['storage_category_id' => $album_id]) as $image) {
            $element_ids[] = $image->getId();
        }

        $imageMapper->deleteElements($element_ids);

        $this->addFlash('success', $translator->trans('Album deleted', [], 'admin'));
        $albumMapper->updateGlobalRank();
        $userMapper->invalidateUserCache();

        return $this->redirectToRoute('admin_albums', ['parent_id' => $parent_id]);
    }
}
