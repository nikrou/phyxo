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

use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\Language;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ApiController extends AdminCommonController
{
    public function index(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['U_PAGE'] = $this->generateUrl('api');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('api');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Api');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('api.tpl', $tpl_params);
    }
}
