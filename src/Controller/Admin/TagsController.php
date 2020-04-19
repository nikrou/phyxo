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

use App\DataMapper\TagMapper;
use App\Entity\User;
use App\Repository\ImageTagRepository;
use App\Repository\TagRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\URL;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagsController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'all'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('all', $this->translator->trans('All tags', [], 'admin'), $this->generateUrl('admin_tags'));
        $tabsheet->add('permissions', $this->translator->trans('Permissions', [], 'admin'), $this->generateUrl('admin_tags_permissions'), 'fa-lock');
        $tabsheet->add('pending', $this->translator->trans('Pendings', [], 'admin'), $this->generateUrl('admin_tags_pending'), 'fa-clock');

        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function list(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, CsrfTokenManagerInterface $csrfTokenManager,
                        TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $result = $em->getRepository(TagRepository::class)->getOrphanTags();
        $orphan_tags = $em->getConnection()->result2array($result);

        $orphan_tag_names = [];
        foreach ($orphan_tags as $tag) {
            $orphan_tag_names[] = $tag['name'];
        }

        if (count($orphan_tag_names) > 0) {
            $tpl_params['warnings'][] = sprintf(
              $translator->trans('You have %d orphan tags: %s.', [], 'admin') . ' <a href="%s">' . $translator->trans('Delete orphan tags', [], 'admin') . '</a>',
              count($orphan_tag_names),
              implode(', ', $orphan_tag_names),
              $this->generateUrl('admin_tags_delete_orphans')
            );
        }

        // +-----------------------------------------------------------------------+
        // |                             form creation                             |
        // +-----------------------------------------------------------------------+

        // tag counters
        $result = $em->getRepository(ImageTagRepository::class)->getTagCounters();
        $tag_counters = $em->getConnection()->result2array($result, 'tag_id', 'counter');

        // all tags
        $result = $em->getRepository(TagRepository::class)->findAll();
        $all_tags = [];
        while ($tag = $em->getConnection()->db_fetch_assoc($result)) {
            $raw_name = $tag['name'];
            $tag['name'] = $raw_name;
            if (empty($tag_counters[$tag['id']])) {
                $tag['counter'] = 0;
            } else {
                $tag['counter'] = intval($tag_counters[$tag['id']]);
            }
            if ($tag['counter'] > 0) {
                $tag['U_VIEW'] = $this->generateUrl('images_by_tags', ['tag_ids' => URL::tagToUrl($tag)]);
                $tag['U_MANAGE_PHOTOS'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'tag', 'value' => $tag['id']]);
            }

            $alt_names = [];
            $alt_names = array_diff(array_unique($alt_names), [$tag['name']]);
            if (count($alt_names)) {
                $tag['alt_names'] = implode(', ', $alt_names);
            }
            $all_tags[] = $tag;
        }
        usort($all_tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

        $tpl_params['all_tags'] = $all_tags;

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');

        $tpl_params['F_ACTION_ADD'] = $this->generateUrl('admin_tags_add');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_tags_actions');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_tags');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_tags');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Tags', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('all'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('tags_all.html.twig', $tpl_params);
    }

    public function actions(Request $request, EntityManager $em, TagMapper $tagMapper, TranslatorInterface $translator)
    {
        if ($request->request->get('action') === 'edit') {
            $result = $em->getRepository(TagRepository::class)->findAll();
            $existing_names = $em->getConnection()->result2array($result, null, 'name');

            $current_name_of = [];
            $result = $em->getRepository(TagRepository::class)->findTags($request->request->get('edit_list'));
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $current_name_of[$row['id']] = $row['name'];
            }

            $updates = [];
            // we must not rename tag with an already existing name
            foreach (explode(',', $request->request->get('edit_list')) as $tag_id) {
                $tag_name = $request->request->get('tag_name-' . $tag_id);

                if ($tag_name !== $current_name_of[$tag_id]) {
                    if (in_array($tag_name, $existing_names)) {
                        $this->addFlash('error', $translator->trans('Tag "{tag}" already exists', ['tag' => $tag_name], 'admin'));
                    } elseif (!empty($tag_name)) {
                        $updates[] = [
                            'id' => $tag_id,
                            'name' => $tag_name,
                            'url_name' => $tag_name,
                        ];
                    }
                }
            }
            $em->getRepository(TagRepository::class)->updateTags(
              [
                  'primary' => ['id'],
                  'update' => ['name', 'url_name'],
              ],
              $updates
          );
        } elseif ($request->request->get('action') === 'duplicate') {
            $result = $em->getRepository(TagRepository::class)->findAll();
            $existing_names = $em->getConnection()->result2array($result, null, 'name');

            $current_name_of = [];
            $result = $em->getRepository(TagRepository::class)->findTags($request->request->get('tags'));
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $current_name_of[$row['id']] = $row['name'];
            }

            $updates = [];
            // we must not rename tag with an already existing name
            foreach ($request->request->get('tags') as $tag_id) {
                $tag_name = $request->request->get('tag_name-' . $tag_id);

                if ($tag_name != $current_name_of[$tag_id]) {
                    if (in_array($tag_name, $existing_names)) {
                        $this->addFlash('error', $translator->trans('Tag "{tag}" already exists', ['tag' => $tag_name], 'admin'));
                    } elseif (!empty($tag_name)) {
                        $em->getRepository(TagRepository::class)->insertTag($tag_name, $tag_name);

                        $result = $em->getRepository(TagRepository::class)->findBy('name', $tag_name);
                        $destination_tag = $em->getConnection()->result2array($result, null, 'id');
                        $destination_tag_id = $destination_tag[0];

                        $result = $em->getRepository(ImageTagRepository::class)->findBy('tag_id', $tag_id);
                        $destination_tag_image_ids = $em->getConnection()->result2array($result, null, 'image_id');

                        $inserts = [];
                        foreach ($destination_tag_image_ids as $image_id) {
                            $inserts[] = [
                                'tag_id' => $destination_tag_id,
                                'image_id' => $image_id
                            ];
                        }

                        if (count($inserts) > 0) {
                            $em->getRepository(ImageTagRepository::class)->insertImageTags(
                                array_keys($inserts[0]),
                                $inserts
                            );
                        }

                        $this->addFlash(
                            'info',
                            $translator->trans('Tag "{tag}" is now a duplicate of "{duplicate_tag}"', ['tag' => $tag_name, 'duplicate_tag' => $current_name_of[$tag_id]], 'admin')
                        );
                    }
                }
            }

            $em->getRepository(TagRepository::class)->updateTags(
                [
                    'primary' => ['id'],
                    'update' => ['name', 'url_name'],
                ],
                $updates
            );
        } elseif ($request->request->get('action') === 'merge') {
            if (!$request->request->get('destination_tag')) {
                $this->addFlash('error', $translator->trans('No destination tag selected', [], 'admin'));
            } else {
                $destination_tag_id = $request->request->get('destination_tag');
                $tag_ids = $request->request->get('tags');

                if (is_array($tag_ids) && count($tag_ids) > 1) {
                    $name_of_tag = [];
                    $result = $em->getRepository(TagRepository::class)->findTags($tag_ids);
                    while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                        $name_of_tag[$row['id']] = $row['name'];
                    }

                    $tag_ids_to_delete = array_diff($tag_ids, [$destination_tag_id]);

                    $result = $em->getRepository(ImageTagRepository::class)->findImageByTags($tag_ids_to_delete);
                    $image_ids = $em->getConnection()->result2array($result, null, 'image_id');

                    $tagMapper->deleteTags($tag_ids_to_delete);

                    $result = $em->getRepository(ImageTagRepository::class)->findBy('tag_id', $destination_tag_id);
                    $destination_tag_image_ids = $em->getConnection()->result2array($result, null, 'image_id');

                    $image_ids_to_link = array_diff($image_ids, $destination_tag_image_ids);

                    $inserts = [];
                    foreach ($image_ids_to_link as $image_id) {
                        $inserts[] = [
                            'tag_id' => $destination_tag_id,
                            'image_id' => $image_id
                        ];
                    }

                    if (count($inserts) > 0) {
                        $em->getRepository(ImageTagRepository::class)->insertImageTags(array_keys($inserts[0]), $inserts);
                    }

                    $tags_deleted = [];
                    foreach ($tag_ids_to_delete as $tag_id) {
                        $tags_deleted[] = $name_of_tag[$tag_id];
                    }

                    $this->addFlash(
                        'info',
                        $translator->trans('Tags <em>{tags_deleted}</em> merged into tag <em>{destination_tags}</em>',
                                            ['tags_deleted' => implode(', ', $tags_deleted), 'destination_tags' => $name_of_tag[$destination_tag_id]],
                                            'admin'
                        )
                    );
                }
            }
        } elseif ($request->request->get('action') === 'delete' && $request->request->get('tags')) {
            if (!$request->request->get('confirm_deletion')) {
                $this->addFlash('error', $translator->trans('You need to confirm deletion', [], 'admin'));
            } else {
                $result = $em->getRepository(TagRepository::class)->findTags($request->request->get('tags'));
                $tag_names = $em->getConnection()->result2array($result, null, 'name');

                $tagMapper->deleteTags($_POST['tags']);

                if (count($tag_names) > 1) {
                    $this->addFlash('info', $translator->trans('The following tags were deleted' . ' : ' . implode(', ', $tag_names)));
                } else {
                    $this->addFlash('info', $translator->trans('The following tag was deleted' . ' : ' . implode(', ', $tag_names)));
                }
            }
        }

        return $this->redirectToRoute('admin_tags');
    }

    public function add(Request $request, TagMapper $tagMapper)
    {
        if ($request->request->get('add_tag')) {
            $ret = $tagMapper->createTag($request->request->get('add_tag'));

            if (isset($ret['error'])) {
                $this->addFlash('error', $ret['error']);
            } else {
                $this->addFlash('info', $ret['info']);
            }
        }

        return $this->redirectToRoute('admin_tags');
    }

    public function deleteOrphans(TagMapper $tagMapper, TranslatorInterface $translator)
    {
        $tagMapper->deleteOrphanTags();
        $this->addFlash('info', $translator->trans('Orphan tags deleted', [], 'admin'));

        return $this->redirectToRoute('admin_tags');
    }

    public function permissions(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, TagMapper $tagMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $status_options[null] = '----------';
        foreach (User::ALL_STATUS as $status) {
            $status_options[$status] = $translator->trans('user_status_' . $status, [], 'admin');
        }

        if ($request->isMethod('POST')) {
            if ($request->request->get('permission_add') && isset($status_options[$request->request->get('permission_add')])) {
                $Permissions['add'] = $request->request->get('permission_add');
                $conf['tags_permission_add'] = $Permissions['add'];
            }

            $Permissions['existing_tags_only'] = $request->request->get('existing_tags_only') ? 1 : 0;
            $conf['tags_existing_tags_only'] = $Permissions['existing_tags_only'];

            if ($request->request->get('permission_delete') && isset($status_options[$request->request->get('permission_delete')])) {
                $Permissions['delete'] = $request->request->get('permission_delete');
                $conf['tags_permission_delete'] = $Permissions['delete'];
            }

            $Permissions['publish_tags_immediately'] = $request->request->get('publish_tags_immediately') ? 0 : 1;
            $conf['publish_tags_immediately'] = $Permissions['publish_tags_immediately'];

            $Permissions['delete_tags_immediately'] = $request->request->get('delete_tags_immediately') ? 0 : 1;
            $conf['delete_tags_immediately'] = $Permissions['delete_tags_immediately'];

            $Permissions['show_pending_added_tags'] = $request->request->get('show_pending_added_tags') ? 1 : 0;
            $conf['show_pending_added_tags'] = $Permissions['show_pending_added_tags'];

            $Permissions['show_pending_deleted_tags'] = $request->request->get('show_pending_deleted_tags') ? 1 : 0;
            $conf['show_pending_deleted_tags'] = $Permissions['show_pending_deleted_tags'];

            $tagMapper->invalidateUserCacheNbTags();

            $this->addFlash('info', $translator->trans('Settings have been updated', [], 'admin'));

            $this->redirectToRoute('admin_tags_permissions');
        }

        $Permissions = [];
        $Permissions['add'] = $conf['tags_permission_add'];
        $Permissions['delete'] = $conf['tags_permission_delete'];
        $Permissions['existing_tags_only'] = $conf['tags_existing_tags_only'];
        $Permissions['publish_tags_immediately'] = $conf['publish_tags_immediately'];
        $Permissions['delete_tags_immediately'] = $conf['delete_tags_immediately'];
        $Permissions['show_pending_added_tags'] = $conf['show_pending_added_tags'];
        $Permissions['show_pending_deleted_tags'] = $conf['show_pending_deleted_tags'];

        $tpl_params['PERMISSIONS'] = $Permissions;
        $tpl_params['STATUS_OPTIONS'] = $status_options;

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_tags_permissions');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_tags');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_tags');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Tags', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('permissions'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('tags_permissions.html.twig', $tpl_params);
    }

    public function pending(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, TagMapper $tagMapper, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST') && $request->request->get('tag_ids')) {
            if ($request->request->get('validate')) {
                $tagMapper->validateTags($request->request->get('tag_ids'));
                $this->addFlash('info', $translator->trans('Tags have been validated', [], 'admin'));
            } elseif ($request->request->get('reject')) {
                $tagMapper->rejectTags($request->request->get('tag_ids'));
                $this->addFlash('info', $translator->trans('Tags have been rejected', [], 'admin'));
            }

            $this->redirectToRoute('admin_tags_pending');
        }

        $tpl_params['tags'] = $tagMapper->getPendingTags();

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_tags_permissions');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_tags');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Tags', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('pending'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('tags_pending.html.twig', $tpl_params);
    }
}
