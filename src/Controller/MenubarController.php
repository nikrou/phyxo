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

namespace App\Controller;

use Phyxo\Conf;
use Phyxo\MenuBar;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class MenubarController extends CommonController
{
    public function navigation(RequestStack $requestStack, Conf $conf, MenuBar $menuBar): Response
    {
        $tpl_params = [];

        $tpl_params['blocks'] = $menuBar->getBlocks();
        $tpl_params['current_route'] = $requestStack->getMainRequest()->get('_route');
        $tpl_params['current_route_params'] = $requestStack->getMainRequest()->get('_route_params');

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($requestStack->getSession()->get('_theme'), $conf));

        return $this->render('_menubar.html.twig', $tpl_params);
    }
}
