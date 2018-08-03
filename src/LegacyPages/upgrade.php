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

define('PHPWG_ROOT_PATH', '../../');

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

use Phyxo\DBLayer\DBLayer;
use Phyxo\Language\Languages;
use Phyxo\Update\Updates;
use Phyxo\Template\Template;

// load config file
include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
}
defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

if (is_readable(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/database.inc.php')) {
    include(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/database.inc.php');
}

// $conf is not used for users tables - define cannot be re-defined
define('USERS_TABLE', $prefixeTable . 'users');
include_once(PHPWG_ROOT_PATH . 'include/constants.php');
define('PREFIX_TABLE', $prefixeTable);
define('UPGRADES_PATH', PHPWG_ROOT_PATH . 'install/db');

include_once(PHPWG_ROOT_PATH . 'include/functions.inc.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// |                          database connection                          |
// +-----------------------------------------------------------------------+
include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upgrade.php');

try {
    $conn = DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
} catch (Exception $e) {
    $page['errors'][] = l10n($e->getMessage());
}

$page['count_queries'] = 0;
$page['queries_time'] = 0;

// +-----------------------------------------------------------------------+
// |                             language                                  |
// +-----------------------------------------------------------------------+
$languages = new Languages($conn, 'utf-8');

if (isset($_GET['language'])) {
    if (!in_array($language, array_keys($languages->getFsLanguages()))) {
        $language = PHPWG_DEFAULT_LANGUAGE;
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

load_language('common.lang', '', array('language' => $language, 'target_charset' => 'utf-8', 'no_fallback' => true));
load_language('admin.lang', '', array('language' => $language, 'target_charset' => 'utf-8', 'no_fallback' => true));
load_language('install.lang', '', array('language' => $language, 'target_charset' => 'utf-8', 'no_fallback' => true));
load_language('upgrade.lang', '', array('language' => $language, 'target_charset' => 'utf-8', 'no_fallback' => true));

list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
define('CURRENT_DATE', $dbnow);

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+

$template = new Template(PHPWG_ROOT_PATH . 'admin/theme', '.');
$template->set_filenames(array('upgrade' => 'upgrade.tpl'));
$template->assign(array('RELEASE' => PHPWG_VERSION));

// +-----------------------------------------------------------------------+
// |                            upgrade choice                             |
// +-----------------------------------------------------------------------+

$tables = $conn->db_get_tables(PREFIX_TABLE);
$columns_of = $conn->db_get_columns_of($tables);

// find the current release
$query = 'SELECT id FROM ' . PREFIX_TABLE . 'upgrade;';
$applied_upgrades = $conn->query2array($query, null, 'id');

if (in_array('validated', $columns_of[PREFIX_TABLE . 'tags'])) {
    $current_release = '1.3.0';
} elseif (!in_array(145, $applied_upgrades)) {
    $current_release = '1.2.0';
} elseif (!in_array(142, $applied_upgrades)) {
    $current_release = '1.0.0';
} else {
    // confirm that the database is in the same version as source code files
    conf_update_param('phyxo_db_version', get_branch_from_version(PHPWG_VERSION));

    header('Content-Type: text/html; charset=' . get_pwg_charset());
    echo 'No upgrade required, the database structure is up to date';
    echo '<br><a href="index.php">← back to gallery</a>';
    exit();
}

// +-----------------------------------------------------------------------+
// |                            upgrade launch                             |
// +-----------------------------------------------------------------------+
$page['infos'] = array();
$page['errors'] = array();
$mysql_changes = array();

check_upgrade_access_rights();

if ((isset($_POST['submit']) or isset($_GET['now'])) and check_upgrade()) {
    $upgrade_file = PHPWG_ROOT_PATH . 'install/upgrade_' . $current_release . '.php';
    if (is_file($upgrade_file)) {
        $page['upgrade_start'] = get_moment();
        $conf['die_on_sql_error'] = false;
        include($upgrade_file);
        conf_update_param('phyxo_db_version', get_branch_from_version(PHPWG_VERSION));

        // Plugins deactivation
        if (in_array(PREFIX_TABLE . 'plugins', $tables)) {
            deactivate_non_standard_plugins();
        }

        deactivate_non_standard_themes();
        deactivate_templates();

        $page['upgrade_end'] = get_moment();

        $template->assign(
            'upgrade',
            array(
                'VERSION' => $current_release,
                'TOTAL_TIME' => get_elapsed_time(
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
            )
        );

        $page['infos'][] = l10n('Perform a maintenance check in [Administration>Tools>Maintenance] if you encounter any problem.');

        // Save $page['infos'] in order to restore after maintenance actions
        $page['infos_sav'] = $page['infos'];
        $page['infos'] = array();

        // Delete cache data
        invalidate_user_cache(true);
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

    $languages = new Languages($conn);

    foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
        if ($language == $language_code) {
            $template->assign('language_selection', $language_code);
        }
        $languages_options[$language_code] = $fs_language['name'];
    }
    $template->assign('language_options', $languages_options);

    $template->assign('introduction', array(
        'CURRENT_RELEASE' => $current_release,
        'F_ACTION' => 'upgrade.php?language=' . $language
    ));

    if (!check_upgrade()) {
        $template->assign('login', true);
    }
}

if (count($page['errors']) != 0) {
    $template->assign('errors', $page['errors']);
}

if (count($page['infos']) != 0) {
    $template->assign('infos', $page['infos']);
}

// +-----------------------------------------------------------------------+
// |                          sending html code                            |
// +-----------------------------------------------------------------------+

$template->pparse('upgrade');
