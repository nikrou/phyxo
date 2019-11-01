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
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Plugin\Plugins;
use Phyxo\Template\Template;
use Phyxo\Theme\Themes;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class ExtensionController extends AdminCommonController
{
    protected $params, $conf;

    public function theme(Request $request, string $theme, EntityManager $em, UserMapper $userMapper, string $themesDir, Conf $conf, Template $template, ParameterBagInterface $params)
    {
        $tpl_params = [];
        $this->conf = $conf;
        $this->params = $params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);

        $themes = new Themes($em->getConnection(), $userMapper);
        $themes->setRootPath($themesDir);
        if (!in_array($theme, array_keys($themes->getFsThemes()))) {
            throw $this->createNotFoundException('Invalid theme');
        }

        $filename = $themesDir . '/' . $theme . '/admin/admin.inc.php';
        if (is_readable($filename)) {
            $load = (function ($themeConfiguration) {
                // For old Piwigo themes
                if (!defined('PHPWG_ROOT_PATH')) {
                    define('PHPWG_ROOT_PATH', $this->params->get('root_project_dir'));
                }
                if (!defined('PHPWG_THEMES_PATH')) {
                    define('PHPWG_THEMES_PATH', $this->params->get('themes_dir') . '/');
                }

                $user = $this->getUser();
                $conf = $this->conf;
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

    public function plugin(Request $request, string $plugin, EntityManager $em, UserMapper $userMapper, string $pluginsDir, Conf $conf, Template $template, ParameterBagInterface $params)
    {
        $tpl_params = [];
        $this->conf = $conf;
        $this->params = $params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);

        $plugins = new Plugins($em->getConnection(), $userMapper);
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

                include_once($pluginConfiguration);

                return [
                    'template_filename' => $template_filename,
                    'tpl_params' => $tpl_params
                ];
            });
        } else {
            throw $this->createNotFoundException('Missing plugin configuration file ' . $filename);
        }

        $pluginResponse = $load($filename, $conf, $this->getUser());
        $tpl_params = array_merge($tpl_params, $pluginResponse['tpl_params']);
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_plugins_installed');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_plugins_installed');


        return $this->render($pluginResponse['template_filename'], $tpl_params);
    }
}
