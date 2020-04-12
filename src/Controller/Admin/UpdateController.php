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
use App\Repository\UpgradeRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Update\Updates;
use Phyxo\Upgrade;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class UpdateController extends AdminCommonController
{
    private $translator;

    protected function setTabsheet(string $section = 'core'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('core', $this->translator->trans('Phyxo Update', [], 'admin'), $this->generateUrl('admin_update'));
        $tabsheet->add('extensions', $this->translator->trans('Extensions Update', [], 'admin'), $this->generateUrl('admin_update_extensions'));
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function core(Request $request, int $step = 0, string $version = null, Conf $conf, EntityManager $em, UserMapper $userMapper,
                        ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        /*
        STEP:
        0 = check is needed. If version is latest or check fail, we stay on step 0
        1 = new version on same branch AND new branch are available => user may choose upgrade.
        2 = upgrade on same branch
        3 = upgrade on different branch
         */
        $upgrade_to = $version;
        $obsolete_file = $params->get('install_dir') . '/obsolete.list';

        // +-----------------------------------------------------------------------+
        // |                                Step 0                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 0) {
            $tpl_params['CHECK_VERSION'] = false;
            $tpl_params['DEV_VERSION'] = false;

            $updater = new Updates($em->getConnection(), $userMapper, $params->get('core_version'));
            $updater->setUpdateUrl($params->get('update_url'));

            if (preg_match('/.*-dev$/', $params->get('core_version'), $matches)) {
                $tpl_params['DEV_VERSION'] = true;
            } elseif (preg_match('/(\d+\.\d+)\.(\d+)/', $params->get('core_version'), $matches)) {
                try {
                    $all_versions = $updater->getAllVersions();
                    $tpl_params['CHECK_VERSION'] = true;
                    $last_version = trim($all_versions[0]['version']);
                    $upgrade_to = $last_version;

                    if (version_compare($params->get('core_version'), $last_version, '<')) {
                        $new_branch = preg_replace('/(\d+\.\d+)\.\d+/', '$1', $last_version);
                        $actual_branch = $matches[1];

                        if ($new_branch === $actual_branch) {
                            $step = 2;
                        } else {
                            $step = 3;

                            // Check if new version exists in same branch
                            foreach ($all_versions as $version) {
                                $new_branch = preg_replace('/(\d+\.\d+)\.\d+/', '$1', $version);

                                if ($new_branch === $actual_branch) {
                                    if (version_compare($params->get('core_version'), $version, '<')) {
                                        $step = 1;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $tpl_params['LAST_ERROR_MESSAGE'] = $e->getMessage();
                }
            }
        }

        // +-----------------------------------------------------------------------+
        // |                                Step 1                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 1) {
            $tpl_params['MINOR_VERSION'] = $version;
            $tpl_params['MAJOR_VERSION'] = $last_version;

            $tpl_params['U_UPDATE_MINOR'] = $this->generateUrl('admin_update', ['step' => 2, 'version' => $version]);
            $tpl_params['U_UPDATE_MAJOR'] = $this->generateUrl('admin_update', ['step' => 3, 'version' => $last_version]);
        }

        // +-----------------------------------------------------------------------+
        // |                                Step 2                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 2 && $userMapper->isWebmaster()) {
            if ($request->isMethod('POST') && $request->request->get('upgrade_to')) {
                $zip = $params->get('cache_dir') . '/update' . '/' . $request->request->get('upgrade_to') . '.zip';
                $updater->upgradeTo($request->request->get('upgrade_to'));
                $updater->download($zip);

                $fs = new Filesystem();
                try {
                    $updater->upgrade($zip);
                    $updater->removeObsoleteFiles($obsolete_file, $params->get('root_project_dir'));
                    $userMapper->invalidateUserCache(true);

                    $page['infos'][] = $translator->trans('Update Complete', [], 'admin');
                    $page['infos'][] = $upgrade_to;
                    $step = -1;
                } catch (\Exception $e) {
                    $step = 0;
                    $message = $e->getMessage();
                    $message .= '<pre>';
                    $message .= implode("\n", $e->not_writable);
                    $message .= '</pre>';

                    $tpl_params['UPGRADE_ERROR'] = $message;
                }
            }
        }

        // +-----------------------------------------------------------------------+
        // |                                Step 3                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 3 && $userMapper->isWebmaster()) {
            if ($request->isMethod('POST') && $request->request->get('upgrade_to')) {
                $zip = __DIR__ . '/../' . $conf['data_location'] . 'update' . '/' . $request->request->get('upgrade_to') . '.zip';
                $updater->upgradeTo($request->request->get('upgrade_to'));
                $updater->download($zip);

                try {
                    $updater->upgrade($zip);

                    $upgrade = new Upgrade($em, $conf);
                    $upgrade->deactivateNonStandardPlugins();
                    $upgrade->deactivateNonStandardThemes();

                    $tables = $em->getConnection()->db_get_tables($em->getConnection()->getPrefix());
                    $columns_of = $em->getConnection()->db_get_columns_of($tables);

                    $result = $em->getRepository(UpgradeRepository::class)->findAll();
                    $applied_upgrades = $em->getConnection()->result2array($result, null, 'id');

                    if (!in_array(142, $applied_upgrades)) {
                        $current_release = '1.0.0';
                    } elseif (!in_array(144, $applied_upgrades)) {
                        $current_release = '1.1.0';
                    } elseif (!in_array(145, $applied_upgrades)) {
                        $current_release = '1.2.0';
                    } elseif (in_array('validated', $columns_of[$em->getConnection()->getPrefix() . 'tags'])) {
                        $current_release = '1.3.0';
                    } elseif (!in_array(146, $applied_upgrades)) {
                        $current_release = '1.5.0';
                    } elseif (!in_array(147, $applied_upgrades)) {
                        $current_release = '1.6.0';
                    } elseif (!is_dir(__DIR__ . '/../src/LegacyPages')) {
                        $current_release = '1.8.0';
                    } else {
                        $current_release = '1.9.0';
                    }

                    $upgrade_file = __DIR__ . '/../install/upgrade_' . $current_release . '.php';
                    if (is_readable($upgrade_file)) {
                        ob_start();
                        include($upgrade_file);
                        ob_end_clean();
                    }

                    $updater->removeObsoleteFiles($obsolete_file, __DIR__ . '/..');

                    $fs = new Filesystem();
                    $fs->remove(__DIR__ . '/../' . $conf['data_location'] . 'update');
                    $fs->remove(__DIR__ . '/../var/cache');

                    $userMapper->invalidateUserCache($full = true);

                    file_get_contents('./'); // @TODO cache warmup
                    \Phyxo\Functions\Utils::redirect('./?now=' . time());
                } catch (\Exception $e) {
                    $step = 0;
                    $message = $e->getMessage();
                    $message .= '<pre>';
                    $message .= implode("\n", $e->not_writable);
                    $message .= '</pre>';

                    $tpl_params['UPGRADE_ERROR'] = $message;
                }
            }
        }

        // +-----------------------------------------------------------------------+
        // |                        Process template                               |
        // +-----------------------------------------------------------------------+

        if (!$userMapper->isWebmaster()) {
            $tpl_params['errors'][] = $translator->trans('Webmaster status is required.', [], 'admin');
        }

        $tpl_params['STEP'] = $step;
        $tpl_params['CORE_VERSION'] = $params->get('core_version');
        $tpl_params['UPGRADE_TO'] = $upgrade_to;
        $tpl_params['RELEASE_URL'] = $params->get('phyxo_website') . '/releases/' . $upgrade_to;

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_update');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_update');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Updates', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('core'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('updates_core.html.twig', $tpl_params);
    }

    public function extensions(Request $request, Conf $conf, EntityManager $em, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_update');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_update_extensions');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Updates', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('extensions'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('updates_ext.html.twig', $tpl_params);
    }
}
