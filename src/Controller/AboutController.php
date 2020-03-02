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
use Phyxo\Functions\Language;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AboutController extends CommonController
{
    public function index(Request $request, Conf $conf, MenuBar $menuBar, string $themesDir, string $rootProjectDir, TranslatorInterface $translator)
    {
        $tpl_params = [];

        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];
        $tpl_params['PAGE_TITLE'] = $translator->trans('About Phyxo');
        $tpl_params['ABOUT_MESSAGE'] = Language::loadLanguageFile('about.html', $rootProjectDir . '/languages/' . $this->getUser()->getLanguage());
        $tpl_params['THEME_ABOUT'] = Language::loadLanguageFile('about.html', $themesDir . '/' . $this->getUser()->getTheme() . '/languages/' . $this->getUser()->getLanguage());

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('about.html.twig', $tpl_params);
    }
}
