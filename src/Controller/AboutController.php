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

use App\Security\AppUserService;
use Phyxo\Conf;
use Phyxo\Functions\Language;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AboutController extends CommonController
{
    public function index(Request $request, AppUserService $appUserService, Conf $conf, string $themesDir, string $rootProjectDir, TranslatorInterface $translator)
    {
        $tpl_params = [];

        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];
        $tpl_params['PAGE_TITLE'] = $translator->trans('About Phyxo');
        $tpl_params['ABOUT_MESSAGE'] = Language::loadLanguageFile('about.html', $rootProjectDir . '/languages/' . $appUserService->getUser()->getLocale());
        $tpl_params['THEME_ABOUT'] = Language::loadLanguageFile('about.html', $themesDir . '/' . $appUserService->getUser()->getTheme() . '/languages/' . $appUserService->getUser()->getLocale());

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('about.html.twig', $tpl_params);
    }
}
