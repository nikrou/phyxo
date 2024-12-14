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
use App\DataMapper\UserMapper;
use App\Repository\LanguageRepository;
use App\Repository\UserInfosRepository;
use Phyxo\Conf;
use Phyxo\Language\Languages;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminLanguagesController extends AbstractController
{
    private TranslatorInterface $translator;

    public function setTabsheet(string $section = 'installed'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('installed', $this->translator->trans('Installed Languages', [], 'admin'), $this->generateUrl('admin_languages_installed'), 'fa-language');
        $tabsheet->add('update', $this->translator->trans('Check for updates', [], 'admin'), $this->generateUrl('admin_languages_update'), 'fa-refresh');
        $tabsheet->add('new', $this->translator->trans('Add New Language', [], 'admin'), $this->generateUrl('admin_languages_new'), 'fa-plus-circle');
        $tabsheet->select($section);

        return $tabsheet;
    }

    public function installed(
        UserMapper $userMapper,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        LanguageRepository $languageRepository,
        UserInfosRepository $userInfosRepository
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $default_language = $userMapper->getDefaultLanguage();

        $tpl_languages = [];

        $languages = new Languages($languageRepository, $userMapper->getDefaultLanguage());
        $languages->setRootPath($params->get('translator.default_path'));

        foreach ($languages->getFsLanguages() as $language_id => $language) {
            if (in_array($language_id, array_keys($languages->getDbLanguages()))) {
                $language['state'] = 'active';
                $language['deactivable'] = true;
                $language['CURRENT_VERSION'] = $languages->getDbLanguages()[$language_id]->getVersion();

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

        if ($missing_language_ids !== []) {
            $userInfosRepository->updateLanguageForLanguages($userMapper->getDefaultLanguage(), $missing_language_ids);
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_languages_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('installed');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_languages_installed');

        return $this->render('languages_installed.html.twig', $tpl_params);
    }

    public function action(
        string $language,
        string $action,
        UserMapper $userMapper,
        LanguageRepository $languageRepository,
        UserInfosRepository $userInfosRepository,
        ParameterBagInterface $params
    ): Response {
        $languages = new Languages($languageRepository, $userMapper->getDefaultLanguage());
        $languages->setRootPath($params->get('translator.default_path'));

        if ($action === 'set_default') {
            $userInfosRepository->updateFieldForUsers('language', $language, [$userMapper->getDefaultUser()->getId()]);
        } else {
            $error = $languages->performAction($action, $language);
        }

        if (!empty($error)) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('admin_languages_installed');
    }

    public function new(UserMapper $userMapper, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator, LanguageRepository $languageRepository): Response
    {
        $tpl_params = [];
        $this->translator = $translator;

        $languages = new Languages($languageRepository, $userMapper->getDefaultLanguage());
        $languages->setRootPath($params->get('translator.default_path'));
        $languages->setExtensionsURL($params->get('pem_url'));

        foreach ($languages->getServerLanguages($conf['pem_languages_category'], $params->get('core_version'), $new = true) as $language) {
            [$date, ] = explode(' ', (string) $language['revision_date']);

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
        $tpl_params['tabsheet'] = $this->setTabsheet('new');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_languages_installed');

        return $this->render('languages_new.html.twig', $tpl_params);
    }

    public function install(
        int $revision,
        ParameterBagInterface $params,
        UserMapper $userMapper,
        TranslatorInterface $translator,
        LanguageRepository $languageRepository
    ): Response {
        if (!$userMapper->isWebmaster()) {
            $this->addFlash('error', $translator->trans('Webmaster status is required.', [], 'admin'));

            return $this->redirectToRoute('admin_languages_new');
        }

        $languages = new Languages($languageRepository, $userMapper->getDefaultLanguage());
        $languages->setRootPath($params->get('translator.default_path'));
        $languages->setExtensionsURL($params->get('pem_url'));

        try {
            $languages->extractLanguageFiles('install', $revision);
            $this->addFlash('success', $translator->trans('Language has been successfully installed', [], 'admin'));

            return $this->redirectToRoute('admin_languages_installed');
        } catch (Exception $e) {
            $this->addFlash('error', $translator->trans($e->getMessage(), [], 'admin'));

            return $this->redirectToRoute('admin_languages_new');
        }
    }

    public function update(
        UserMapper $userMapper,
        Conf $conf,
        CsrfTokenManagerInterface $csrfTokenManager,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        LanguageRepository $languageRepository
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        if (!$userMapper->isWebmaster()) {
            $this->addFlash('error', $translator->trans('Webmaster status is required.', [], 'admin'));

            return $this->redirectToRoute('admin_languages_new');
        }

        $tpl_params['SHOW_RESET'] = 0;
        if (!empty($conf['updates_ignored'])) {
            $updates_ignored = $conf['updates_ignored'];
        } else {
            $updates_ignored = ['plugins' => [], 'themes' => [], 'languages' => []];
        }

        $languages = new Languages($languageRepository, $userMapper->getDefaultLanguage());
        $languages->setRootPath($params->get('translator.default_path'));
        $languages->setExtensionsURL($params->get('pem_url'));

        $server_languages = $languages->getServerLanguages($conf['pem_languages_category'], $params->get('core_version'), $new = false);
        $tpl_params['update_languages'] = [];

        if ($server_languages !== []) {
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
                        'EXT_DESC' => trim((string) $extension_info['extension_description'], " \n\r"),
                        'REV_DESC' => trim((string) $extension_info['revision_description'], " \n\r"),
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
                $tpl_params['SHOW_RESET'] = is_countable($updates_ignored['languages']) ? count($updates_ignored['languages']) : 0;
            }
        }

        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['EXT_TYPE'] = 'languages';

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_languages_installed');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Languages', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('update');

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_languages_installed');
        $tpl_params['INSTALL_URL'] = $this->generateUrl('admin_languages_installed');

        return $this->render('languages_update.html.twig', $tpl_params);
    }
}
