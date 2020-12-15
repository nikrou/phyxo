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
use Phyxo\Conf;
use Phyxo\Plugin\Plugins;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PluginsController extends AbstractController
{
    private $translator;

    protected function setTabsheet(string $section = 'installed')
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('installed', $this->translator->trans('Plugin list', [], 'admin'), $this->generateUrl('admin_plugins_installed'), 'fa-sliders');
        $tabsheet->add('update', $this->translator->trans('Check for updates', [], 'admin'), $this->generateUrl('admin_plugins_update'), 'fa-refresh');
        $tabsheet->add('new', $this->translator->trans('Other plugins', [], 'admin'), $this->generateUrl('admin_plugins_new'), 'fa-plus-circle');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function installed(Request $request, UserMapper $userMapper, PluginRepository $pluginRepository, Conf $conf, CsrfTokenManagerInterface $csrfTokenManager,
                            ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $plugins = new Plugins($pluginRepository, $userMapper);
        $plugins->setRootPath($params->get('plugins_dir'));
        $plugins->setExtensionsURL($params->get('pem_url'));
        $tpl_params['plugins_by_state'] = ['active' => 0, 'inactive' => 0, 'missing' => 0, 'obsolete' => 0];

        $tpl_plugins = [];
        foreach ($plugins->getFsPlugins() as $plugin_id => $fs_plugin) {
            $tpl_plugin = [
                'ID' => $plugin_id,
                'NAME' => $fs_plugin['name'],
                'VISIT_URL' => $fs_plugin['uri'],
                'VERSION' => $fs_plugin['version'],
                'DESC' => $fs_plugin['description'],
                'AUTHOR' => $fs_plugin['author'],
                'AUTHOR_URL' => isset($fs_plugin['author uri']) ? $fs_plugin['author uri'] : '',
            ];

            if (isset($plugins->getDbPlugins()[$plugin_id])) {
                $tpl_plugin['state'] = $plugins->getDbPlugins()[$plugin_id]['state'];
            } else {
                $tpl_plugin['state'] = 'inactive';
            }
            $tpl_params['plugins_by_state'][$tpl_plugin['state']]++;

            $tpl_plugins[] = $tpl_plugin;
        }

        $missing_plugin_ids = array_diff(
            array_keys($plugins->getDbPlugins()),
            array_keys($plugins->getFsPlugins())
        );

        if (count($missing_plugin_ids) > 0) {
            foreach ($missing_plugin_ids as $plugin_id) {
                $tpl_plugin = [
                    'NAME' => $plugin_id,
                    'VERSION' => $plugins->getDbPlugins()[$plugin_id]['version'],
                    'DESC' => $translator->trans('Error! This plugin is missing but it is installed! Uninstall it now.', [], 'admin'),
                    'state' => 'missing',
                ];
                $tpl_plugins[] = $tpl_plugin;
                $tpl_params['plugins_by_state'][$tpl_plugin['state']]++;
            }
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        try {
            $tpl_params['incompatible_plugins'] = $plugins->getIncompatiblePlugins($conf['pem_plugins_category'], $params->get('core_version'));
        } catch (\Exception $e) {
            // @TODO : do something usefull
        }

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['plugins'] = $tpl_plugins;
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_plugins_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Plugins', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('installed'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_plugins_installed');

        return $this->render('plugins_installed.html.twig', $tpl_params);
    }

    public function install(int $revision, PluginRepository $pluginRepository, ParameterBagInterface $params, UserMapper $userMapper, TranslatorInterface $translator)
    {
        if (!$userMapper->isWebmaster()) {
            $this->addFlash('error', $translator->trans('Webmaster status is required.', [], 'admin'));

            return $this->redirectToRoute('admin_plugins_new');
        }

        $plugins = new Plugins($pluginRepository, $userMapper);
        $plugins->setRootPath($params->get('plugins_dir'));
        $plugins->setExtensionsURL($params->get('pem_url'));

        try {
            $plugins->extractPluginFiles('install', $revision);
            $this->addFlash('info', $translator->trans('Plugin has been successfully installed', [], 'admin'));

            return $this->redirectToRoute('admin_plugins_installed');
        } catch (\Exception $e) {
            $this->addFlash('error', $translator->trans($e->getMessage(), [], 'admin'));

            return $this->redirectToRoute('admin_plugins_new');
        }
    }

    public function new(Request $request, UserMapper $userMapper, PluginRepository $pluginRepository, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $plugins = new Plugins($pluginRepository, $userMapper);
        $plugins->setRootPath($params->get('plugins_dir'));
        $plugins->setExtensionsURL($params->get('pem_url'));

        foreach ($plugins->getServerPlugins($new = true, $conf['pem_plugins_category'], $params->get('core_version')) as $plugin) {
            $ext_desc = trim($plugin['extension_description'], " \n\r");
            list($small_desc) = explode("\n", wordwrap($ext_desc, 200));

            $tpl_params['plugins'][] = [
                'ID' => $plugin['extension_id'],
                'EXT_NAME' => $plugin['extension_name'],
                'EXT_URL' => $params->get('pem_url') . '/extension_view.php?eid=' . $plugin['extension_id'],
                'SMALL_DESC' => trim($small_desc, " \r\n"),
                'BIG_DESC' => $ext_desc,
                'VERSION' => $plugin['revision_name'],
                'REVISION_DATE' => preg_replace('/[^0-9]/', '', $plugin['revision_date']),
                'AUTHOR' => $plugin['author_name'],
                'DOWNLOADS' => $plugin['extension_nb_downloads'],
                'URL_DOWNLOAD' => $plugin['download_url'] . '&amp;origin=phyxo_download',
                'install' => $this->generateUrl('admin_plugins_install', ['revision' => $plugin['revision_id']]),
            ];
        }

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_plugins_new');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Plugins', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('new'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_plugins_installed');

        return $this->render('plugins_new.html.twig', $tpl_params);
    }

    public function update(Request $request, UserMapper $userMapper, PluginRepository $pluginRepository, Conf $conf, CsrfTokenManagerInterface $csrfTokenManager,
                        ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        if (!$userMapper->isWebmaster()) {
            $this->addFlash('error', $translator->trans('Webmaster status is required.', [], 'admin'));

            return $this->redirectToRoute('admin_languages_new');
        }

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['SHOW_RESET'] = 0;
        if (!empty($conf['updates_ignored'])) {
            $updates_ignored = $conf['updates_ignored'];
        } else {
            $updates_ignored = ['plugins' => [], 'themes' => [], 'languages' => []];
        }

        $plugins = new Plugins($pluginRepository, $userMapper);
        $plugins->setRootPath($params->get('plugins_dir'));
        $plugins->setExtensionsURL($params->get('pem_url'));

        $server_plugins = $plugins->getServerPlugins($new = false, $conf['pem_plugins_category'], $params->get('core_version'));
        $tpl_params['update_plugins'] = [];

        if (count($server_plugins) > 0) {
            foreach ($plugins->getFsPlugins() as $extension_id => $fs_extension) {
                if (!isset($fs_extension['extension']) || !isset($server_plugins[$fs_extension['extension']])) {
                    continue;
                }

                $extension_info = $server_plugins[$fs_extension['extension']];

                if (!version_compare($fs_extension['version'], $extension_info['revision_name'], '>=')) {
                    $tpl_params['update_plugins'][] = [
                        'ID' => $extension_info['extension_id'],
                        'REVISION_ID' => $extension_info['revision_id'],
                        'EXT_ID' => $extension_id,
                        'EXT_NAME' => $fs_extension['name'],
                        'EXT_URL' => $params->get('pem_url') . '/extension_view.php?eid=' . $extension_info['extension_id'],
                        'EXT_DESC' => trim($extension_info['extension_description'], " \n\r"),
                        'REV_DESC' => trim($extension_info['revision_description'], " \n\r"),
                        'CURRENT_VERSION' => $fs_extension['version'],
                        'NEW_VERSION' => $extension_info['revision_name'],
                        'AUTHOR' => $extension_info['author_name'],
                        'DOWNLOADS' => $extension_info['extension_nb_downloads'],
                        'URL_DOWNLOAD' => $extension_info['download_url'] . '&amp;origin=phyxo',
                        'IGNORED' => in_array($extension_id, $updates_ignored['plugins']),
                    ];
                }
            }

            if (!empty($updates_ignored['plugins'])) {
                $tpl_params['SHOW_RESET'] = count($updates_ignored['plugins']);
            }
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['EXT_TYPE'] = 'plugins';
        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_plugins_update');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Plugins', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('update'), $tpl_params);

        $tpl_params['INSTALL_URL'] = $this->generateUrl('admin_plugins_installed');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_plugins_installed');

        return $this->render('plugins_update.html.twig', $tpl_params);
    }
}
