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

use App\Repository\ConfigRepository;
use Phyxo\Block\BlockManager;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenubarController extends AdminCommonController
{
    public function index(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $menu = new BlockManager('menubar');
        $menu->loadDefaultBlocks();
        $menu->loadRegisteredBlocks();
        $menu->loadMenuConfig(json_decode($conf['blk_menubar'], true));
        $menu->prepareDisplay();
        $reg_blocks = $menu->getRegisteredBlocks();

        $mb_conf = json_decode($conf['blk_menubar'], true);
        $mb_conf = $this->makeConsecutive($reg_blocks, $mb_conf);

        foreach ($mb_conf as $id => $pos) {
            $tpl_params['blocks'][] = [
                'pos' => $pos / 5,
                'reg' => $reg_blocks[$id]
            ];
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_menubar');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_menubar');

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('menubar.tpl', $tpl_params);
    }

    public function update(Request $request, EntityManager $em, Conf $conf, TranslatorInterface $translator)
    {
        if ($request->isMethod('POST')) {
            $menu = new BlockManager('menubar');
            $menu->loadDefaultBlocks();
            $reg_blocks = $menu->getRegisteredBlocks();

            $mb_conf = json_decode($conf['blk_menubar'], true);

            foreach ($mb_conf as $id => $pos) {
                $hide = isset($_POST['hide_' . $id]);
                $mb_conf[$id] = ($hide ? -1 : +1) * abs($pos);

                if ($pos = $request->request->get('pos_' . $id)) {
                    $mb_conf[$id] = $mb_conf[$id] > 0 ? $pos : -$pos;
                }
            }
            $mb_conf = $this->makeConsecutive($reg_blocks, $mb_conf);
            $em->getRepository(ConfigRepository::class)->addOrUpdateParam('blk_' . $menu->getId(), $mb_conf);

            $this->addFlash('info', $translator->trans('Order of menubar items has been updated successfully.', [], 'admin'));
        }

        return $this->redirectToRoute('admin_menubar');
    }

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
}
