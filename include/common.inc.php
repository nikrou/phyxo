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

defined('PHPWG_ROOT_PATH') or trigger_error('Hacking attempt!', E_USER_ERROR);

require_once(PHPWG_ROOT_PATH . 'vendor/autoload.php');

use Phyxo\DBLayer\DBLayer;
use Phyxo\Template\Template;
use Phyxo\Session\SessionDbHandler;
use Phyxo\Cache\PersistentFileCache;

// container
if (!empty($_SERVER['CONTAINER'])) {
    $container = $_SERVER['CONTAINER'];
}

// determine the initial instant to indicate the generation time of this page
$t2 = microtime(true);

// Define some basic configuration arrays this also prevents malicious
// rewriting of language and otherarray values via URI params
//
$conf = array();
$debug = '';
$page = array(
    'infos' => array(),
    'errors' => array(),
    'warnings' => array(),
    'count_queries' => 0,
    'queries_time' => 0,
);
$user = array();
$lang = array();
$lang_info = array();
$header_msgs = array();
$header_notes = array();
$filter = array();

include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
}

defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

if (is_readable(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/database.inc.php')) {
    include(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/database.inc.php');
}
if (!defined('PHPWG_INSTALLED')) {
    header('Location: ' . \Phyxo\Functions\URL::get_root_url() . 'admin/install');
    exit();
}

if (!empty($conf['show_php_errors'])) {
    @ini_set('error_reporting', $conf['show_php_errors']);
    @ini_set('display_errors', true);
}

include(PHPWG_ROOT_PATH . 'include/constants.php');

$persistent_cache = new PersistentFileCache();

// Database connection
if (defined('IN_WS')) {
    try {
        $conn = DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
    } catch (Exception $e) {
        $page['error'][] = \Phyxo\Functions\Language::l10n($e->getMessage());
    }
} else {
    $conn = $container->get('phyxo.conn');
}

// services
include(PHPWG_ROOT_PATH . 'include/services.php');

\Phyxo\Functions\Conf::load_conf_from_db();

if ($services['users']->isAdmin() && $conf['check_upgrade_feed']) {
    if (empty($conf['phyxo_db_version']) or $conf['phyxo_db_version'] != \Phyxo\Functions\Utils::get_branch_from_version(PHPWG_VERSION)) {
        \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url() . 'upgrade.php');
    }
}

\Phyxo\Image\ImageStdParams::load_from_db();

if (isset($conf['session_save_handler']) && ($conf['session_save_handler'] == 'db') && defined('PHPWG_INSTALLED')) {
    session_set_save_handler(new SessionDbHandler($conn), true);
}

if (function_exists('ini_set')) {
    ini_set('session.use_cookies', $conf['session_use_cookies']);
    ini_set('session.use_only_cookies', $conf['session_use_only_cookies']);
    ini_set('session.use_trans_sid', intval($conf['session_use_trans_sid']));
    ini_set('session.cookie_httponly', 1);
}

session_set_cookie_params(0, \Phyxo\Functions\Utils::cookie_path());
register_shutdown_function('session_write_close');
session_name($conf['session_name']);
session_start();
\Phyxo\Functions\Plugin::load_plugins();

// users can have defined a custom order pattern, incompatible with GUI form
if (isset($conf['order_by_custom'])) {
    $conf['order_by'] = $conf['order_by_custom'];
}
if (isset($conf['order_by_inside_category_custom'])) {
    $conf['order_by_inside_category'] = $conf['order_by_inside_category_custom'];
}

include(PHPWG_ROOT_PATH . 'include/user.inc.php');

// language files
\Phyxo\Functions\Language::load_language('common.lang');

if ($services['users']->isAdmin() || (defined('IN_ADMIN') && IN_ADMIN)) {
    \Phyxo\Functions\Language::load_language('admin.lang');
}
\Phyxo\Functions\Plugin::trigger_notify('loading_lang');
\Phyxo\Functions\Language::load_language('lang', PHPWG_ROOT_PATH . PWG_LOCAL_DIR, array('no_fallback' => true, 'local' => true));

// only now we can set the localized username of the guest user (and not in include/user.inc.php)
if ($services['users']->isGuest()) {
    $user['username'] = \Phyxo\Functions\Language::l10n('guest');
}

if (!defined('IN_WS') || !IN_WS) {
    if (defined('IN_ADMIN') && IN_ADMIN) { // Admin template
        $template = new Template(['conf' => $conf, 'lang' => $lang, 'lang_info' => $lang_info]);
        $template->set_theme(PHPWG_ROOT_PATH . 'admin/theme', '.');
    } else {
        $template = new Template(['conf' => $conf, 'lang' => $lang, 'lang_info' => $lang_info]);
        $theme = $user['theme'];
        $template->set_theme(PHPWG_ROOT_PATH . 'themes', $theme);
    }
    $compile_dir = $container->getParameter('kernel.cache_dir') . '/smarty';
    $template->setCompileDir($compile_dir);
}

if (!isset($conf['no_photo_yet']) || !$conf['no_photo_yet']) {
    include(PHPWG_ROOT_PATH . 'include/no_photo_yet.inc.php');
}

if (isset($user['internal_status']['guest_must_be_guest']) && $user['internal_status']['guest_must_be_guest'] === true) {
    $header_msgs[] = \Phyxo\Functions\Language::l10n('Bad status for user "guest", using default status. Please notify the webmaster.');
}

if ($conf['gallery_locked']) {
    $header_msgs[] = \Phyxo\Functions\Language::l10n('The gallery is locked for maintenance. Please, come back later.');

    if (\Phyxo\Functions\Utils::script_basename() != 'identification' && !$services['users']->isAdmin()) {
        \Phyxo\Functions\HTTP::set_status_header(503, 'Service Unavailable');
        @header('Retry-After: 900');
        header('Content-Type: text/html; charset=' . \Phyxo\Functions\Utils::get_charset());
        echo '<a href="' . \Phyxo\Functions\URL::get_absolute_root_url(false) . 'identification.php">' . \Phyxo\Functions\Language::l10n('The gallery is locked for maintenance. Please, come back later.') . '</a>';
        echo str_repeat(' ', 512); //IE6 doesn't error output if below a size
        exit();
    }
}

if ($conf['check_upgrade_feed']) {
    if (\Phyxo\Functions\Upgrade::check_upgrade_feed()) {
        $header_msgs[] = 'Some database upgrades are missing, <a class="alert-link" href="' . \Phyxo\Functions\URL::get_absolute_root_url(false) . 'upgrade_feed.php">upgrade now</a>';
    }
}

if (count($header_msgs) > 0) {
    $template->assign('header_msgs', $header_msgs);
    $header_msgs = array();
}

if (!empty($conf['filter_pages']) and \Phyxo\Functions\Utils::get_filter_page_value('used')) {
    // $filter['enabled']: Filter is enabled
    // $filter['recent_period']: Recent period used to computed filter data
    // $filter['categories']: Computed data of filtered categories
    // $filter['visible_categories']:
    //  List of visible categories (count(visible) < count(forbidden) more often)
    // $filter['visible_images']: List of visible images
    if (!\Phyxo\Functions\Utils::get_filter_page_value('cancel')) {
        if (isset($_GET['filter'])) {
            $filter['matches'] = array();
            $filter['enabled'] = preg_match('/^start-recent-(\d+)$/', $_GET['filter'], $filter['matches']) === 1;
        } else {
            $filter['enabled'] = isset($_SESSION['filter_enabled']) ? $_SESSION['filter_enabled'] : false;
        }
    } else {
        $filter['enabled'] = false;
    }

    if ($filter['enabled']) {
        $filter_key = isset($_SESSION['filter_check_key']) ? $_SESSION['filter_check_key'] : array('user' => 0, 'recent_period' => -1, 'time' => 0, 'date' => '');

        if (isset($filter['matches'])) {
            $filter['recent_period'] = $filter['matches'][1];
        } else {
            $filter['recent_period'] = $filter_key['recent_period'] > 0 ? $filter_key['recent_period'] : $user['recent_period'];
        }

        // New filter or Cache data updated
        // Date, period, user are changed
        if (empty($_SESSION['filter_enabled']) || $filter_key['time'] <= $user['cache_update_time']
            || $filter_key['user'] != $user['id'] || $filter_key['recent_period'] != $filter['recent_period']
            || $filter_key['date'] != date('Ymd')) {
            // Need to compute dats
            $filter_key = array(
                'user' => (int)$user['id'],
                'recent_period' => (int)$filter['recent_period'],
                'time' => time(),
                'date' => date('Ymd')
            );

            $filter['categories'] = \Phyxo\Functions\Category::get_computed_categories($user, (int)$filter['recent_period']);

            $filter['visible_categories'] = implode(',', array_keys($filter['categories']));
            if (empty($filter['visible_categories'])) {
                // Must be not empty
                $filter['visible_categories'] = -1;
            }

            $query = 'SELECT distinct image_id FROM ' . IMAGES_TABLE;
            $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON image_id = id';
            $query .= ' WHERE ';

            if (!empty($filter['visible_categories'])) {
                $query .= ' category_id  ' . $conn->in($filter['visible_categories']) . ' AND ';
            }

            $query .= ' date_available >= ' . $conn->db_get_recent_period_expression($filter['recent_period']);

            $filter['visible_images'] = implode(',', $conn->query2array($query, null, 'image_id'));

            if (empty($filter['visible_images'])) {
                // Must be not empty
                $filter['visible_images'] = -1;
            }

            // Save filter data on session
            $_SESSION['filter_enabled'] = $filter['enabled'];
            $_SESSION['filter_check_key'] = $filter_key;
            $_SESSION['filter_categories'] = $filter['categories'];
            $_SESSION['filter_visible_categories'] = $filter['visible_categories'];
            $_SESSION['filter_visible_images'] = $filter['visible_images'];
        } else {
            // Read only data
            $filter['categories'] = isset($_SESSION['filter_categories']) ? $_SESSION['filter_categories'] : array();
            $filter['visible_categories'] = isset($_SESSION['filter_visible_categories']) ? $_SESSION['filter_visible_categories'] : '';
            $filter['visible_images'] = isset($_SESSION['filter_visible_images']) ? $_SESSION['filter_visible_images'] : '';
        }
        unset($filter_key);
        if (\Phyxo\Functions\Utils::get_filter_page_value('add_notes')) {
            $header_notes[] = \Phyxo\Functions\Language::l10n_dec(
                'Photos posted within the last %d day.',
                'Photos posted within the last %d days.',
                $filter['recent_period']
            );
        }
    } else {
        if (!empty($_SESSION['filter_enabled'])) {
            unset($_SESSION['filter_enabled'], $_SESSION['filter_check_key'], $_SESSION['filter_categories'],
                $_SESSION['filter_visible_categories'], $_SESSION['filter_visible_images']);
        }
    }
} else {
    $filter['enabled'] = false;
}

if (isset($conf['header_notes'])) {
    $header_notes = array_merge($header_notes, $conf['header_notes']);
}

// default event handlers
\Phyxo\Functions\Plugin::add_event_handler('render_category_literal_description', '\Phyxo\Functions\Category::render_category_literal_description');
if (!$conf['allow_html_descriptions']) {
    \Phyxo\Functions\Plugin::add_event_handler('render_category_description', 'nl2br');
}
\Phyxo\Functions\Plugin::add_event_handler('render_comment_content', '\Phyxo\Functions\Utils::render_comment_content');
\Phyxo\Functions\Plugin::add_event_handler('render_comment_author', 'strip_tags');
\Phyxo\Functions\Plugin::add_event_handler('render_tag_url', '\Phyxo\Functions\Language::str2url');
\Phyxo\Functions\Plugin::add_event_handler(
    'blockmanager_register_blocks',
    '\Phyxo\Functions\Utils::register_default_menubar_blocks',
    \Phyxo\Functions\Plugin::EVENT_HANDLER_PRIORITY_NEUTRAL - 1
);

if (!empty($conf['original_url_protection'])) {
    \Phyxo\Functions\Plugin::add_event_handler('get_element_url', '\Phyxo\Functions\URL::get_element_url_protection_handler');
    \Phyxo\Functions\Plugin::add_event_handler('get_src_image_url', '\Phyxo\Functions\URL::get_src_image_url_protection_handler');
}
\Phyxo\Functions\Plugin::trigger_notify('init');
