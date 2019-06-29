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
use Phyxo\Template\Template;
use App\Entity\User;
use Phyxo\Extension\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Phyxo\Functions\Language;

abstract class CommonController extends AbstractController
{
    protected $language_load;

    public function addThemeParams(Template $template, Conf $conf, User $user, string $themesDir, string $phyxoVersion, string $phyxoWebsite): array
    {
        $tpl_params = [];

        $this->language_load = Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $user->getLanguage(), 'return_vars' => true]
        );

        $template->setRouter($this->get('router'));
        $template->setConf($conf);
        $template->setLang($this->language_load['lang']);
        $template->setLangInfo($this->language_load['lang_info']);
        $template->postConstruct();

        // default theme
        if (isset($this->image_std_params)) {
            $template->setImageStandardParams($this->image_std_params);
        }
        $template->setTheme(new Theme($themesDir, $this->getUser()->getTheme()));

        $tpl_params['PHYXO_VERSION'] = $conf['show_version'] ? $phyxoVersion : '';
        $tpl_params['PHYXO_URL'] = $phyxoWebsite;

        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];
        $tpl_params['CONTENT_ENCODING'] = 'utf-8';
        $tpl_params['U_HOME'] = $this->generateUrl('homepage');
        $tpl_params['LEVEL_SEPARATOR'] = $conf['level_separator'];

        \Phyxo\Functions\Plugin::trigger_notify('init');

        return $tpl_params;
    }
}
