<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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

define('PHPWG_ROOT_PATH', './');

// load config file
include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
@include(PHPWG_ROOT_PATH. 'local/config/config.inc.php');
defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

$config_file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR.'config/database.inc.php';
$config_file_contents = @file_get_contents($config_file);
if ($config_file_contents === false) {
    die('Cannot load '.$config_file);
}

include($config_file);

// $conf is not used for users tables - define cannot be re-defined
define('USERS_TABLE', $prefixeTable.'users');
include_once(PHPWG_ROOT_PATH.'include/constants.php');
define('PREFIX_TABLE', $prefixeTable);
define('UPGRADES_PATH', PHPWG_ROOT_PATH.'install/db');

include_once(PHPWG_ROOT_PATH.'include/functions.inc.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// |                              functions                                |
// +-----------------------------------------------------------------------+

/**
 * list all tables in an array
 *
 * @return array
 */
function get_tables() {
    return pwg_db_get_tables(PREFIX_TABLE);
}

/**
 * list all columns of each given table
 *
 * @return array of array
 */
function get_columns_of($tables) {
    return pwg_db_get_columns_of($tables);
}

/**
 */
function print_time($message) {
    global $last_time;

    $new_time = get_moment();
    echo '<pre>['.get_elapsed_time($last_time, $new_time).']';
    echo ' '.$message;
    echo '</pre>';
    flush();
    $last_time = $new_time;
}

// +-----------------------------------------------------------------------+
// |                             playing zone                              |
// +-----------------------------------------------------------------------+

// echo implode('<br>', get_tables());
// echo '<pre>'; print_r(get_columns_of(get_tables())); echo '</pre>';

// foreach (get_available_upgrade_ids() as $upgrade_id)
// {
//   echo $upgrade_id, '<br>';
// }

// +-----------------------------------------------------------------------+
// |                             language                                  |
// +-----------------------------------------------------------------------+
include(PHPWG_ROOT_PATH . 'admin/include/languages.class.php');
$languages = new languages('utf-8');

if (isset($_GET['language'])) {
    $language = strip_tags($_GET['language']);

    if (!in_array($language, array_keys($languages->fs_languages))) {
        $language = PHPWG_DEFAULT_LANGUAGE;
    }
} else {
  $language = 'en_UK';
  // Try to get browser language
  foreach ($languages->fs_languages as $language_code => $fs_language) {
      if (substr($language_code,0,2) == @substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,2)) {
          $language = $language_code;
          break;
      }
  }
}

define('PHPWG_URL', 'http://phyxo.nikrou.net');

// +-----------------------------------------------------------------------+
// |                          database connection                          |
// +-----------------------------------------------------------------------+
include_once(PHPWG_ROOT_PATH.'admin/include/functions_upgrade.php');
include(PHPWG_ROOT_PATH .'include/dblayer/functions_'.$conf['dblayer'].'.inc.php');

try {
    pwg_db_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
} catch (Exception $e) {
    my_error(l10n($e->getMessage()), true);
}

load_language('common.lang', '', array('language' => $language, 'target_charset'=>'utf-8', 'no_fallback' => true) );
load_language('admin.lang', '', array('language' => $language, 'target_charset'=>'utf-8', 'no_fallback' => true) );
load_language('install.lang', '', array('language' => $language, 'target_charset'=>'utf-8', 'no_fallback' => true) );
load_language('upgrade.lang', '', array('language' => $language, 'target_charset'=>'utf-8', 'no_fallback' => true) );

// check php version
if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
    include(PHPWG_ROOT_PATH.'install/php5_apache_configuration.php');
}

upgrade_db_connect();
pwg_db_check_charset();

list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
define('CURRENT_DATE', $dbnow);

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+

$template = new Template(PHPWG_ROOT_PATH.'admin/themes', 'clear');
$template->set_filenames(array('upgrade' => 'upgrade.tpl'));
$template->assign(
    array(
        'RELEASE' => PHPWG_VERSION,
        'L_UPGRADE_HELP' => l10n('Need help ? Ask your question on <a href="%s">Piwigo message board</a>.', PHPWG_URL.'/forum'),
    )
);

// +-----------------------------------------------------------------------+
// | Remote sites are not compatible with Piwigo 2.4+                      |
// +-----------------------------------------------------------------------+

$has_remote_site = false;

$query = 'SELECT galleries_url FROM '.SITES_TABLE.';';
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    if (url_is_remote($row['galleries_url'])) {
        $has_remote_site = true;
    }
}

if ($has_remote_site) {
    include_once(PHPWG_ROOT_PATH.'admin/include/updates.class.php');
    include_once(PHPWG_ROOT_PATH.'admin/include/pclzip.lib.php');
    
    $page['errors'] = array();
    $step = 3;
    updates::upgrade_to('2.3.4', $step, false);

    if (!empty($page['errors'])) {
        echo '<ul>';
        foreach ($page['errors'] as $error) {
            echo '<li>'.$error.'</li>';
        }
        echo '</ul>';
    }

    exit();
}

// +-----------------------------------------------------------------------+
// |                            upgrade choice                             |
// +-----------------------------------------------------------------------+

$tables = get_tables();
$columns_of = get_columns_of($tables);

// find the current release
$query = 'SELECT id FROM '.PREFIX_TABLE.'upgrade;';
$applied_upgrades = array_from_query($query, 'id');
    
if (!in_array(142, $applied_upgrades)) {
    $current_release = '1.0.0';
} else {
    // confirm that the database is in the same version as source code files
    conf_update_param('piwigo_db_version', get_branch_from_version(PHPWG_VERSION));
    
    header('Content-Type: text/html; charset='.get_pwg_charset());
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
    $upgrade_file = PHPWG_ROOT_PATH.'install/upgrade_'.$current_release.'.php';
    if (is_file($upgrade_file)) {
        $page['upgrade_start'] = get_moment();
        $conf['die_on_sql_error'] = false;
        include($upgrade_file);
        conf_update_param('piwigo_db_version', get_branch_from_version(PHPWG_VERSION));

        // Plugins deactivation
        if (in_array(PREFIX_TABLE.'plugins', $tables)) {
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
                ).' s',
                'NB_QUERIES' => $page['count_queries']
            )
        );
        
        $page['infos'][] = l10n('Perform a maintenance check in [Administration>Tools>Maintenance] if you encounter any problem.');
        
        // Save $page['infos'] in order to restore after maintenance actions
        $page['infos_sav'] = $page['infos'];
        $page['infos'] = array();

        /* might be usefull when we will have a real integrity checker
           $query = '
           REPLACE INTO '.PLUGINS_TABLE.'
           (id, state)
           VALUES (\'c13y_upgrade\', \'active\')
           ;';
           pwg_query($query);*/

        // Delete cache data
        invalidate_user_cache(true);
        $template->delete_compiled_templates();
        
        // Tables Maintenance
        do_maintenance_all_tables();
        
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

    include_once(PHPWG_ROOT_PATH.'admin/include/languages.class.php');
    $languages = new languages();
    
    foreach ($languages->fs_languages as $language_code => $fs_language) {
        if ($language == $language_code) {
            $template->assign('language_selection', $language_code);
        }
        $languages_options[$language_code] = $fs_language['name'];
    }
    $template->assign('language_options', $languages_options);
    
    $template->assign('introduction', array(
        'CURRENT_RELEASE' => $current_release,
        'F_ACTION' => 'upgrade.php?language=' . $language)
    );
    
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
