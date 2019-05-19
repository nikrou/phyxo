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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Phyxo\Template\Template;
use Phyxo\Conf;
use Phyxo\Extension\Theme;
use Phyxo\MenuBar;
use Phyxo\Functions\Language;

class AboutController extends AbstractController {
    public function index(Template $template, Conf $conf, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar, string $themesDir)
    {
        $this->conf = $conf;

        $this->language_load = Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $this->getUser()->getLanguage(), 'return_vars' => true]
        );
        $template->setConf($conf);
        $template->setLang($this->language_load['lang']);
        $template->setLangInfo($this->language_load['lang_info']);
        $template->postConstruct();

        // default theme
        $template->setTheme(new Theme(__DIR__ . '/../../themes', $this->getUser()->getTheme()));

        $template->assign('PHYXO_VERSION', $conf['show_version'] ? $phyxoVersion : '');
        $template->assign('PHYXO_URL', $phyxoWebsite);

        $tpl_params = [];
        $tpl_params['PAGE_TITLE'] = Language::l10n('About Phyxo');
        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];
        $tpl_params['CONTENT_ENCODING'] = 'utf-8';
        $tpl_params['U_HOME'] = $this->generateUrl('homepage');
        $tpl_params['LEVEL_SEPARATOR'] = $conf['level_separator'];
        $tpl_params['ABOUT_MESSAGE'] = Language::load_language('about.html', '', ['language' => $this->getUser()->getLanguage(), 'return' => true]);

        if ($theme_about = Language::load_language('about.html', $themesDir . '/themes/' . $this->getUser()->getTheme() . '/', ['language' => $this->getUser()->getLanguage(), 'return' => true])) {
            $template->assign('THEME_ABOUT', $theme_about);
        }

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        \Phyxo\Functions\Plugin::trigger_notify('init');

        return $this->render('about.tpl', $tpl_params);
    }
}
