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

use Phyxo\Functions\Utils;
use App\Repository\ImageRepository;
use Phyxo\Extension\Theme;

// determine the initial instant to indicate the generation time of this page
$t2 = microtime(true);

// @TODO: move it in symfony configuration. Needed because of constants
$prefixeTable = 'phyxo_';

$pwg_loaded_plugins = [];

$debug = '';
$page = [
    'infos' => [],
    'errors' => [],
    'warnings' => [],
    'count_queries' => 0,
    'queries_time' => 0,
];
$user = ['theme' => 'treflez'];
$lang = [];
$lang_info = [];
$header_msgs = [];
$header_notes = [];
$filter = [];

defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');
$db_config_file = __DIR__ . '/../local/config/database.inc.php';
if (!Utils::phyxoInstalled($db_config_file)) {
    header('Location: ' . \Phyxo\Functions\URL::get_root_url() . 'admin/install');
    exit();
}

$conn = $container->get('phyxo.conn');
$conf = $container->get('phyxo.conf');
$template = $container->get('templating.engine.smarty');

include(__DIR__ . '/constants.php');

if (!empty($conf['show_php_errors'])) {
    @ini_set('error_reporting', $conf['show_php_errors']);
    @ini_set('display_errors', true);
}

// if ($userMapper->isAdmin() && $conf['check_upgrade_feed']) {
//     if (empty($conf['phyxo_db_version']) or $conf['phyxo_db_version'] != \Phyxo\Functions\Utils::get_branch_from_version(PHPWG_VERSION)) {
//         \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url() . 'admin/?page=upgrade');
//     }
// }

if ($conn->getLayer() === 'mysql') {
    $conf_derivatives = @unserialize(stripslashes($conf['derivatives']));
} else {
    $conf_derivatives = @unserialize($conf['derivatives']);
}
\Phyxo\Image\ImageStdParams::load_from_db($conf_derivatives);
\Phyxo\Functions\Plugin::load_plugins();

// users can have defined a custom order pattern, incompatible with GUI form
if (isset($conf['order_by_custom'])) {
    $conf['order_by'] = $conf['order_by_custom'];
}
if (isset($conf['order_by_inside_category_custom'])) {
    $conf['order_by_inside_category'] = $conf['order_by_inside_category_custom'];
}


if ($app_user) {
    $user = $app_user->getInfos();
}

$userMapper->buildUser($app_user->getId(), (defined('IN_ADMIN') && IN_ADMIN) ? false : true);

\Phyxo\Functions\Plugin::trigger_notify('user_init', $app_user);

// language files
\Phyxo\Functions\Language::load_language('common.lang', '', ['language' => $app_user->getLanguage()]);

if ($userMapper->isAdmin() || (defined('IN_ADMIN') && IN_ADMIN)) {
    \Phyxo\Functions\Language::load_language('admin.lang', '', ['language' => $app_user->getLanguage()]);
}
\Phyxo\Functions\Plugin::trigger_notify('loading_lang');
\Phyxo\Functions\Language::load_language('lang', __DIR__ . '/' . PWG_LOCAL_DIR, ['local' => true]);

if (!defined('IN_WS') || !IN_WS) {
    $template->setLang($lang);
    $template->setLangInfo($lang_info);
    $template->setConf($conf);
    $template->postConstruct();
    if (defined('IN_ADMIN') && IN_ADMIN) { // Admin template
        $template->setTheme(new Theme(__DIR__ . '/../admin/theme', '.'));
    } else {
        $theme = $user['theme'];
        $template->setTheme(new Theme(__DIR__ . '/../themes', 'treflez'));
    }
}

// @TODO : move elsewhere
if ($conf['gallery_locked']) {
    $header_msgs[] = \Phyxo\Functions\Language::l10n('The gallery is locked for maintenance. Please, come back later.');

    if (\Phyxo\Functions\Utils::script_basename() != 'identification' && !$userMapper->isAdmin()) {
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
        $header_msgs[] = 'Some database upgrades are missing, <a class="alert-link" href="' . \Phyxo\Functions\URL::get_root_url() . 'admin/?page=upgrade_feed">upgrade now</a>';
    }
}

if (count($header_msgs) > 0) {
    $template->assign('header_msgs', $header_msgs);
    $header_msgs = [];
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
            $filter['matches'] = [];
            $filter['enabled'] = preg_match('/^start-recent-(\d+)$/', $_GET['filter'], $filter['matches']) === 1;
        } else {
            $filter['enabled'] = isset($_SESSION['filter_enabled']) ? $_SESSION['filter_enabled'] : false;
        }
    } else {
        $filter['enabled'] = false;
    }

    if ($filter['enabled']) {
        $filter_key = isset($_SESSION['filter_check_key']) ? $_SESSION['filter_check_key'] : ['user' => 0, 'recent_period' => -1, 'time' => 0, 'date' => ''];

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
            $filter_key = [
                'user' => (int)$user['id'],
                'recent_period' => (int)$filter['recent_period'],
                'time' => time(),
                'date' => date('Ymd')
            ];

            $filter['categories'] = $categoryMapper->getComputedCategories($user, (int)$filter['recent_period']);

            $filter['visible_categories'] = implode(',', array_keys($filter['categories']));
            if (empty($filter['visible_categories'])) {
                // Must be not empty
                $filter['visible_categories'] = -1;
            }

            $result = (new ImageRepository($conn))->findVisibleImages(
                explode(',', $filter['visible_categories']),
                $filter['recent_period']
            );
            $filter['visible_images'] = implode(',', $conn->result2array($result, null, 'image_id'));

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
            $filter['categories'] = isset($_SESSION['filter_categories']) ? $_SESSION['filter_categories'] : [];
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
