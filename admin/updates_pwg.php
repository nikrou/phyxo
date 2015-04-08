<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined("PHPWG_ROOT_PATH")) {
    die ("Hacking attempt!");
}

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

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

// +-----------------------------------------------------------------------+
// |                                Step 0                                 |
// +-----------------------------------------------------------------------+
if ($step == 0) {
    $template->assign(array(
        'CHECK_VERSION' => false,
        'DEV_VERSION' => false,
    ));

    $updater = new Updates(PHYXO_UPDATE_URL);

    if (preg_match('/(\d+\.\d+)\.(\d+)/', PHPWG_VERSION, $matches)) {
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
    } else {
        $template->assign('DEV_VERSION', true);
    }
}

// +-----------------------------------------------------------------------+
// |                                Step 1                                 |
// +-----------------------------------------------------------------------+
if ($step == 1) {
    $template->assign(array(
        'MINOR_VERSION' => $version,
        'MAJOR_VERSION' => $last_version,
    ));
}

// +-----------------------------------------------------------------------+
// |                                Step 2                                 |
// +-----------------------------------------------------------------------+
if ($step == 2 and $services['users']->isWebmaster()) {
    if (isset($_POST['submit']) and isset($_POST['upgrade_to'])) {
        $zip = PHPWG_ROOT_PATH.$conf['data_location'].'update'.'/'.$_POST['upgrade_to'].'.zip';
        $updater->upgradeTo($_POST['upgrade_to']);
        $updater->download($zip);

        try {
            $updater->upgrade($zip);

            deltree(PHPWG_ROOT_PATH.$conf['data_location'].'update');
            invalidate_user_cache(true);
            $template->delete_compiled_templates();
            $page['infos'][] = l10n('Update Complete');
            $page['infos'][] = $upgrade_to;
            $step = -1;
        } catch (Exception $e) {
            $step = 0;
            $message = $e->getMessage();
            $message .= '<pre>';
            $message .= implode("\n", $e->not_writable);
            $message .= '</pre>';

            $template->assign(array('UPGRADE_ERROR' => $message));
        }
    }
}

// +-----------------------------------------------------------------------+
// |                                Step 3                                 |
// +-----------------------------------------------------------------------+
if ($step == 3 and $services['users']->isWebmaster()) {
    if (isset($_POST['submit']) and isset($_POST['upgrade_to'])) {
        $zip = PHPWG_ROOT_PATH.$conf['data_location'].'update'.'/'.$_POST['upgrade_to'].'.zip';
        $updater->upgradeTo($_POST['upgrade_to']);
        $updater->download($zip);

        try {
            $updater->upgrade($zip);

            deltree(PHPWG_ROOT_PATH.$conf['data_location'].'update');
            invalidate_user_cache(true);
            $template->delete_compiled_templates();
            redirect(PHPWG_ROOT_PATH.'upgrade.php?now=');
        } catch (Exception $e) {
            $step = 0;
            $message = $e->getMessage();
            $message .= '<pre>';
            $message .= implode("\n", $e->not_writable);
            $message .= '</pre>';

            $template->assign(array('UPGRADE_ERROR' => $message));
        }
    }
}

// +-----------------------------------------------------------------------+
// |                        Process template                               |
// +-----------------------------------------------------------------------+

if (!$services['users']->isWebmaster()) {
    $page['errors'][] = l10n('Webmaster status is required.');
}

$template->assign(array(
    'STEP'          => $step,
    'PHPWG_VERSION' => PHPWG_VERSION,
    'UPGRADE_TO'    => $upgrade_to,
    'RELEASE_URL'   => PHPWG_URL.'/releases/'.$upgrade_to,
));

$template->set_filename('plugin_admin_content', 'updates_pwg.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
