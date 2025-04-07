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

use Exception;
use DateTime;
use App\DataMapper\UserMapper;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;
use App\Utils\DirectoryManager;
use Phyxo\Conf;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Theme\Themes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminThemesController extends AbstractController
{
    private TranslatorInterface $translator;
    protected function setTabsheet(string $section = 'installed'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('installed', $this->translator->trans('Installed Themes', [], 'admin'), $this->generateUrl('admin_themes_installed'), 'fa-paint-brush');
        $tabsheet->add('update', $this->translator->trans('Check for updates', [], 'admin'), $this->generateUrl('admin_themes_update'), 'fa-refresh');
        $tabsheet->add('new', $this->translator->trans('Add New Theme', [], 'admin'), $this->generateUrl('admin_themes_new'), 'fa-plus-circle');
        $tabsheet->select($section);

        return $tabsheet;
    }
    #[Route('/admin/themes', name: 'admin_themes_installed')]
    public function installed(
        ThemeRepository $themeRepository,
        UserMapper $userMapper,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        CsrfTokenManagerInterface $tokenManager
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $themes = new Themes($themeRepository, $userMapper);
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
                'SCREENSHOT' => $fs_theme['screenshot'] ?? null,
                'IS_DEFAULT' => ($theme_id === $default_theme),
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

                if ($children !== []) {
                    $tpl_theme['DELETABLE'] = false;
                    $tpl_theme['DELETE_TOOLTIP'] = $translator->trans('Impossible to delete this theme. Other themes depends on it: {themes}', ['themes' => implode(', ', $children)], 'admin');
                } else {
                    $tpl_theme['delete'] = $this->generateUrl('admin_themes_action', ['theme' => $theme_id, 'action' => 'delete']);
                }
            }

            $tpl_themes[] = $tpl_theme;
        }

        usort($tpl_themes, function ($a, $b): int|bool {
            if (!empty($a['IS_DEFAULT'])) {
                return -1;
            }

            if (!empty($b['IS_DEFAULT'])) {
                return 1;
            }

            $s = ['active' => 0, 'inactive' => 1];
            if ($a['state'] === $b['state']) {
                return strcasecmp((string) $a['NAME'], (string) $b['NAME']);
            } else {
                return $s[$a['state']] >= $s[$b['state']];
            }
        });

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $tokenManager->getToken('authenticate');
        $tpl_params['EXT_TYPE'] = 'themes';
        $tpl_params['themes'] = $tpl_themes;
        $tpl_params['theme_states'] = ['active', 'inactive'];

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_themes_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('installed');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_themes_installed');

        return $this->render('themes_installed.html.twig', $tpl_params);
    }
    #[Route('/admin/themes/update', name: 'admin_themes_update')]
    public function update(
        UserMapper $userMapper,
        Conf $conf,
        CsrfTokenManagerInterface $tokenManager,
        ThemeRepository $themeRepository,
        ParameterBagInterface $params,
        TranslatorInterface $translator
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $themes = new Themes($themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));
        $themes->setExtensionsURL($params->get('pem_url'));

        $tpl_params['SHOW_RESET'] = 0;
        if (!empty($conf['updates_ignored'])) {
            $updates_ignored = $conf['updates_ignored'];
        } else {
            $updates_ignored = ['plugins' => [], 'themes' => [], 'languages' => []];
        }

        $server_themes = $themes->getServerThemes($conf['pem_themes_category'], $params->get('core_version'), $new = false);
        $tpl_params['update_themes'] = [];

        if ($server_themes !== []) {
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
                        'EXT_DESC' => trim((string) $ext_info['extension_description'], " \n\r"),
                        'REV_DESC' => trim((string) $ext_info['revision_description'], " \n\r"),
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
                $tpl_params['SHOW_RESET'] = is_countable($updates_ignored['themes']) ? count($updates_ignored['themes']) : 0;
            }
        }

        $tpl_params['csrf_token'] = $tokenManager->getToken('authenticate');
        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['EXT_TYPE'] = 'themes';

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_themes_update');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('update');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_themes_installed');

        return $this->render('themes_update.html.twig', $tpl_params);
    }
    #[Route('/admin/themes/{action}/{theme}', name: 'admin_themes_action', requirements: ['action' => 'activate|deactivate|delete|set_default', 'theme' => '.+'])]
    public function action(
        string $theme,
        string $action,
        UserMapper $userMapper,
        ThemeRepository $themeRepository,
        UserInfosRepository $userInfosRepository,
        ParameterBagInterface $params,
        DirectoryManager $directoryManager,
        string $themesDir,
        string $publicThemesDir,
        Filesystem $fs
    ): Response {
        $themes = new Themes($themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));

        if ($action === 'set_default') {
            // first we need to know which users are using the current default theme
            $user_ids = [];
            foreach ($userInfosRepository->findBy(['theme' => $userMapper->getDefaultTheme()]) as $user) {
                $user_ids[] = $user->getUser()->getId();
            }

            $userInfosRepository->updateFieldForUsers('theme', $theme, $user_ids);
        } else {
            $error = $themes->performAction($action, $theme);
            if ($action === 'activate' && is_dir($originDir = $themesDir . '/' . $theme . '/build')) {
                try {
                    $targetDir = $publicThemesDir . '/' . $theme;
                    $fs->remove($targetDir);
                    $directoryManager->relativeSymlinkWithFallback($originDir, $targetDir);
                } catch (Exception $e) {
                    $error = 'Cannot copy web theme assets: ' . $e->getMessage();
                }
            }

            if ($error !== '' && $error !== '0') {
                $this->addFlash('error', $error);
            }
        }

        return $this->redirectToRoute('admin_themes_installed');
    }
    #[Route('/admin/themes/install/{revision}', name: 'admin_themes_install')]
    public function install(int $revision, ParameterBagInterface $params, UserMapper $userMapper, ThemeRepository $themeRepository, TranslatorInterface $translator): Response
    {
        if (!$userMapper->isWebmaster()) {
            $this->addFlash('error', $translator->trans('Webmaster status is required.', [], 'admin'));

            return $this->redirectToRoute('admin_themes_new');
        }

        $themes = new Themes($themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));
        $themes->setExtensionsURL($params->get('pem_url'));

        try {
            $themes->extractThemeFiles('install', $revision);
            $this->addFlash('success', $translator->trans('Theme has been successfully installed', [], 'admin'));

            return $this->redirectToRoute('admin_themes_installed');
        } catch (Exception $exception) {
            $this->addFlash('error', $translator->trans($exception->getMessage(), [], 'admin'));

            return $this->redirectToRoute('admin_themes_new');
        }
    }
    #[Route('/admin/themes/new', name: 'admin_themes_new')]
    public function new(ThemeRepository $themeRepository, UserMapper $userMapper, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        $themes = new Themes($themeRepository, $userMapper);
        $themes->setRootPath($params->get('themes_dir'));
        $themes->setExtensionsURL($params->get('pem_url'));

        $tpl_params['themes'] = [];

        foreach ($themes->getServerThemes($conf['pem_themes_category'], $params->get('core_version'), $new = true) as $theme) {
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
        $tpl_params['tabsheet'] = $this->setTabsheet('new');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_themes_installed');

        return $this->render('themes_new.html.twig', $tpl_params);
    }
    #[Route('/admin/themes/screenshot/{theme}', name: 'admin_theme_screenshot')]
    public function screenshot(string $theme, string $themesDir, MimeTypeGuesserInterface $mimeTypeGuesser): Response
    {
        $path = sprintf('%s/%s/screenshot.png', $themesDir, $theme);
        if (!is_readable($path)) {
            return new Response('screenshot not found', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->setEtag(md5_file($path));
        $response->setLastModified((new DateTime())->setTimestamp(filemtime($path)));
        $response->setMaxAge(3600);
        $response->setPublic();

        $response->headers->set('Content-Type', $mimeTypeGuesser->guessMimeType($path));

        return $response;
    }
}
