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

use App\DataMapper\UserMapper;
use App\Repository\PluginRepository;
use App\Repository\ThemeRepository;
use App\Security\AppUserService;
use App\Twig\ThemeLoader;
use Phyxo\Conf;
use Phyxo\Plugin\Plugins;
use Phyxo\Theme\Themes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class AdminExtensionController extends AbstractController
{
    protected ParameterBagInterface $params;
    protected Conf $conf;

    public function theme(
        string $theme,
        ThemeRepository $themeRepository,
        UserMapper $userMapper,
        string $themesDir,
        Conf $conf,
        ThemeLoader $themeLoader,
        ParameterBagInterface $params,
        AppUserService $appUserService
    ): Response {
        $tpl_params = [];
        $this->conf = $conf;
        $this->params = $params;

        $themes = new Themes($themeRepository, $userMapper);
        $themes->setRootPath($themesDir);
        if (!in_array($theme, array_keys($themes->getFsThemes()))) {
            throw $this->createNotFoundException('Invalid theme');
        }

        $filename = $themesDir . '/' . $theme . '/admin/admin.inc.php';
        if (is_readable($filename)) {
            $themeLoader->addPath($this->params->get('themes_dir') . '/' . $theme . '/admin/template');

            $load = (function ($themeConfiguration) use ($appUserService) {
                // For old Piwigo themes
                if (!defined('PHPWG_ROOT_PATH')) {
                    define('PHPWG_ROOT_PATH', $this->params->get('root_project_dir'));
                }
                if (!defined('PHPWG_THEMES_PATH')) {
                    define('PHPWG_THEMES_PATH', $this->params->get('themes_dir') . '/');
                }

                $user = $appUserService->getUser();
                $conf = $this->conf;
                $template_filename = '';
                $tpl_params = [];

                include_once($themeConfiguration);

                return [
                    'template_filename' => $template_filename,
                    'tpl_params' => $tpl_params
                ];
            });
        } else {
            throw $this->createNotFoundException('Missing theme configuration file ' . $filename);
        }

        $themeResponse = $load($filename);
        $tpl_params = array_merge($tpl_params, $themeResponse['tpl_params']);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_themes_installed');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_themes_installed');

        return $this->render($themeResponse['template_filename'], $tpl_params);
    }

    public function plugin(
        string $plugin,
        PluginRepository $pluginRepository,
        AppUserService $appUserService,
        UserMapper $userMapper,
        string $pluginsDir,
        Conf $conf,
        ParameterBagInterface $params
    ): Response {
        $tpl_params = [];
        $this->conf = $conf;
        $this->params = $params;

        $plugins = new Plugins($pluginRepository, $userMapper);
        $plugins->setRootPath($pluginsDir);
        if (!in_array($plugin, array_keys($plugins->getDbPlugins()))) {
            throw $this->createNotFoundException('Invalid plugin');
        }

        $filename = $pluginsDir . '/' . $plugin . '/admin.php';
        if (is_readable($filename)) {
            $load = (function ($pluginConfiguration, $conf, $user) {
                // For old Piwigo plugins
                if (!defined('PHPWG_ROOT_PATH')) {
                    define('PHPWG_ROOT_PATH', $this->params->get('root_project_dir'));
                }
                if (!defined('PHPWG_PLUGINS_PATH')) {
                    define('PHPWG_PLUGINS_PATH', $this->params->get('plugins_dir') . '/');
                }

                $template_filename = '';
                $tpl_params = [];

                include_once($pluginConfiguration);

                return [
                    'template_filename' => $template_filename,
                    'tpl_params' => $tpl_params
                ];
            });
        } else {
            throw $this->createNotFoundException('Missing plugin configuration file ' . $filename);
        }

        $pluginResponse = $load($filename, $conf, $appUserService->getUser());
        $tpl_params = array_merge($tpl_params, $pluginResponse['tpl_params']);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_plugins_installed');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_plugins_installed');

        return $this->render($pluginResponse['template_filename'], $tpl_params);
    }
}
