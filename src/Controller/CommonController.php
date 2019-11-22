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
use App\Security\UserProvider;
use Phyxo\Extension\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Phyxo\Functions\Language;

abstract class CommonController extends AbstractController
{
    protected $language_load,  $image_std_params, $userProvider, $user;

    public function __construct(UserProvider $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    public function getUser()
    {
        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return;
        }

        if (!$this->user) {
            $this->user = $this->userProvider->fromToken($token);
        }

        return $this->user;
    }

    public function loadLanguage(User $user)
    {
        $this->language_load = array_merge(
            Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $user->getLanguage(), 'return_vars' => true]
            ),
            Language::load_language(
                'admin.lang',
                __DIR__ . '/../../',
                ['language' => $user->getLanguage(), 'return_vars' => true]
            )
        );
    }

    public function addThemeParams(Template $template, Conf $conf, User $user, string $themesDir, string $phyxoVersion, string $phyxoWebsite): array
    {
        $tpl_params = [];

        $this->loadLanguage($user);

        $template->setUser($this->getUser());
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
        $tpl_params['PAGE_TITLE'] = $tpl_params['GALLERY_TITLE'];
        $tpl_params['CONTENT_ENCODING'] = 'utf-8';
        $tpl_params['U_HOME'] = $this->generateUrl('homepage');
        $tpl_params['LEVEL_SEPARATOR'] = $conf['level_separator'];
        $tpl_params['category_view'] = 'grid';

        \Phyxo\Functions\Plugin::trigger_notify('init');

        return $tpl_params;
    }
}
