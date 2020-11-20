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
use App\Security\UserProvider;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\RouterInterface;

abstract class AdminCommonController extends AbstractController
{
    protected $conf, $userProvider, $commentRepository, $caddieRepository;

    public function __construct(UserProvider $userProvider, CommentRepository $commentRepository, CaddieRepository $caddieRepository)
    {
        $this->userProvider = $userProvider;
        $this->commentRepository = $commentRepository;
        $this->caddieRepository = $caddieRepository;
    }

    public function getUser()
    {
        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return;
        }

        $user = $this->userProvider->fromToken($token);

        return $user;
    }

    protected function menu(RouterInterface $router, User $user, EntityManager $em, Conf $conf, string $core_version): array
    {
        $tpl_params = [
            'USERNAME' => $user->getUsername(),
            'ENABLE_SYNCHRONIZATION' => $conf['enable_synchronization'],
            'U_SITE_MANAGER' => $router->generate('admin_site'),
            'U_HISTORY_STAT' => $router->generate('admin_history'),
            'U_SITES' => $router->generate('admin_site'),
            'U_MAINTENANCE' => $router->generate('admin_maintenance'),
            'U_CONFIG_GENERAL' => $router->generate('admin_configuration'),
            'U_CONFIG_MENUBAR' => $router->generate('admin_menubar'),
            'U_CONFIG_LANGUAGES' => $router->generate('admin_languages_installed'),
            'U_CONFIG_THEMES' => $router->generate('admin_themes_installed'),
            'U_ALBUMS' => $router->generate('admin_albums'),
            'U_ALBUMS_OPTIONS' => $router->generate('admin_albums_options'),
            'U_CAT_UPDATE' => $conf['enable_synchronization'] ? $router->generate('admin_synchronize', ['site' => 1]): '',
            'U_RATING' => $router->generate('admin_rating'),
            'U_RECENT_SET' => $router->generate('admin_batch_manager_global', ['filter' => 'last_import']),
            'U_BATCH' => $router->generate('admin_batch_manager_global'),
            'U_TAGS' => $router->generate('admin_tags'),
            'U_USERS' => $router->generate('admin_users'),
            'U_GROUPS' => $router->generate('admin_groups'),
            'U_NOTIFICATION_BY_MAIL' => $router->generate('admin_notification'),
            'U_RETURN' => $router->generate('homepage'),
            'U_ADMIN' => $router->generate('admin_home'),
            'U_LOGOUT' => $router->generate('logout'),
            'U_PLUGINS' => $router->generate('admin_plugins_installed'),
            'U_ADD_PHOTOS' => $router->generate('admin_photos_add'),
            'U_UPDATE' => $router->generate('admin_update'),
            'U_DEV_VERSION' => strpos($core_version, 'dev') !== false,
            'U_DEV_API' => $router->generate('api'),
        ];

        if ($conf['activate_comments']) {
            $tpl_params['U_COMMENTS'] = $router->generate('admin_comments');

            // pending comments
            $tpl_params['NB_PENDING_COMMENTS'] = $this->commentRepository->count(['validated' => false]);
        }

        // any photo in the caddie?
        $nb_photos_in_caddie = $this->caddieRepository->count(['user' => $user->getId()]);

        if ($nb_photos_in_caddie > 0) {
            $tpl_params['NB_PHOTOS_IN_CADDIE'] = $nb_photos_in_caddie;
            $tpl_params['U_CADDIE'] = $router->generate('admin_batch_manager_global', ['filter' => 'caddie']);
        }
        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];

        return $tpl_params;
    }
}
