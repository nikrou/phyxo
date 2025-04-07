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
use Phyxo\Extension\AbstractTheme;
use Phyxo\Plugin\Plugins;
use Phyxo\Theme\Themes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminExtensionController extends AbstractController
{
    protected ParameterBagInterface $params;
    protected Conf $conf;
    #[Route('/admin/theme/{theme}', name: 'admin_theme')]
    public function theme(
        Request $request,
        string $theme,
        ThemeRepository $themeRepository,
        UserMapper $userMapper,
        string $themesDir,
        Conf $conf,
        ThemeLoader $themeLoader,
        TranslatorInterface $translator
    ): Response {
        $tpl_params = [];
        $this->conf = $conf;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_themes_installed');

        $themes = new Themes($themeRepository, $userMapper);
        $themes->setRootPath($themesDir);
        if (!in_array($theme, array_keys($themes->getFsThemes()))) {
            throw $this->createNotFoundException('Invalid theme');
        }

        $className = AbstractTheme::getClassName($theme);

        if (!class_exists($className)) {
            throw $this->createNotFoundException(sprintf('%s extending AbstractTheme cannot be found for theme %s', $className, $theme));
        }

        $themeLoader->addPath($themesDir . '/' . $theme);
        $themeConfig = new $className($this->conf);

        if ($request->isMethod('POST')) {
            $themeConfig->handleFormRequest($request);
            $this->addFlash('success', $translator->trans('Configuration has been updated'));
        }

        $tpl_params['theme_config'] = $themeConfig->getConfig();

        return $this->render($themeConfig->getAdminTemplate(), $tpl_params);
    }
    #[Route('/adminplugin/{plugin}', name: 'admin_plugin')]
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
            $load = (function ($pluginConfiguration, $conf, $user): array {
                // For old Piwigo plugins
                if (!defined('PHPWG_ROOT_PATH')) {
                    define('PHPWG_ROOT_PATH', $this->params->get('root_project_dir'));
                }

                if (!defined('PHPWG_PLUGINS_PATH')) {
                    define('PHPWG_PLUGINS_PATH', $this->params->get('plugins_dir') . '/');
                }

                $template_filename = '';
                $tpl_params = [];

                include_once $pluginConfiguration;

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
