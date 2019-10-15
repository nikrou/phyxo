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

class SiteUpdateController extends AdminCommonController
{
    public function permalinks(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_site_update');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_site_update');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Site update');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);

        return $this->render('site_update.tpl', $tpl_params);
    }
}
