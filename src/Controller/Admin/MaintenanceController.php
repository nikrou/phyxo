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
use App\DataMapper\RateMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Repository\HistoryRepository;
use App\Repository\HistorySummaryRepository;
use App\Repository\ImageRepository;
use App\Repository\SearchRepository;
use App\Repository\UserFeedRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\Utils;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class MaintenanceController extends AdminCommonController
{
    private $translator;

    public function index(Request $request, ?string $action, Template $template, Conf $conf, EntityManager $em, ParameterBagInterface $params, CategoryMapper $categoryMapper,
                          UserMapper $userMapper, RateMapper $rateMapper, TagMapper $tagMapper, ImageStandardParams $image_std_params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        switch ($action) {
          case 'lock_gallery':
              {
                  $conf['gallery_locked'] = true;
                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'unlock_gallery':
              {
                  $conf['gallery_locked'] = false;
                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'categories':
              {
                  Utils::imagesIntegrity($em);
                  $categoryMapper->updateUppercats();
                  $categoryMapper->updateCategory('all');
                  $categoryMapper->updateGlobalRank();
                  $userMapper->invalidateUserCache(true);

                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'images':
              {
                  Utils::imagesIntegrity($em);

                  $result = $em->getRepository(ImageRepository::class)->findDistinctStorageId();
                  $cat_ids = $em->getConnection()->result2array($result, null, 'storage_category_id');
                  $fulldirs = $categoryMapper->getFulldirs($cat_ids);

                  foreach ($cat_ids as $cat_id) { // @TODO : use mass_updates ?
                      $em->getRepository(ImageRepository::class)->updatePathByStorageId($fulldirs[$cat_id], $cat_id);
                  }

                  $rateMapper->updateRatingScore();
                  $userMapper->invalidateUserCache();
                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'delete_orphan_tags':
              {
                  $tagMapper->deleteOrphanTags();
                  $this->addFlash('info', $translator->trans('Orphan tags deleted', [], 'admin'));

                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'user_cache':
              {
                  $userMapper->invalidateUserCache();

                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'history_detail':
              {
                  $em->getRepository(HistoryRepository::class)->deleteAll();
                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'history_summary':
              {
                  $em->getRepository(HistorySummaryRepository::class)->deleteAll();
                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'feeds':
              {
                return  $this->redirectToRoute('admin_maintenance');
                $em->getRepository(UserFeedRepository::class)->deleteUserFeedNotChecked();
              }
          case 'database':
              {
                  if ($em->getConnection()->do_maintenance_all_tables()) {
                      $this->addFlash('info', $translator->trans('All optimizations have been successfully completed.', [], 'admin'));
                  } else {
                      $this->addFlash('error', $translator->trans('Optimizations have been completed with some errors.', [], 'admin'));
                  }
                  return  $this->redirectToRoute('admin_maintenance');
              }
          case 'search':
              {
                  $em->getRepository(SearchRepository::class)->delete();
                  return  $this->redirectToRoute('admin_maintenance');
              }
          default:
              {
                  break;
              }
          }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_maintenance');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_maintenance');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Maintenance', [], 'admin');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);

        $tpl_params = array_merge($tpl_params, [
            'U_MAINT_CATEGORIES' => $this->generateUrl('admin_maintenance', ['action' => 'categories']),
            'U_MAINT_IMAGES' => $this->generateUrl('admin_maintenance', ['action' => 'images']),
            'U_MAINT_ORPHAN_TAGS' => $this->generateUrl('admin_maintenance', ['action' => 'delete_orphan_tags']),
            'U_MAINT_USER_CACHE' => $this->generateUrl('admin_maintenance', ['action' => 'user_cache']),
            'U_MAINT_HISTORY_DETAIL' => $this->generateUrl('admin_maintenance', ['action' => 'history_detail']),
            'U_MAINT_HISTORY_SUMMARY' => $this->generateUrl('admin_maintenance', ['action' => 'history_summary']),
            'U_MAINT_FEEDS' => $this->generateUrl('admin_maintenance', ['action' => 'feeds']),
            'U_MAINT_DATABASE' => $this->generateUrl('admin_maintenance', ['action' => 'database']),
            'U_MAINT_SEARCH' => $this->generateUrl('admin_maintenance', ['action' => 'search']),
            'U_MAINT_DERIVATIVES' => $this->generateUrl('admin_maintenance', ['action' => 'derivatives']),
        ]);

        $purge_urls[$translator->trans('All', [], 'admin')] = $this->generateUrl('admin_maintenance_derivatives', ['type' => 'all']);
        foreach ($image_std_params->getDefinedTypeMap() as $std_params) {
            $purge_urls[$translator->trans($std_params->type, [], 'admin')] = $this->generateUrl('admin_maintenance_derivatives', ['type' => $std_params->type]);
        }
        $purge_urls[$translator->trans(ImageStandardParams::IMG_CUSTOM, [], 'admin')] = $this->generateUrl('admin_maintenance_derivatives', ['type' => ImageStandardParams::IMG_CUSTOM]);

        $tpl_params['purge_derivatives'] = $purge_urls;

        if ($conf['gallery_locked']) {
            $tpl_params['U_MAINT_UNLOCK_GALLERY'] = $this->generateUrl('admin_maintenance', ['action' => 'unlock_gallery']);
        } else {
            $tpl_params['U_MAINT_LOCK_GALLERY'] = $this->generateUrl('admin_maintenance', ['action' => 'lock_gallery']);
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('maintenance.tpl', $tpl_params);
    }

    public function derivatives(string $type, ImageStandardParams $image_std_params)
    {
        Utils::clear_derivative_cache([$type], $image_std_params->getAllTypes());

        return  $this->redirectToRoute('admin_maintenance');
    }
}
