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

use Phyxo\Template\Template;
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Functions\Language;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AboutController extends CommonController
{
    public function index(Request $request, Template $template, Conf $conf, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar, string $themesDir, string $rootProjectDir,
                        TranslatorInterface $translator)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);

        $tpl_params['PAGE_TITLE'] = $translator->trans('About Phyxo');
        $tpl_params['ABOUT_MESSAGE'] = Language::loadLanguageFile('about.html', $rootProjectDir . '/languages/' . $this->getUser()->getLanguage());
        $tpl_params['THEME_ABOUT'] = Language::loadLanguageFile('about.html', $themesDir . '/' . $this->getUser()->getTheme() . '/languages/' . $this->getUser()->getLanguage());

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('about.tpl', $tpl_params);
    }
}
