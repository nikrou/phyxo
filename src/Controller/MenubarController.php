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

use Phyxo\MenuBar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MenubarController extends AbstractController
{
    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/menubar_navigation', name: 'public_menubar_navigation')]
    public function __invoke(RequestStack $requestStack, MenuBar $menuBar, array $publicTemplates): Response
    {
        $tpl_params = [];

        $tpl_params['blocks'] = $menuBar->getBlocks();
        $tpl_params['current_route'] = $requestStack->getMainRequest()->get('_route');
        $tpl_params['current_route_params'] = $requestStack->getMainRequest()->get('_route_params');

        return $this->render(sprintf('%s.html.twig', $publicTemplates['menubar']), $tpl_params);
    }
}
