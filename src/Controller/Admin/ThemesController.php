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
use App\Entity\User;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Theme\Themes;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ThemesController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'installed')
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('installed', $this->translator->trans('Installed Themes', [], 'admin'), $this->generateUrl('admin_themes_installed'), 'fa-paint-brush');
        $tabsheet->add('update', $this->translator->trans('Check for updates', [], 'admin'), $this->generateUrl('admin_themes_update'), 'fa-refresh');
        $tabsheet->add('new', $this->translator->trans('Add New Theme', [], 'admin'), $this->generateUrl('admin_themes_new'), 'fa-plus-circle');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function installed(Request $request, EntityManager $em, ThemeRepository $themeRepository, UserMapper $userMapper, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $themes = new Themes($em->getConnection(), $themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));

        $db_themes = $themes->getDbThemes();
        $db_theme_ids = [];
        foreach ($db_themes as $db_theme) {
            $db_theme_ids[] = $db_theme->getId();
        }
        $default_theme = $userMapper->getDefaultTheme();

        $tpl_themes = [];

        foreach ($themes->getFsThemes() as $theme_id => $fs_theme) {
            $tpl_theme = [
                'ID' => $theme_id,
                'NAME' => $fs_theme['name'],
                'VISIT_URL' => $fs_theme['uri'],
                'VERSION' => $fs_theme['version'],
                'DESC' => $fs_theme['description'],
                'AUTHOR' => $fs_theme['author'],
                'AUTHOR_URL' => $fs_theme['author uri'] ?? null,
                'PARENT' => $fs_theme['parent'] ?? null,
                'SCREENSHOT' => $fs_theme['screenshot'],
                'IS_MOBILE' => $fs_theme['mobile'],
                'ADMIN_URI' => $fs_theme['admin_uri'] ? $this->generateUrl('admin_theme', ['theme' => $theme_id]) : ''
            ];

            if (in_array($theme_id, $db_theme_ids)) {
                $tpl_theme['state'] = 'active';
                $tpl_theme['IS_DEFAULT'] = ($theme_id === $default_theme);
                $tpl_theme['DEACTIVABLE'] = true;
                $tpl_theme['deactivate'] = $this->generateUrl('admin_themes_action', ['theme' => $theme_id, 'action' => 'deactivate']);
                $tpl_theme['set_default'] = $this->generateUrl('admin_themes_action', ['theme' => $theme_id, 'action' => 'set_default']);

                if (count($db_theme_ids) <= 1) {
                    $tpl_theme['DEACTIVABLE'] = false;
                    $tpl_theme['DEACTIVATE_TOOLTIP'] = $translator->trans('Impossible to deactivate this theme, you need at least one theme.', [], 'admin');
                }
                if ($tpl_theme['IS_DEFAULT']) {
                    $tpl_theme['DEACTIVABLE'] = false;
                    $tpl_theme['DEACTIVATE_TOOLTIP'] = $translator->trans('Impossible to deactivate the default theme.', [], 'admin');
                }
            } else {
                $tpl_theme['state'] = 'inactive';

                // is the theme "activable" ?
                if (isset($fs_theme['activable']) && !$fs_theme['activable']) {
                    $tpl_theme['ACTIVABLE'] = false;
                    $tpl_theme['ACTIVABLE_TOOLTIP'] = $translator->trans('This theme was not designed to be directly activated', [], 'admin');
                } else {
                    $tpl_theme['ACTIVABLE'] = true;
                    $tpl_theme['activate'] = $this->generateUrl('admin_themes_action', ['theme' => $theme_id, 'action' => 'activate']);
                }

                $missing_parent = $themes->missingParentTheme($theme_id);
                if (isset($missing_parent)) {
                    $tpl_theme['ACTIVABLE'] = false;

                    $tpl_theme['ACTIVABLE_TOOLTIP'] = $translator->trans('Impossible to activate this theme, the parent theme is missing: {theme}', ['theme' => $missing_parent], 'admin');
                }

                // is the theme "deletable" ?
                $children = $themes->getChildrenThemes($theme_id);

                $tpl_theme['DELETABLE'] = true;

                if (count($children) > 0) {
                    $tpl_theme['DELETABLE'] = false;
                    $tpl_theme['DELETE_TOOLTIP'] = $translator->trans('Impossible to delete this theme. Other themes depends on it: {themes}', ['themes' => implode(', ', $children)], 'admin');
                } else {
                    $tpl_theme['delete'] = $this->generateUrl('admin_themes_action', ['theme' => $theme_id, 'action' => 'delete']);
                }
            }

            $tpl_themes[] = $tpl_theme;
        }

        usort($tpl_themes, function($a, $b) {
            if (!empty($a['IS_DEFAULT'])) {
                return -1;
            }
            if (!empty($b['IS_DEFAULT'])) {
                return 1;
            }
            $s = ['active' => 0, 'inactive' => 1];
            if ($a['state'] === $b['state']) {
                return strcasecmp($a['NAME'], $b['NAME']);
            } else {
                return $s[$a['state']] >= $s[$b['state']];
            }
        });

        $tpl_params['themes'] = $tpl_themes;
        $tpl_params['theme_states'] = ['active', 'inactive'];

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_themes_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('installed'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_themes_installed');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('themes_installed.html.twig', $tpl_params);
    }

    public function update(Request $request, EntityManager $em, UserMapper $userMapper, Conf $conf, CsrfTokenManagerInterface $csrfTokenManager,
                            ThemeRepository $themeRepository, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $themes = new Themes($em->getConnection(), $themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));
        $themes->setExtensionsURL($params->get('pem_url'));

        $tpl_params['SHOW_RESET'] = 0;
        if (!empty($conf['updates_ignored'])) {
            $updates_ignored = $conf['updates_ignored'];
        } else {
            $updates_ignored = ['plugins' => [], 'themes' => [], 'languages' => []];
        }

        $server_themes = $themes->getServerThemes($new = false, $conf['pem_themes_category'], $params->get('core_version'));
        $tpl_params['update_themes'] = [];

        if (count($server_themes) > 0) {
            foreach ($themes->getFsThemes() as $extension_id => $fs_extension) {
                if (!isset($fs_extension['extension']) || !isset($server_themes[$fs_extension['extension']])) {
                    continue;
                }

                $ext_info = $server_themes[$fs_extension['extension']];
                if (!version_compare($fs_extension['version'], $ext_info['revision_name'], '>=')) {
                    $tpl_params['update_themes'][] = [
                        'ID' => $ext_info['extension_id'],
                        'REVISION_ID' => $ext_info['revision_id'],
                        'EXT_ID' => $extension_id,
                        'EXT_NAME' => $fs_extension['name'],
                        'EXT_URL' => $params->get('pem_url') . '/extension_view.php?eid=' . $ext_info['extension_id'],
                        'EXT_DESC' => trim($ext_info['extension_description'], " \n\r"),
                        'REV_DESC' => trim($ext_info['revision_description'], " \n\r"),
                        'CURRENT_VERSION' => $fs_extension['version'],
                        'NEW_VERSION' => $ext_info['revision_name'],
                        'AUTHOR' => $ext_info['author_name'],
                        'DOWNLOADS' => $ext_info['extension_nb_downloads'],
                        'URL_DOWNLOAD' => $ext_info['download_url'] . '&amp;origin=phyxo',
                        'IGNORED' => in_array($extension_id, $updates_ignored['themes']),
                    ];
                }
            }

            if (!empty($updates_ignored['themes'])) {
                $tpl_params['SHOW_RESET'] = count($updates_ignored['themes']);
            }
        }

        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['EXT_TYPE'] = 'themes';

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_themes_update');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('update'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_themes_installed');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('themes_update.html.twig', $tpl_params);
    }

    public function action(string $theme, string $action, EntityManager $em, UserMapper $userMapper, ThemeRepository $themeRepository, ParameterBagInterface $params)
    {
        $themes = new Themes($em->getConnection(), $themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));

        $result = $em->getRepository(UserInfosRepository::class)->findByStatuses([User::STATUS_GUEST]);
        $guest_id = $em->getConnection()->result2array($result, null, 'user_id')[0];

        $error = $themes->performAction($action, $theme, [$guest_id]);

        if (!empty($error)) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('admin_themes_installed');
    }

    public function install(int $revision, EntityManager $em, ParameterBagInterface $params, UserMapper $userMapper, ThemeRepository $themeRepository, TranslatorInterface $translator)
    {
        if (!$userMapper->isWebmaster()) {
            $this->addFlash('error', $translator->trans('Webmaster status is required.', [], 'admin'));

            return $this->redirectToRoute('admin_themes_new');
        }

        $themes = new Themes($em->getConnection(), $themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));
        $themes->setExtensionsURL($params->get('pem_url'));

        try {
            $themes->extractThemeFiles('install', $revision);
            $this->addFlash('info', $translator->trans('Theme has been successfully installed', [], 'admin'));

            return $this->redirectToRoute('admin_themes_installed');
        } catch (\Exception $e) {
            $this->addFlash('error', $translator->trans($e->getMessage(), [], 'admin'));

            return $this->redirectToRoute('admin_themes_new');
        }
    }

    public function new(Request $request, EntityManager $em, ThemeRepository $themeRepository, UserMapper $userMapper, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $themes = new Themes($em->getConnection(), $themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));
        $themes->setExtensionsURL($params->get('pem_url'));

        $tpl_params['themes'] = [];

        foreach ($themes->getServerThemes(true, $conf['pem_themes_category'], $params->get('core_version')) as $theme) {
            $tpl_params['themes'][] = [
                'id' => $theme['extension_id'],
                'name' => $theme['extension_name'],
                'thumbnail' => $params->get('pem_url') . '/upload/extension-' . $theme['extension_id'] . '/thumbnail.jpg',
                'screenshot' => $params->get('pem_url') . '/upload/extension-' . $theme['extension_id'] . '/screenshot.jpg',
                'install' => $this->generateUrl('admin_themes_install', ['revision' => $theme['revision_id']])
            ];
        }

        $tpl_params['default_screenshot'] = 'admin/theme/images/missing_screenshot.png';

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_themes_new');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('new'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_themes_installed');
        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('themes_new.html.twig', $tpl_params);
    }
}
