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
use App\Repository\UserInfosRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Language\Languages;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LanguagesController extends AdminCommonController
{
    private $translator;

    public function setTabsheet(string $section = 'installed')
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('installed', $this->translator->trans('Installed Languages', [], 'admin'), $this->generateUrl('admin_languages_installed'), 'fa-language');
        $tabsheet->add('update', $this->translator->trans('Check for updates', [], 'admin'), $this->generateUrl('admin_languages_update'), 'fa-refresh');
        $tabsheet->add('new', $this->translator->trans('Add New Language', [], 'admin'), $this->generateUrl('admin_languages_new'), 'fa-plus-circle');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function installed(Request $request, UserMapper $userMapper, EntityManager $em, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $default_language = $userMapper->getDefaultLanguage();

        $tpl_languages = [];

        $languages = new Languages($em, $userMapper);
        $languages->setRootPath($params->get('translator.default_path'));

        foreach ($languages->getFsLanguages() as $language_id => $language) {
            if (in_array($language_id, array_keys($languages->getDbLanguages()))) {
                $language['state'] = 'active';
                $language['deactivable'] = true;
                $language['CURRENT_VERSION'] = $languages->getDbLanguages()[$language_id]['version'];

                if (count($languages->getDbLanguages()) <= 1) {
                    $language['deactivable'] = false;
                    $language['deactivate_tooltip'] = $translator->trans('Impossible to deactivate this language, you need at least one language.', [], 'admin');
                }

                if ($language_id === $default_language) {
                    $language['deactivable'] = false;
                    $language['deactivate_tooltip'] = $translator->trans('Impossible to deactivate this language, first set another language as default.', [], 'admin');
                }
            } else {
                $language['state'] = 'inactive';
            }

            if ($language['state'] === 'active') {
                $language['action'] = $this->generateUrl('admin_languages_action', ['language' => $language_id, 'action' => 'deactivate']);
            } elseif ($language['state'] === 'inactive') {
                $language['action'] = $this->generateUrl('admin_languages_action', ['language' => $language_id, 'action' => 'activate']);
                $language['delete'] = $this->generateUrl('admin_languages_action', ['language' => $language_id, 'action' => 'delete']);
            }

            if ($language_id === $default_language) {
                $language['is_default'] = true;

                array_unshift($tpl_languages, $language);
            } else {
                $language['is_default'] = false;
                $language['set_default'] = $this->generateUrl('admin_languages_action', ['language' => $language_id, 'action' => 'set_default']);
                $tpl_languages[] = $language;
            }
        }

        $tpl_params['languages'] = $tpl_languages;
        $tpl_params['language_states'] = ['active', 'inactive'];

        $missing_language_ids = array_diff(
            array_keys($languages->getDbLanguages()),
            array_keys($languages->getFsLanguages())
        );

        if (count($missing_language_ids) > 0) {
            $em->getRepository(UserInfosRepository::class)->updateLanguageForLanguages($userMapper->getDefaultLanguage(), $missing_language_ids);
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_languages_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('installed'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_languages_installed');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('languages_installed.html.twig', $tpl_params);
    }

    public function action(string $language, string $action, EntityManager $em, UserMapper $userMapper, Conf $conf, ParameterBagInterface $params)
    {
        $languages = new Languages($em, $userMapper);
        $languages->setRootPath($params->get('translator.default_path'));

        $result = $em->getRepository(UserInfosRepository::class)->findByStatuses([User::STATUS_GUEST]);
        $guest_id = $em->getConnection()->result2array($result, null, 'user_id')[0];

        $error = $languages->performAction($action, $language, [$guest_id]);

        if (!empty($error)) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('admin_languages_installed');
    }

    public function new(Request $request, UserMapper $userMapper, EntityManager $em, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $languages = new Languages($em, $userMapper);
        $languages->setRootPath($params->get('translator.default_path'));
        $languages->setExtensionsURL($params->get('pem_url'));

        foreach ($languages->getServerLanguages($new = true, $conf['pem_languages_category'], $params->get('core_version')) as $language) {
            list($date, ) = explode(' ', $language['revision_date']);

            $tpl_params['languages'][] = [
                'EXT_NAME' => $language['extension_name'],
                'EXT_DESC' => $language['extension_description'],
                'EXT_URL' => $params->get('pem_url') . '/extension_view.php?eid=' . $language['extension_id'],
                'VERSION' => $language['revision_name'],
                'VER_DESC' => $language['revision_description'],
                'DATE' => $date,
                'AUTHOR' => $language['author_name'],
                'URL_INSTALL' => $this->generateUrl('admin_languages_install', ['revision' => $language['revision_id']]),
                'URL_DOWNLOAD' => $language['download_url'] . '&amp;origin=phyxo'
            ];
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_languages_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('new'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_languages_installed');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('languages_new.html.twig', $tpl_params);
    }

    public function install(int $revision, EntityManager $em, ParameterBagInterface $params, UserMapper $userMapper, TranslatorInterface $translator)
    {
        if (!$userMapper->isWebmaster()) {
            $this->addFlash('error', $translator->trans('Webmaster status is required.', [], 'admin'));

            return $this->redirectToRoute('admin_languages_new');
        }

        $languages = new Languages($em, $userMapper);
        $languages->setRootPath($params->get('translator.default_path'));
        $languages->setExtensionsURL($params->get('pem_url'));

        try {
            $languages->extractLanguageFiles('install', $revision);
            $this->addFlash('info', $translator->trans('Language has been successfully installed', [], 'admin'));

            return $this->redirectToRoute('admin_languages_installed');
        } catch (\Exception $e) {
            $this->addFlash('error', $translator->trans($e->getMessage(), [], 'admin'));

            return $this->redirectToRoute('admin_languages_new');
        }
    }

    public function update(Request $request, UserMapper $userMapper, EntityManager $em, Conf $conf, CsrfTokenManagerInterface $csrfTokenManager,
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
            $updates_ignored = json_decode($conf['updates_ignored'], true);
        } else {
            $updates_ignored = ['plugins' => [], 'themes' => [], 'languages' => []];
        }

        $languages = new Languages($em, $userMapper);
        $languages->setRootPath($params->get('translator.default_path'));
        $languages->setExtensionsURL($params->get('pem_url'));

        $server_languages = $languages->getServerLanguages($new = false, $conf['pem_languages_category'], $params->get('core_version'));
        $tpl_params['update_languages'] = [];

        if (count($server_languages) > 0) {
            foreach ($languages->getFsLanguages() as $extension_id => $fs_extension) {
                if (!isset($fs_extension['extension']) || !isset($server_languages[$fs_extension['extension']])) {
                    continue;
                }

                $extension_info = $server_languages[$fs_extension['extension']];

                if (!version_compare($fs_extension['version'], $extension_info['revision_name'], '>=')) {
                    $tpl_params['update_languages'][] = [
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
                        'IGNORED' => in_array($extension_id, $updates_ignored['languages']),
                    ];
                }
            }

            if (!empty($updates_ignored['languages'])) {
                $tpl_params['SHOW_RESET'] = count($updates_ignored['languages']);
            }
        }

        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['EXT_TYPE'] = 'languages';

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_languages_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('update'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_languages_installed');
        $tpl_params['INSTALL_URL'] = $this->generateUrl('admin_languages_installed');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('languages_update.html.twig', $tpl_params);
    }
}
