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
use App\Security\UserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

abstract class CommonController extends AbstractController
{
    protected $image_std_params, $userProvider, $user, $defaultTheme, $themesDir, $conf, $phyxoVersion, $phyxoWebsite, $session;

    public function __construct(UserProvider $userProvider, string $defaultTheme, string $themesDir, Conf $conf, string $phyxoVersion, string $phyxoWebsite, SessionInterface $session)
    {
        $this->userProvider = $userProvider;
        $this->defaultTheme = $defaultTheme;
        $this->themesDir = $themesDir;
        $this->conf = $conf;
        $this->phyxoVersion = $phyxoVersion;
        $this->phyxoWebsite = $phyxoWebsite;
        $this->session = $session;
    }

    public function getUser()
    {
        if (($token = $this->container->get('security.token_storage')->getToken()) === null) {
            return;
        }

        if (!$this->user) {
            $this->user = $this->userProvider->fromToken($token);

            if ($token instanceof AnonymousToken) {
                $this->session->set('_theme', $this->user->getTheme());
                $this->session->set('_locale', $this->user->getLocale());
            }
        }

        return $this->user;
    }

    protected function loadThemeConf(string $theme = null, Conf $core_conf = null): array
    {
        if (empty($theme)) {
            $theme = $this->defaultTheme;
        }

        $themeconf_filename = sprintf('%s/%s/themeconf.inc.php', $this->themesDir, $theme);
        if (!is_readable($themeconf_filename)) {
            return [];
        }

        $extra_params = [];
        ob_start();
        // inject variables and objects in loaded theme
        $conf = $core_conf;
        $extra_params = require $themeconf_filename;
        ob_end_clean();

        return $extra_params;
    }

    public function addThemeParams(Conf $conf): array
    {
        $tpl_params = [];

        $tpl_params['GALLERY_TITLE'] = $conf['gallery_title'];
        $tpl_params['PAGE_TITLE'] = '';
        $tpl_params['CONTENT_ENCODING'] = 'utf-8';
        $tpl_params['U_HOME'] = $this->generateUrl('homepage');
        $tpl_params['LEVEL_SEPARATOR'] = $conf['level_separator'];
        $tpl_params['category_view'] = 'grid';

        return $tpl_params;
    }
}
