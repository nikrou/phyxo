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
use App\Form\TagPermissionsType;
use App\Repository\ImageTagRepository;
use DateTime;
use Phyxo\Conf;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminTagsController extends AbstractController
{
    private TranslatorInterface $translator;

    protected function setTabsheet(string $section = 'all'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('all', $this->translator->trans('All tags', [], 'admin'), $this->generateUrl('admin_tags'));
        $tabsheet->add('permissions', $this->translator->trans('Permissions', [], 'admin'), $this->generateUrl('admin_tags_permissions'), 'fa-lock');
        $tabsheet->add('pending', $this->translator->trans('Pendings', [], 'admin'), $this->generateUrl('admin_tags_pending'), 'fa-clock');

        $tabsheet->select($section);

        return $tabsheet;
    }

    #[Route('/admin/tags', name: 'admin_tags')]
    public function list(
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
        TagMapper $tagMapper,
        ImageTagRepository $imageTagRepository,
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $orphan_tag_names = [];
        foreach ($tagMapper->getRepository()->getOrphanTags() as $tag) {
            $orphan_tag_names[] = $tag->getName();
        }

        if ($orphan_tag_names !== []) {
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
        $tpl_params['tabsheet'] = $this->setTabsheet('all');

        return $this->render('tags_all.html.twig', $tpl_params);
    }

    #[Route('/admin/tags/actions', name: 'admin_tags_actions')]
    public function actions(Request $request, TagMapper $tagMapper, TranslatorInterface $translator): Response
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

                if ($tag_name !== $current_name_of[(int) $tag_id]->getName()) {
                    if (in_array($tag_name, $existing_names)) {
                        $this->addFlash('error', $translator->trans('Tag "{tag}" already exists', ['tag' => $tag_name], 'admin'));
                    } elseif (!empty($tag_name)) {
                        $current_tag = $current_name_of[(int) $tag_id];
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
            foreach ($request->request->all('tags') as $tag_id) {
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
                            'success',
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
                $tag_ids = $request->request->all('tags');

                if (count($tag_ids) > 1) {
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
                        'sucess',
                        $translator->trans(
                            'Tags <em>{tags_deleted}</em> merged into tag <em>{destination_tag}</em>',
                            ['tags_deleted' => implode(', ', $tags_deleted), 'destination_tag' => $destination_tag->getName()],
                            'admin'
                        )
                    );
                }
            }
        } elseif ($request->request->get('action') === 'delete' && $request->request->has('tags')) {
            if (!$request->request->get('confirm_deletion')) {
                $this->addFlash('error', $translator->trans('You need to confirm deletion', [], 'admin'));
            } else {
                $tagMapper->deleteTags($request->request->all('tags'));

                if (count($request->request->all('tags')) > 1) {
                    $this->addFlash('success', $translator->trans('The tags were deleted', [], 'admin'));
                } else {
                    $this->addFlash('success', $translator->trans('The tag was deleted', [], 'admin'));
                }
            }
        }

        return $this->redirectToRoute('admin_tags');
    }

    #[Route('/admin/tags/add', name: 'admin_tags_add')]
    public function add(Request $request, TagMapper $tagMapper, TranslatorInterface $translator): Response
    {
        if ($request->request->get('add_tag')) {
            if (!is_null($tagMapper->getRepository()->findOneBy(['name' => $request->request->get('add_tag')]))) {
                $this->addFlash('error', 'Tag already exists');
            } else {
                $tag = new Tag();
                $tag->setName($request->request->get('add_tag'));
                $tag->setUrlName($request->request->get('add_tag'));
                $tag->setLastModified(new DateTime());
                $tagMapper->getRepository()->addOrUpdateTag($tag);

                $this->addFlash('success', $translator->trans('Tag "{tag}" was added', ['tag' => $tag->getName()], 'admin'));
            }
        }

        return $this->redirectToRoute('admin_tags');
    }

    #[Route('/admin/tags/delete_orphans', name: 'admin_tags_delete_orphans')]
    public function deleteOrphans(TagMapper $tagMapper, TranslatorInterface $translator): Response
    {
        $tagMapper->deleteOrphanTags();
        $this->addFlash('success', $translator->trans('Orphan tags deleted', [], 'admin'));

        return $this->redirectToRoute('admin_tags');
    }

    #[Route('/admin/tags/permissions', name: 'admin_tags_permissions')]
    public function permissions(Request $request, Conf $conf, TranslatorInterface $translator): Response
    {
        $this->translator = $translator;
        $tpl_params = [];

        $form = $this->createForm(TagPermissionsType::class, $conf);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $confKey => $confParam) {
                $conf->addOrUpdateParam($confKey, $confParam['value'], $confParam['type']);
            }

            $this->addFlash('success', $translator->trans('Your configuration settings have been updated', [], 'admin'));

            $this->redirectToRoute('admin_tags_permissions');
        }

        $tpl_params['form'] = $form->createView();
        $tpl_params['PAGE_TITLE'] = $translator->trans('Tags', [], 'admin');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_tags');
        $tpl_params['tabsheet'] = $this->setTabsheet('permissions');

        return $this->render('tags_permissions.html.twig', $tpl_params);
    }

    #[Route('/admin/tags/pending', name: 'admin_tags_pending')]
    public function pending(Request $request, TagMapper $tagMapper, TranslatorInterface $translator, CsrfTokenManagerInterface $tokenManager): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        if ($request->isMethod('POST') && $request->request->has('tag_ids')) {
            if ($request->request->get('validate')) {
                $tagMapper->validateTags($request->request->all('tag_ids'));
                $this->addFlash('success', $translator->trans('Tags have been validated', [], 'admin'));
            } elseif ($request->request->get('reject')) {
                $tagMapper->rejectTags($request->request->all('tag_ids'));
                $this->addFlash('success', $translator->trans('Tags have been rejected', [], 'admin'));
            }

            $this->redirectToRoute('admin_tags_pending');
        }

        $tpl_params['tags'] = $tagMapper->getPendingTags();

        $tpl_params['csrf_token'] = $tokenManager->getToken('authenticate');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_tags_permissions');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_tags');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Tags', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('pending');

        return $this->render('tags_pending.html.twig', $tpl_params);
    }
}
