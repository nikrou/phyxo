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

use Phyxo\Language\Languages;
use App\Repository\UpgradeRepository;
use App\Repository\BaseRepository;

define('UPGRADE_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=upgrade');
define('PREFIX_TABLE', $prefixeTable);

$template_filename = 'upgrade';

if (preg_match('/.*-dev$/', PHPWG_VERSION, $matches)) {
    $template->assign('DEV_VERSION', true);
    return;
}

// +-----------------------------------------------------------------------+
// |                             language                                  |
// +-----------------------------------------------------------------------+
$languages = new Languages($conn, $userMapper);
$languages->setLanguagesRootPath(__DIR__ . '/../language');

if (isset($_GET['language'])) {
    if (!in_array($_GET['language'], array_keys($languages->getFsLanguages()))) {
        $language = PHPWG_DEFAULT_LANGUAGE;
    } else {
        $language = $_GET['language'];
    }
} else {
    $language = 'en_GB';
    // Try to get browser language
    foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
        if (substr($language_code, 0, 2) == @substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2)) {
            $language = $language_code;
            break;
        }
    }
}

\Phyxo\Functions\Language::load_language('common.lang', '', ['language' => $language]);
\Phyxo\Functions\Language::load_language('admin.lang', '', ['language' => $language]);
\Phyxo\Functions\Language::load_language('install.lang', '', ['language' => $language]);
\Phyxo\Functions\Language::load_language('upgrade.lang', '', ['language' => $language]);

define('CURRENT_DATE', (new BaseRepository($conn))->getNow());

$template->set_filenames(['upgrade' => 'upgrade.tpl']);
$template->assign(['RELEASE' => PHPWG_VERSION]);

// +-----------------------------------------------------------------------+
// |                            upgrade choice                             |
// +-----------------------------------------------------------------------+

$tables = $conn->db_get_tables(PREFIX_TABLE);
$columns_of = $conn->db_get_columns_of($tables);

// find the current release
$result = (new UpgradeRepository($conn))->findAll();
$applied_upgrades = $conn->result2array($result, null, 'id');

if (!in_array(142, $applied_upgrades)) {
    $current_release = '1.0.0';
} elseif (!in_array(144, $applied_upgrades)) {
    $current_release = '1.1.0';
} elseif (!in_array(145, $applied_upgrades)) {
    $current_release = '1.2.0';
} elseif (in_array('validated', $columns_of[PREFIX_TABLE . 'tags'])) {
    $current_release = '1.3.0';
} elseif (!in_array(146, $applied_upgrades)) {
    $current_release = '1.5.0';
} elseif (!in_array(147, $applied_upgrades)) {
    $current_release = '1.6.0';
} elseif (!in_array(148, $applied_upgrades)) {
    $current_release = '1.8.0';
}

// +-----------------------------------------------------------------------+
// |                            upgrade launch                             |
// +-----------------------------------------------------------------------+
$page['infos'] = [];
$page['errors'] = [];
$mysql_changes = [];

if (isset($_POST['submit']) && $upgrade->checkUpgrade()) {
    $upgrade_file = __DIR__ . '/../install/upgrade_' . $current_release . '.php';
    if (is_file($upgrade_file)) {
        $page['upgrade_start'] = microtime(true);
        $conf['die_on_sql_error'] = false;
        include($upgrade_file);
        $conf['phyxo_db_version'] = \Phyxo\Functions\Utils::get_branch_from_version(PHPWG_VERSION);

        // Plugins deactivation
        if (in_array(PREFIX_TABLE . 'plugins', $tables)) {
            $deactivate_plugins = $upgrade->deactivateNonStandardPlugins();
            if (count($deactivate_plugins) > 0) {
                $page['infos'][] = Language::l10n('As a precaution, following plugins have been deactivated. You must check for plugins upgrade before reactiving them:') .
                 '<p><i>' . implode(', ', $plugins) . '</i></p>';
            }
        }

        $deactivate_themes = $upgrade->deactivateNonStandardThemes();
        if (count($deactivate_themes) > 0) {
            $page['infos'][] = \Phyxo\Functions\Language::l10n('As a precaution, following themes have been deactivated. You must check for themes upgrade before reactiving them:') .
            '<p><i>' . implode(', ', $deactivate_themes) . '</i></p>';
        }

        $page['upgrade_end'] = microtime(true);

        $template->assign(
            'upgrade',
            [
                'VERSION' => $current_release,
                'TOTAL_TIME' => \Phyxo\Functions\Utils::get_elapsed_time(
                    $page['upgrade_start'],
                    $page['upgrade_end']
                ),
                'SQL_TIME' => number_format(
                    $page['queries_time'],
                    3,
                    '.',
                    ' '
                ) . ' s',
                'NB_QUERIES' => $page['count_queries']
            ]
        );

        $page['infos'][] = \Phyxo\Functions\Language::l10n('Perform a maintenance check in [Administration>Tools>Maintenance] if you encounter any problem.');

        // Save $page['infos'] in order to restore after maintenance actions
        $page['infos_sav'] = $page['infos'];
        $page['infos'] = [];

        // Delete cache data
        $userMapper->invalidateUserCache(true);
        $template->delete_compiled_templates();

        // Tables Maintenance
        $conn->do_maintenance_all_tables();

        // Restore $page['infos'] in order to hide informations messages from functions calles
        // errors messages are not hide
        $page['infos'] = $page['infos_sav'];
    }
} else {
    // +-----------------------------------------------------------------------+
    // |                          start template output                        |
    // +-----------------------------------------------------------------------+
    if (!defined('PWG_CHARSET')) {
        define('PWG_CHARSET', 'utf-8');
    }

    $languages = new Languages($conn, $userMapper);
    $languages->setLanguagesRootPath(__DIR__ . '/../language');

    foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
        if ($language == $language_code) {
            $template->assign('language_selection', $language_code);
        }
        $languages_options[$language_code] = $fs_language['name'];
    }
    $template->assign('language_options', $languages_options);

    $template->assign('introduction', [
        'CURRENT_RELEASE' => $current_release,
        'F_ACTION' => UPGRADE_BASE_URL,
        'LANGUAGE' => $language
    ]);
}

if (count($page['errors']) != 0) {
    $template->assign('errors', $page['errors']);
}

if (count($page['infos']) != 0) {
    $template->assign('infos', $page['infos']);
}
