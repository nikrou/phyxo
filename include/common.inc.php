<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire         http://www.phyxo.net/ |
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

defined('PHPWG_ROOT_PATH') or trigger_error('Hacking attempt!', E_USER_ERROR);

require_once(PHPWG_ROOT_PATH.'vendor/autoload.php');

use Phyxo\DBLayer\DBLayer;
use Phyxo\Template\Template;
use Phyxo\Session\SessionDbHandler;

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
$header_msgs = array();
$header_notes = array();
$filter = array();

include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
if (is_readable(PHPWG_ROOT_PATH. 'local/config/config.inc.php')) {
    include(PHPWG_ROOT_PATH. 'local/config/config.inc.php');
}

defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

if (is_readable(PHPWG_ROOT_PATH.PWG_LOCAL_DIR .'config/database.inc.php')) {
    include(PHPWG_ROOT_PATH.PWG_LOCAL_DIR .'config/database.inc.php');
}
if (!defined('PHPWG_INSTALLED')) {
    header('Location: install.php');
    exit();
}

if (!empty($conf['show_php_errors'])) {
    @ini_set('error_reporting', $conf['show_php_errors']);
    @ini_set('display_errors', true);
}

include(PHPWG_ROOT_PATH . 'include/constants.php');
include(PHPWG_ROOT_PATH . 'include/functions.inc.php');

$persistent_cache = new PersistentFileCache();

// Database connection
try {
    $conn = DBLayer::init($conf['dblayer'], $conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
} catch (Exception $e) {
    $page['error'][] = l10n($e->getMessage());
}

// services
include(PHPWG_ROOT_PATH . 'include/services.php');

load_conf_from_db();

if ($services['users']->isAdmin() && $conf['check_upgrade_feed']) {
    if (empty($conf['phyxo_db_version']) or $conf['phyxo_db_version'] != get_branch_from_version(PHPWG_VERSION)) {
        redirect(get_root_url().'upgrade.php');
    }
}

ImageStdParams::load_from_db();

if (isset($conf['session_save_handler']) && ($conf['session_save_handler'] == 'db') && defined('PHPWG_INSTALLED')) {
    session_set_save_handler(new SessionDbHandler($conn), true);
}

if (function_exists('ini_set')) {
    ini_set('session.use_cookies', $conf['session_use_cookies']);
    ini_set('session.use_only_cookies', $conf['session_use_only_cookies']);
    ini_set('session.use_trans_sid', intval($conf['session_use_trans_sid']));
    ini_set('session.cookie_httponly', 1);
}

session_set_cookie_params(0, cookie_path());
register_shutdown_function('session_write_close');
session_name($conf['session_name']);
session_start();
load_plugins();

// users can have defined a custom order pattern, incompatible with GUI form
if (isset($conf['order_by_custom'])) {
    $conf['order_by'] = $conf['order_by_custom'];
}
if (isset($conf['order_by_inside_category_custom'])) {
    $conf['order_by_inside_category'] = $conf['order_by_inside_category_custom'];
}

include(PHPWG_ROOT_PATH.'include/user.inc.php');

// language files
load_language('common.lang');
if ($services['users']->isAdmin() || (defined('IN_ADMIN') && IN_ADMIN)) {
    load_language('admin.lang');
}
trigger_notify('loading_lang');
load_language('lang', PHPWG_ROOT_PATH.PWG_LOCAL_DIR, array('no_fallback' => true, 'local' => true));

// only now we can set the localized username of the guest user (and not in include/user.inc.php)
if ($services['users']->isGuest()) {
    $user['username'] = l10n('guest');
}

if (!defined('IN_WS') || !IN_WS) {
    // template instance
    if (defined('IN_ADMIN') && IN_ADMIN ) { // Admin template
        $template = new Template(PHPWG_ROOT_PATH.'admin/theme', '.');
    } else { // Classic template
        $theme = $user['theme'];
        if (script_basename() != 'ws' and mobile_theme()) {
            $theme = $conf['mobile_theme'];
        }
        $template = new Template(PHPWG_ROOT_PATH.'themes', $theme );
    }
}

if (!isset($conf['no_photo_yet']) || !$conf['no_photo_yet']) {
    include(PHPWG_ROOT_PATH.'include/no_photo_yet.inc.php');
}

if (isset($user['internal_status']['guest_must_be_guest']) && $user['internal_status']['guest_must_be_guest'] === true) {
    $header_msgs[] = l10n('Bad status for user "guest", using default status. Please notify the webmaster.');
}

if ($conf['gallery_locked']) {
    $header_msgs[] = l10n('The gallery is locked for maintenance. Please, come back later.');

    if (script_basename() != 'identification' && !$services['users']->isAdmin()) {
        set_status_header(503, 'Service Unavailable');
        @header('Retry-After: 900');
        header('Content-Type: text/html; charset='.get_pwg_charset());
        echo '<a href="'.get_absolute_root_url(false).'identification.php">'.l10n('The gallery is locked for maintenance. Please, come back later.').'</a>';
        echo str_repeat( ' ', 512); //IE6 doesn't error output if below a size
        exit();
    }
}

if ($conf['check_upgrade_feed']) {
    include_once(PHPWG_ROOT_PATH.'admin/include/functions_upgrade.php');
    if (check_upgrade_feed()) {
        $header_msgs[] = 'Some database upgrades are missing, <a href="'.get_absolute_root_url(false).'upgrade_feed.php">upgrade now</a>';
    }
}

if (count($header_msgs) > 0) {
    $template->assign('header_msgs', $header_msgs);
    $header_msgs = array();
}

if (!empty($conf['filter_pages']) and get_filter_page_value('used')) {
    include(PHPWG_ROOT_PATH.'include/filter.inc.php');
} else {
    $filter['enabled'] = false;
}

if (isset($conf['header_notes'])) {
    $header_notes = array_merge($header_notes, $conf['header_notes']);
}

// default event handlers
add_event_handler('render_category_literal_description', 'render_category_literal_description');
if (!$conf['allow_html_descriptions']) {
    add_event_handler('render_category_description', 'nl2br');
}
add_event_handler('render_comment_content', 'render_comment_content');
add_event_handler('render_comment_author', 'strip_tags');
add_event_handler('render_tag_url', 'str2url');
add_event_handler('blockmanager_register_blocks', 'register_default_menubar_blocks', EVENT_HANDLER_PRIORITY_NEUTRAL-1);

if (!empty($conf['original_url_protection'])) {
    add_event_handler('get_element_url', 'get_element_url_protection_handler');
    add_event_handler('get_src_image_url', 'get_src_image_url_protection_handler');
}
trigger_notify('init');
