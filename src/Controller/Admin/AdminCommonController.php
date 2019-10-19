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

use App\Entity\User;
use App\Repository\CaddieRepository;
use App\Repository\CommentRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Extension\Theme;
use Phyxo\Functions\Language;
use Phyxo\Template\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;

abstract class AdminCommonController extends AbstractController
{
    protected $conf, $language_load = [];

    protected function loadLanguage(User $user)
    {
        $this->language_load = array_merge(
            Language::load_language(
            'common.lang',
            __DIR__ . '/../../../',
            ['language' => $user->getLanguage(), 'return_vars' => true]
            ),
            Language::load_language(
                'admin.lang',
                __DIR__ . '/../../../',
                ['language' => $user->getLanguage(), 'return_vars' => true]
            )
        );
    }

    protected function menu(RouterInterface $router, User $user, EntityManager $em, Conf $conf, string $core_version): array
    {
        $link_start = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=';

        $tpl_params = [
            'USERNAME' => $user->getUsername(),
            'ENABLE_SYNCHRONIZATION' => $conf['enable_synchronization'],
            'U_SITE_MANAGER' => $router->generate('admin_site'),
            'U_HISTORY_STAT' => $router->generate('admin_history'),
            'U_SITES' => $link_start . 'remote_site',
            'U_MAINTENANCE' => $router->generate('admin_maintenance'),
            'U_CONFIG_GENERAL' => $router->generate('admin_configuration'),
            'U_CONFIG_MENUBAR' => $router->generate('admin_menubar'),
            'U_CONFIG_LANGUAGES' => $router->generate('admin_languages_installed'),
            'U_CONFIG_THEMES' => $router->generate('admin_themes_installed'),
            'U_ALBUMS' => $router->generate('admin_albums'),
            'U_ALBUMS_OPTIONS' => $router->generate('admin_albums_options'),
            'U_CAT_UPDATE' => $router->generate('admin_synchronize', ['site' => 1]),
            'U_RATING' => $router->generate('admin_rating'),
            'U_RECENT_SET' => $link_start . 'batch_manager&amp;filter=prefilter-last_import',
            'U_BATCH' => $link_start . 'batch_manager',
            'U_TAGS' => $router->generate('admin_tags'),
            'U_USERS' => $router->generate('admin_users'),
            'U_GROUPS' => $router->generate('admin_groups'),
            'U_NOTIFICATION_BY_MAIL' => $link_start . 'notification_by_mail',
            'U_RETURN' => $router->generate('homepage'),
            'U_ADMIN' => $router->generate('admin_home'),
            'U_LOGOUT' => $router->generate('logout'),
            'U_PLUGINS' => $router->generate('admin_plugins_installed'),
            'U_ADD_PHOTOS' => $link_start . 'photos_add',
            'U_UPDATE' => $router->generate('admin_update'),
            'U_DEV_VERSION' => strpos($core_version, 'dev') !== false,
            'U_DEV_API' => './api.php',
            'U_DEV_JS_TESTS' => '../tests/functional/'
        ];

        if ($conf['activate_comments']) {
            $tpl_params['U_COMMENTS'] = $router->generate('admin_comments');

            // pending comments
            $nb_comments = $em->getRepository(CommentRepository::class)->count($validated = false);
            if ($nb_comments > 0) {
                $tpl_params['NB_PENDING_COMMENTS'] = $nb_comments;
            }
        }

        // any photo in the caddie?
        $nb_photos_in_caddie = (int) $em->getRepository(CaddieRepository::class)->count($user->getId());

        if ($nb_photos_in_caddie > 0) {
            $tpl_params['NB_PHOTOS_IN_CADDIE'] = $nb_photos_in_caddie;
            $tpl_params['U_CADDIE'] = $link_start . 'batch_manager&amp;filter=prefilter-caddie';
        }

        return $tpl_params;
    }

    public function addThemeParams(Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $this->loadLanguage($this->getUser());

        $template->setUser($this->getUser());
        $template->setRouter($this->get('router'));
        $template->setConf($conf);
        $template->setLang($this->language_load['lang']);
        $template->setLangInfo($this->language_load['lang_info']);
        $template->postConstruct();

        $template->setTheme(new Theme($params->get('admin_theme_dir'), '.'));

        $tpl_params['PHYXO_VERSION'] = $params->get('core_version');
        $tpl_params['PHYXO_URL'] = $params->get('phyxo_website');
        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];

        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $tpl_params;
    }
}
