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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AdminApiController extends AbstractController
{
    public function index(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $tpl_params = [];

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['U_PAGE'] = $this->generateUrl('api');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('api');
        $tpl_params['PAGE_TITLE'] = 'API';

        return $this->render('api.html.twig', $tpl_params);
    }
}
