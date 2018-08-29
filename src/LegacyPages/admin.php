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

// +-----------------------------------------------------------------------+
// | Basic constants and includes                                          |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', '../../');
define('IN_ADMIN', true);

include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_admin');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Direct actions                                                        |
// +-----------------------------------------------------------------------+

// save plugins_new display order (AJAX action)
if (isset($_GET['plugins_new_order'])) {
    $_SESSION['plugins_new_order'] = $_GET['plugins_new_order']; // @TODO: filter ??
    exit;
}

// +-----------------------------------------------------------------------+
// | Synchronize user informations                                         |
// +-----------------------------------------------------------------------+

// sync_user() is only useful when external authentication is activated
if ($conf['external_authentification']) {
    \Phyxo\Functions\Utils::sync_users();
}

// +-----------------------------------------------------------------------+
// | Variables init                                                        |
// +-----------------------------------------------------------------------+

$test_get = $_GET;
unset($test_get['page'], $test_get['section'], $test_get['tag']);

// ?page=plugin-community-pendings is an clean alias of
// ?page=plugin&section=community/admin.php&tab=pendings
if (isset($_GET['page']) and preg_match('/^plugin-([^-]*)(?:-(.*))?$/', $_GET['page'], $matches)) {
    $_GET['page'] = 'plugin';
    $_GET['section'] = $matches[1] . '/admin.php';
    if (isset($matches[2])) {
        $_GET['tab'] = $matches[2];
    }
}

// ?page=album-134-properties is an clean alias of
// ?page=album&cat_id=134&tab=properties
if (isset($_GET['page']) and preg_match('/^album-(\d+)(?:-(.*))?$/', $_GET['page'], $matches)) {
    $_GET['page'] = 'album';
    $_GET['cat_id'] = $matches[1];
    if (isset($matches[2])) {
        $_GET['tab'] = $matches[2];
    }
}

// ?page=photo-1234-properties is an clean alias of
// ?page=photo&image_id=1234&tab=properties
// if (isset($_GET['page']) and preg_match('/^photo-(\d+)(?:-(.*))?$/', $_GET['page'], $matches)) {
//     $_GET['page'] = 'photo';
//     $_GET['image_id'] = $matches[1];
//     if (isset($matches[2])) {
//         $_GET['tab'] = $matches[2];
//     }
// }

if (isset($_GET['page']) and preg_match('/^[a-z_]*$/', $_GET['page']) and is_file(PHPWG_ROOT_PATH . 'admin/' . $_GET['page'] . '.php')) {
    $page['page'] = $_GET['page'];
} else {
    $page['page'] = 'intro';
}

$link_start = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=';
$conf_link = $link_start . 'configuration&amp;section=';

// +-----------------------------------------------------------------------+
// | Template init                                                         |
// +-----------------------------------------------------------------------+

$title = \Phyxo\Functions\Language::l10n('Phyxo Administration'); // for include/page_header.php
$page['page_banner'] = '<h1>' . \Phyxo\Functions\Language::l10n('Phyxo Administration') . '</h1>';
$page['body_id'] = 'theAdminPage';

$template_filename = 'index';
$template->assign(
    [
        'USERNAME' => $user['username'],
        'ENABLE_SYNCHRONIZATION' => $conf['enable_synchronization'],
        'U_SITE_MANAGER' => $link_start . 'site_manager',
        'U_HISTORY_STAT' => $link_start . 'history',
        //'U_FAQ' => $link_start.'help',
        'U_SITES' => $link_start . 'remote_site',
        'U_MAINTENANCE' => $link_start . 'maintenance',
        'U_CONFIG_GENERAL' => $link_start . 'configuration',
        'U_CONFIG_DISPLAY' => $conf_link . 'default',
        'U_CONFIG_MENUBAR' => $link_start . 'menubar',
        'U_CONFIG_LANGUAGES' => $link_start . 'languages',
        'U_CONFIG_THEMES' => $link_start . 'themes',
        'U_ALBUMS' => $link_start . 'albums',
        'U_ALBUMS_OPTIONS' => $link_start . 'albums_options',
        'U_CAT_UPDATE' => $link_start . 'site_update&amp;site=1',
        'U_RATING' => $link_start . 'rating',
        'U_RECENT_SET' => $link_start . 'batch_manager&amp;filter=prefilter-last_import',
        'U_BATCH' => $link_start . 'batch_manager',
        'U_TAGS' => $link_start . 'tags',
        'U_USERS' => $link_start . 'users',
        'U_GROUPS' => $link_start . 'groups',
        'U_NOTIFICATION_BY_MAIL' => $link_start . 'notification_by_mail',
        'U_RETURN' => \Phyxo\Functions\URL::get_root_url(),
        'U_ADMIN' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php',
        'U_LOGOUT' => \Phyxo\Functions\URL::get_root_url() . 'index.php?act=logout',
        'U_PLUGINS' => $link_start . 'plugins',
        'U_ADD_PHOTOS' => $link_start . 'photos_add',
        'U_UPDATES' => $link_start . 'updates',
        'U_DEV_VERSION' => strpos(PHPWG_VERSION, 'dev') !== false,
        'U_DEV_API' => './api.php',
        'U_DEV_JS_TESTS' => '../tests/functional/'
    ]
);

if ($conf['activate_comments']) {
    $template->assign('U_COMMENTS', $link_start . 'comments');

    // pending comments
    $query = 'SELECT COUNT(1) FROM ' . COMMENTS_TABLE . ' WHERE validated=\'' . $conn->boolean_to_db(false) . '\'';
    list($nb_comments) = $conn->db_fetch_row($conn->db_query($query));

    if ($nb_comments > 0) {
        $template->assign('NB_PENDING_COMMENTS', $nb_comments);
    }
}

// any photo in the caddie?
$query = 'SELECT COUNT(1) FROM ' . CADDIE_TABLE . ' WHERE user_id = ' . $user['id'];
list($nb_photos_in_caddie) = $conn->db_fetch_row($conn->db_query($query));

if ($nb_photos_in_caddie > 0) {
    $template->assign(
        [
            'NB_PHOTOS_IN_CADDIE' => $nb_photos_in_caddie,
            'U_CADDIE' => $link_start . 'batch_manager&amp;filter=prefilter-caddie',
        ]
    );
}

// +-----------------------------------------------------------------------+
// | Plugin menu                                                           |
// +-----------------------------------------------------------------------+

$plugin_menu_links = \Phyxo\Functions\Plugin::trigger_change('get_admin_plugin_menu_links', []);
usort($plugin_menu_links, '\Phyxo\Functions\Utils::name_compare');
$template->assign('plugin_menu_items', $plugin_menu_links);

// +-----------------------------------------------------------------------+
// | Refresh permissions                                                   |
// +-----------------------------------------------------------------------+

// Only for pages witch change permissions
if (in_array($page['page'], ['site_manager', 'site_update'])
    or (!empty($_POST) and in_array($page['page'], [
    'album',        // public/private; lock/unlock, permissions
    'albums_move',
    'albums_options',  // public/private; lock/unlock
    'user_list',    // group assoc; user level
    'user_perm',
]))) {
    \Phyxo\Functions\Utils::invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// | Include specific page                                                 |
// +-----------------------------------------------------------------------+

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_admin_page');
include(PHPWG_ROOT_PATH . 'admin/' . $page['page'] . '.php');

$template->assign('ACTIVE_MENU', $link_start . $page['page']);

// +-----------------------------------------------------------------------+
// | Sending html code                                                     |
// +-----------------------------------------------------------------------+

include(PHPWG_ROOT_PATH . '/admin/include/page_header.php');
include(PHPWG_ROOT_PATH . '/admin/include/page_tail.php');

$template->set_filenames(['admin' => "${template_filename}.tpl"]);

\Phyxo\Functions\Plugin::trigger_notify('loc_end_admin');
\Phyxo\Functions\Utils::flush_page_messages();
$template->pparse('admin');
