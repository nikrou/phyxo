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

if (!defined("UPDATES_BASE_URL")) {
    die("Hacking attempt!");
}

use Phyxo\Update\Updates;

/*
STEP:
0 = check is needed. If version is latest or check fail, we stay on step 0
1 = new version on same branch AND new branch are available => user may choose upgrade.
2 = upgrade on same branch
3 = upgrade on different branch
 */
$step = isset($_GET['step']) ? $_GET['step'] : 0;
$upgrade_to = isset($_GET['to']) ? $_GET['to'] : '';

$obsolete_file = __DIR__ . '/../install/obsolete.list';

// +-----------------------------------------------------------------------+
// |                                Step 0                                 |
// +-----------------------------------------------------------------------+
if ($step == 0) {
    $template->assign([
        'CHECK_VERSION' => false,
        'DEV_VERSION' => false,
    ]);

    $updater = new Updates($conn);
    $updater->setUpdateUrl(PHYXO_UPDATE_URL);

    if (preg_match('/.*-dev$/', PHPWG_VERSION, $matches)) {
        $template->assign('DEV_VERSION', true);
    } elseif (preg_match('/(\d+\.\d+)\.(\d+)/', PHPWG_VERSION, $matches)) {
        try {
            $all_versions = $updater->getAllVersions();
            $template->assign('CHECK_VERSION', true);
            $last_version = trim($all_versions[0]['version']);
            $upgrade_to = $last_version;

            if (version_compare(PHPWG_VERSION, $last_version, '<')) {
                $new_branch = preg_replace('/(\d+\.\d+)\.\d+/', '$1', $last_version);
                $actual_branch = $matches[1];

                if ($new_branch == $actual_branch) {
                    $step = 2;
                } else {
                    $step = 3;

                    // Check if new version exists in same branch
                    foreach ($all_versions as $version) {
                        $new_branch = preg_replace('/(\d+\.\d+)\.\d+/', '$1', $version);

                        if ($new_branch == $actual_branch) {
                            if (version_compare(PHPWG_VERSION, $version, '<')) {
                                $step = 1;
                            }
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $template->assign('LAST_ERROR_MESSAGE', $e->getMessage());
        }
    }
}

// +-----------------------------------------------------------------------+
// |                                Step 1                                 |
// +-----------------------------------------------------------------------+
if ($step == 1) {
    $template->assign([
        'MINOR_VERSION' => $version,
        'MAJOR_VERSION' => $last_version,
    ]);
}

// +-----------------------------------------------------------------------+
// |                                Step 2                                 |
// +-----------------------------------------------------------------------+
if ($step == 2 && $userMapper->isWebmaster()) {
    if (isset($_POST['submit']) and isset($_POST['upgrade_to'])) {
        $zip = __DIR__ . '/../' . $conf['data_location'] . 'update' . '/' . $_POST['upgrade_to'] . '.zip';
        $updater->upgradeTo($_POST['upgrade_to']);
        $updater->download($zip);
        $updater->removeObsoleteFiles($obsolete_file, __DIR__ . '/..');

        try {
            $updater->upgrade($zip);

            \Phyxo\Functions\Utils::deltree(__DIR__ . '/../' . $conf['data_location'] . 'update');
            $userMapper->invalidateUserCache(true);
            $template->delete_compiled_templates();
            $page['infos'][] = \Phyxo\Functions\Language::l10n('Update Complete');
            $page['infos'][] = $upgrade_to;
            $step = -1;
        } catch (Exception $e) {
            $step = 0;
            $message = $e->getMessage();
            $message .= '<pre>';
            $message .= implode("\n", $e->not_writable);
            $message .= '</pre>';

            $template->assign(['UPGRADE_ERROR' => $message]);
        }
    }
}

// +-----------------------------------------------------------------------+
// |                                Step 3                                 |
// +-----------------------------------------------------------------------+
if ($step == 3 && $userMapper->isWebmaster()) {
    if (isset($_POST['submit']) and isset($_POST['upgrade_to'])) {
        $zip = __DIR__ . '/../' . $conf['data_location'] . 'update' . '/' . $_POST['upgrade_to'] . '.zip';
        $updater->upgradeTo($_POST['upgrade_to']);
        $updater->download($zip);
        $updater->removeObsoleteFiles($obsolete_file, __DIR__ . '/..');

        try {
            $updater->upgrade($zip);

            \Phyxo\Functions\Utils::deltree(__DIR__ . '/../' . $conf['data_location'] . 'update');
            $userMapper->invalidateUserCache(true);
            $template->delete_compiled_templates();
            \Phyxo\Functions\Utils::redirect(__DIR__ . '/../' . 'upgrade.php?now='); // @TODO: use symfony router
        } catch (Exception $e) {
            $step = 0;
            $message = $e->getMessage();
            $message .= '<pre>';
            $message .= implode("\n", $e->not_writable);
            $message .= '</pre>';

            $template->assign(['UPGRADE_ERROR' => $message]);
        }
    }
}

// +-----------------------------------------------------------------------+
// |                        Process template                               |
// +-----------------------------------------------------------------------+

if (!$userMapper->isWebmaster()) {
    $page['errors'][] = \Phyxo\Functions\Language::l10n('Webmaster status is required.');
}

$template->assign([
    'STEP' => $step,
    'PHPWG_VERSION' => PHPWG_VERSION,
    'UPGRADE_TO' => $upgrade_to,
    'RELEASE_URL' => PHPWG_URL . '/releases/' . $upgrade_to,
]);
