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
use Phyxo\Functions\Language;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class TagsController extends AdminCommonController
{
    protected function setTabsheet(string $section = 'all'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('all', Language::l10n('All tags'), $this->generateUrl('admin_tags'));
        $tabsheet->add('permissions', Language::l10n('Permissions'), $this->generateUrl('admin_tags_permissions'), 'fa-lock');
        $tabsheet->add('pending', Language::l10n('Pendings'), $this->generateUrl('admin_tags_pending'), 'fa-clock');

        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function list(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $result = $em->getRepository(TagRepository::class)->getOrphanTags();
        $orphan_tags = $em->getConnection()->result2array($result);

        $orphan_tag_names = [];
        foreach ($orphan_tags as $tag) {
            $orphan_tag_names[] = \Phyxo\Functions\Plugin::trigger_change('render_tag_name', $tag['name'], $tag);
        }

        if (count($orphan_tag_names) > 0) {
            $tpl_params['warnings'][] = sprintf(
              Language::l10n('You have %d orphan tags: %s.') . ' <a href="%s">' . Language::l10n('Delete orphan tags') . '</a>',
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
            $tag['name'] = \Phyxo\Functions\Plugin::trigger_change('render_tag_name', $raw_name, $tag);
            if (empty($tag_counters[$tag['id']])) {
                $tag['counter'] = 0;
            } else {
                $tag['counter'] = intval($tag_counters[$tag['id']]);
            }
            $tag['U_VIEW'] = \Phyxo\Functions\URL::make_index_url(['tags' => [$tag]]);
            $tag['U_EDIT'] = 'admin/index.php?page=batch_manager&amp;filter=tag-' . $tag['id'];

            $alt_names = \Phyxo\Functions\Plugin::trigger_change('get_tag_alt_names', [], $raw_name);
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
        $tpl_params['PAGE_TITLE'] = Language::l10n('Tags');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('all'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('tags_all.tpl', $tpl_params);
    }

    public function actions(Request $request, EntityManager $em, TagMapper $tagMapper)
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
                        $this->addFlash('error', Language::l10n('Tag "%s" already exists', $tag_name));
                    } elseif (!empty($tag_name)) {
                        $updates[] = [
                            'id' => $tag_id,
                            'name' => $tag_name,
                            'url_name' => \Phyxo\Functions\Plugin::trigger_change('render_tag_url', $tag_name),
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
                        $this->addFlash('error', Language::l10n('Tag "%s" already exists', $tag_name));
                    } elseif (!empty($tag_name)) {
                        $em->getRepository(TagRepository::class)->insertTag(
                            $tag_name,
                            \Phyxo\Functions\Plugin::trigger_change('render_tag_url', $tag_name)
                        );

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

                        $this->addFlash('info', Language::l10n('Tag "%s" is now a duplicate of "%s"', $tag_name, $current_name_of[$tag_id]));
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
                $this->addFlash('error', Language::l10n('No destination tag selected'));
            } else {
                $destination_tag_id = $request->request->get('destination_tag');
                $tag_ids = $request->request->get('tags');

                if (is_array($tag_ids) && count($tag_ids) > 1) {
                    $name_of_tag = [];
                    $result = $em->getRepository(TagRepository::class)->findTags($tag_ids);
                    while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                        $name_of_tag[$row['id']] = \Phyxo\Functions\Plugin::trigger_change('render_tag_name', $row['name'], $row);
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

                    $this->addFlash('info', Language::l10n('Tags <em>%s</em> merged into tag <em>%s</em>', implode(', ', $tags_deleted), $name_of_tag[$destination_tag_id]));
                }
            }
        } elseif ($request->request->get('action') === 'delete' && $request->request->get('tags')) {
            if (!$request->request->get('confirm_deletion')) {
                $this->addFlash('error', Language::l10n('You need to confirm deletion'));
            } else {
                $result = $em->getRepository(TagRepository::class)->findTags($request->request->get('tags'));
                $tag_names = $em->getConnection()->result2array($result, null, 'name');

                $tagMapper->deleteTags($_POST['tags']);

                $this->addFlash('info', Language::l10n_dec('The following tag was deleted', 'The %d following tags were deleted', count($tag_names)) . ' : ' . implode(', ', $tag_names));
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

    public function deleteOrphans(Request $request, TagMapper $tagMapper)
    {
        $tagMapper->deleteOrphanTags();
        $this->addFlash('info', Language::l10n('Orphan tags deleted'));

        return $this->redirectToRoute('admin_tags');
    }

    public function permissions(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, TagMapper $tagMapper)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $status_options[null] = '----------';
        foreach (User::ALL_STATUS as $status) {
            $status_options[$status] = Language::l10n('user_status_' . $status);
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

            $this->addFlash('info', Language::l10n('Settings have been updated'));

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
        $tpl_params['PAGE_TITLE'] = Language::l10n('Tags');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('permissions'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('tags_permissions.tpl', $tpl_params);
    }

    public function pending(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, TagMapper $tagMapper)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST') && $request->request->get('tag_ids')) {
            if ($request->request->get('validate')) {
                $tagMapper->validateTags($request->request->get('tag_ids'));
                $this->addFlash('info', Language::l10n('Tags have been validated'));
            } elseif ($request->request->get('reject')) {
                $tagMapper->rejectTags($request->request->get('tag_ids'));
                $this->addFlash('info', Language::l10n('Tags have been rejected'));
            }

            $this->redirectToRoute('admin_tags_pending');
        }

        $tpl_params['tags'] = $tagMapper->getPendingTags();

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_tags_permissions');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_tags');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Tags');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('pending'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('tags_pending.tpl', $tpl_params);
    }
}
