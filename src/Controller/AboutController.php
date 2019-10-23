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
use Phyxo\Extension\Theme;
use Phyxo\MenuBar;
use Phyxo\Functions\Language;
use Symfony\Component\HttpFoundation\Request;

class AboutController extends CommonController {
    public function index(Request $request, Template $template, Conf $conf, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar, string $themesDir)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);

        $tpl_params['PAGE_TITLE'] = Language::l10n('About Phyxo');
        $tpl_params['ABOUT_MESSAGE'] = Language::load_language('about.html', '', ['language' => $this->getUser()->getLanguage(), 'return' => true]);
        if ($theme_about = Language::load_language('about.html', $themesDir . '/' . $this->getUser()->getTheme() . '/', ['language' => $this->getUser()->getLanguage(), 'return' => true])) {
            $template->assign('THEME_ABOUT', $theme_about);
        }

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        return $this->render('about.tpl', $tpl_params);
    }
}
