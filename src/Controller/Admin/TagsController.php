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
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\ImageTagRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
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
                        TranslatorInterface $translator, TagMapper $tagMapper, ImageTagRepository $imageTagRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $orphan_tag_names = [];
        foreach ($tagMapper->getRepository()->getOrphanTags() as $tag) {
            $orphan_tag_names[] = $tag->getName();
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
        $tag_counters = [];
        foreach ($imageTagRepository->getTagCounters() as $image_tag) {
            $tag_counters[$image_tag['tag_id']] = $image_tag['counter'];
        }

        // all tags
        $all_tags = [];
        foreach ($tagMapper->getRepository()->findAll() as $tag) {
            if (!empty($tag_counters[$tag->getId()])) {
                $tag->setCounter($tag_counters[$tag->getId()]);
            }

            $tpl_tag = $tag->toArray();
            if ($tag->getCounter() > 0) {
                $tpl_tag['U_VIEW'] = $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()]);
                $tpl_tag['U_MANAGE_PHOTOS'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'tag', 'value' => $tag->getId()]);
            }

            $all_tags[] = $tpl_tag;
        }

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

    public function actions(Request $request, TagMapper $tagMapper, TranslatorInterface $translator)
    {
        if ($request->request->get('action') === 'edit') {
            $existing_names = [];
            foreach ($tagMapper->getRepository()->findAll() as $tag) {
                $existing_names[] = $tag->getName();
            }

            $current_name_of = [];
            foreach ($tagMapper->getRepository()->findBy(['id' => $request->request->get('edit_list')]) as $tag) {
                $current_name_of[$tag->getId()] = $tag;
            }

            // we must not rename tag with an already existing name
            foreach (explode(',', $request->request->get('edit_list')) as $tag_id) {
                $tag_name = $request->request->get('tag_name-' . $tag_id);

                if ($tag_name !== $current_name_of[$tag_id]->getName()) {
                    if (in_array($tag_name, $existing_names)) {
                        $this->addFlash('error', $translator->trans('Tag "{tag}" already exists', ['tag' => $tag_name], 'admin'));
                    } elseif (!empty($tag_name)) {
                        $current_tag = $current_name_of[$tag_id];
                        $current_tag->setName($tag_name);
                        $current_tag->setUrlName($tag_name);
                        $tagMapper->getRepository()->addOrUpdateTag($current_tag);
                    }
                }
            }
        } elseif ($request->request->get('action') === 'duplicate') {
            $existing_names = [];
            foreach ($tagMapper->getRepository()->findAll() as $tag) {
                $existing_names[] = $tag->getName();
            }

            $current_name_of = [];
            foreach ($tagMapper->getRepository()->findBy(['id' => $request->request->get('tags')]) as $tag) {
                $current_name_of[$tag->getId()] = $tag;
            }

            // we must not rename tag with an already existing name
            foreach ($request->request->get('tags') as $tag_id) {
                $tag_name = $request->request->get('tag_name-' . $tag_id);

                if ($tag_name != $current_name_of[$tag_id]->getName()) {
                    if (in_array($tag_name, $existing_names)) {
                        $this->addFlash('error', $translator->trans('Tag "{tag}" already exists', ['tag' => $tag_name], 'admin'));
                    } elseif (!empty($tag_name)) {
                        $destination_tag = new Tag();
                        $destination_tag->setName($tag_name);
                        $destination_tag->setUrlName($tag_name);
                        $tagMapper->getRepository()->addOrUpdateTag($destination_tag);

                        $existing_tag_images = $current_name_of[$tag_id]->getImageTags();
                        if (!$existing_tag_images->isEmpty()) {
                            foreach ($existing_tag_images as $tag_image) {
                                $destination_tag->addImageTag($tag_image);
                            }
                            $tagMapper->getRepository()->addOrUpdateTag($destination_tag);
                        }

                        $this->addFlash(
                            'info',
                            $translator->trans('Tag "{tag}" is now a duplicate of "{duplicate_tag}"', ['tag' => $tag_name, 'duplicate_tag' => $current_name_of[$tag_id]->getName()], 'admin')
                        );
                    }
                }
            }
        } elseif ($request->request->get('action') === 'merge') {
            if (!$request->request->get('destination_tag')) {
                $this->addFlash('error', $translator->trans('No destination tag selected', [], 'admin'));
            } else {
                $destination_tag = $tagMapper->getRepository()->find($request->request->get('destination_tag'));
                $tag_ids = $request->request->get('tags');

                if (is_array($tag_ids) && count($tag_ids) > 1) {
                    $tags_deleted = [];
                    foreach ($tagMapper->getRepository()->findBy(['id' => $request->request->get('tags')]) as $tag) {
                        $existing_tag_images = $tag->getImageTags();
                        if (!$existing_tag_images->isEmpty()) {
                            foreach ($existing_tag_images as $tag_image) {
                                $destination_tag->addImageTag($tag_image);
                            }
                        }
                        $tags_deleted[] = $tag->getName();
                        $tagMapper->getRepository()->delete($tag);
                    }

                    $tagMapper->getRepository()->addOrUpdateTag($destination_tag);

                    $this->addFlash(
                        'info',
                        $translator->trans('Tags <em>{tags_deleted}</em> merged into tag <em>{destination_tag}</em>',
                                            ['tags_deleted' => implode(', ', $tags_deleted), 'destination_tag' => $destination_tag->getName()],
                                            'admin'
                        )
                    );
                }
            }
        } elseif ($request->request->get('action') === 'delete' && $request->request->get('tags')) {
            if (!$request->request->get('confirm_deletion')) {
                $this->addFlash('error', $translator->trans('You need to confirm deletion', [], 'admin'));
            } else {
                $tagMapper->deleteTags($request->request->get('tags'));

                if (count($request->request->get('tags')) > 1) {
                    $this->addFlash('info', $translator->trans('The tags were deleted', [], 'admin'));
                } else {
                    $this->addFlash('info', $translator->trans('The tag was deleted', [], 'admin'));
                }
            }
        }

        return $this->redirectToRoute('admin_tags');
    }

    public function add(Request $request, TagMapper $tagMapper)
    {
        if ($request->request->get('add_tag')) {
            if (!is_null($tagMapper->getRepository()->findOneBy(['name' => $request->request->get('add_tag')]))) {
                $this->addFlash('error', "Tag already exists");
            } else {
                $tag = new Tag();
                $tag->setName($request->request->get('add_tag'));
                $tag->setUrlName($request->request->get('add_tag'));
                $tag->setLastModified(new \DateTime());
                $tagMapper->getRepository()->addOrUpdateTag($tag);

                $this->addFlash('info', $this->translator->trans('Tag "{tag}" was added', ['tag' => $tag->getName()], 'admin'));
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

        $status_options[null] = '';
        foreach (User::ALL_STATUS as $status) {
            $status_options[$status] = $translator->trans('user_status_' . $status, [], 'admin');
        }

        if ($request->isMethod('POST')) {
            if ($request->request->get('permission_add') && isset($status_options[$request->request->get('permission_add')])) {
                $conf['tags_permission_add'] = $request->request->get('permission_add');
            } else {
                $conf['tags_permission_add'] = '';
            }

            if ($request->request->get('permission_delete') && isset($status_options[$request->request->get('permission_delete')])) {
                $conf['tags_permission_delete'] = $request->request->get('permission_delete');
            } else {
                $conf['tags_permission_delete'] = '';
            }

            $conf['tags_existing_tags_only'] = $request->request->get('existing_tags_only') ? 1 : 0;
            $conf['publish_tags_immediately'] = $request->request->get('publish_tags_immediately') ? 0 : 1;
            $conf['delete_tags_immediately'] = $request->request->get('delete_tags_immediately') ? 0 : 1;
            $conf['show_pending_added_tags'] = $request->request->get('show_pending_added_tags') ? 1 : 0;
            $conf['show_pending_deleted_tags'] = $request->request->get('show_pending_deleted_tags') ? 1 : 0;

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
