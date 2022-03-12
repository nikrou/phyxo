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

use App\Repository\CaddieRepository;
use App\Repository\CommentRepository;
use App\Security\AppUserService;
use Phyxo\Block\BlockManager;
use Phyxo\Conf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminMenubarController extends AbstractController
{
    public function index(Conf $conf, EventDispatcherInterface $eventDispatcher): Response
    {
        $tpl_params = [];

        if (is_null($conf['blk_menubar'])) {
            $mb_conf = [];
        } else {
            $mb_conf = $conf['blk_menubar'];
        }

        $menu = new BlockManager('menubar');
        $menu->loadDefaultBlocks();
        $menu->loadRegisteredBlocks($eventDispatcher);
        $menu->loadMenuConfig($mb_conf);
        $menu->prepareDisplay();
        $reg_blocks = $menu->getRegisteredBlocks();

        $mb_conf = $this->makeConsecutive($reg_blocks, $mb_conf);

        foreach ($mb_conf as $id => $pos) {
            if (isset($reg_blocks[$id])) {
                $tpl_params['blocks'][] = [
                    'pos' => $pos / 5,
                    'reg' => $reg_blocks[$id]
                ];
            }
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_menubar');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_menubar');

        return $this->render('menubar.html.twig', $tpl_params);
    }

    public function update(Request $request, Conf $conf, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator): Response
    {
        if ($request->isMethod('POST')) {
            $menu = new BlockManager('menubar');
            $menu->loadDefaultBlocks();
            $menu->loadRegisteredBlocks($eventDispatcher);
            $reg_blocks = $menu->getRegisteredBlocks();

            if (is_null($conf['blk_menubar'])) {
                $mb_conf = [];
            } else {
                $mb_conf = $conf['blk_menubar'];
            }

            foreach ($mb_conf as $id => $pos) {
                $hide = $request->request->get('hide_' . $id);
                $mb_conf[$id] = ($hide ? -1 : +1) * abs($pos);

                if ($pos = $request->request->get('pos_' . $id)) {
                    $mb_conf[$id] = $mb_conf[$id] > 0 ? $pos : -$pos;
                }
            }
            $mb_conf = $this->makeConsecutive($reg_blocks, $mb_conf);
            $conf->addOrUpdateParam('blk_' . $menu->getId(), $mb_conf, 'json');

            $this->addFlash('success', $translator->trans('Order of menubar items has been updated successfully.', [], 'admin'));
        }

        return $this->redirectToRoute('admin_menubar');
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    private function makeConsecutive(array $blocks = [], array $orders = [], $step = 50): array
    {
        uasort($orders, function($a, $b) {
            return abs($a) - abs($b);
        });

        $idx = 1;
        foreach ($blocks as $id => $block) {
            if (!isset($orders[$id])) {
                $orders[$id] = $idx * 50;
            }
            $idx++;
        }

        $crt = 1;
        foreach ($orders as $id => $pos) {
            $orders[$id] = $step * ($pos < 0 ? -$crt : $crt);
            $crt++;
        }

        return $orders;
    }

    public function navigation(AppUserService $appUserService, Conf $conf, CaddieRepository $caddieRepository, CommentRepository $commentRepository, ParameterBagInterface $params): Response
    {
        $tpl_params = [
            'USERNAME' => $appUserService->getUser()->getUserIdentifier(),
            'ENABLE_SYNCHRONIZATION' => $conf['enable_synchronization'],
            'U_HISTORY_STAT' => $this->generateUrl('admin_history'),
            'U_MAINTENANCE' => $this->generateUrl('admin_maintenance'),
            'U_CONFIG_GENERAL' => $this->generateUrl('admin_configuration'),
            'U_CONFIG_MENUBAR' => $this->generateUrl('admin_menubar'),
            'U_CONFIG_LANGUAGES' => $this->generateUrl('admin_languages_installed'),
            'U_CONFIG_THEMES' => $this->generateUrl('admin_themes_installed'),
            'U_ALBUMS' => $this->generateUrl('admin_albums'),
            'U_ALBUMS_OPTIONS' => $this->generateUrl('admin_albums_options'),
            'U_CAT_UPDATE' => $conf['enable_synchronization'] ? $this->generateUrl('admin_synchronize', ['site' => 1]): '',
            'U_RATING' => $this->generateUrl('admin_rating'),
            'U_RECENT_SET' => $this->generateUrl('admin_batch_manager_global', ['filter' => 'last_import']),
            'U_BATCH' => $this->generateUrl('admin_batch_manager_global'),
            'U_TAGS' => $this->generateUrl('admin_tags'),
            'U_USERS' => $this->generateUrl('admin_users'),
            'U_GROUPS' => $this->generateUrl('admin_groups'),
            'U_NOTIFICATION_BY_MAIL' => $this->generateUrl('admin_notification'),
            'U_RETURN' => $this->generateUrl('homepage'),
            'U_ADMIN' => $this->generateUrl('admin_home'),
            'U_LOGOUT' => $this->generateUrl('logout'),
            'U_PLUGINS' => $this->generateUrl('admin_plugins_installed'),
            'U_ADD_PHOTOS' => $this->generateUrl('admin_photos_add'),
            'U_UPDATE' => $this->generateUrl('admin_update'),
            'U_DEV_VERSION' => strpos($params->get('core_version'), 'dev') !== false,
            'U_DEV_API' => $this->generateUrl('api'),
        ];

        if ($conf['activate_comments']) {
            $tpl_params['U_COMMENTS'] = $this->generateUrl('admin_comments');

            // pending comments
            $tpl_params['NB_PENDING_COMMENTS'] = $commentRepository->count(['validated' => false]);
        }

        // any photo in the caddie?
        $nb_photos_in_caddie = $caddieRepository->count(['user' => $appUserService->getUser()->getId()]);

        if ($nb_photos_in_caddie > 0) {
            $tpl_params['NB_PHOTOS_IN_CADDIE'] = $nb_photos_in_caddie;
            $tpl_params['U_CADDIE'] = $this->generateUrl('admin_batch_manager_global', ['filter' => 'caddie']);
        }
        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];

        return $this->render('_menubar.html.twig', $tpl_params);
    }
}
