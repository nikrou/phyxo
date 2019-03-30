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

if (!defined('PHPWG_ROOT_PATH')) {
    die("Hacking attempt!");
}

use Phyxo\Template\FileCombiner;
use App\Repository\UserFeedRepository;
use App\Repository\HistoryRepository;
use App\Repository\HistorySummaryRepository;
use App\Repository\SearchRepository;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (isset($_GET['action'])) {
    \Phyxo\Functions\Utils::check_token();
}

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'lock_gallery':
        {
            $conf['gallery_locked'] = true;
            \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=maintenance');
            break;
        }
    case 'unlock_gallery':
        {
            $conf['gallery_locked'] = false;
            $_SESSION['page_infos'] = [\Phyxo\Functions\Language::l10n('Gallery unlocked')];
            \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=maintenance');
            break;
        }
    case 'categories':
        {
            \Phyxo\Functions\Utils::images_integrity();
            \Phyxo\Functions\Category::update_uppercats();
            \Phyxo\Functions\Category::update_category('all');
            \Phyxo\Functions\Utils::update_global_rank();
            \Phyxo\Functions\Utils::invalidate_user_cache(true);
            break;
        }
    case 'images':
        {
            \Phyxo\Functions\Utils::images_integrity();
            \Phyxo\Functions\Utils::update_path();
            \Phyxo\Functions\Rate::update_rating_score();
            \Phyxo\Functions\Utils::invalidate_user_cache();
            break;
        }
    case 'delete_orphan_tags':
        {
            $services['tags']->deleteOrphanTags();
            break;
        }
    case 'user_cache':
        {
            \Phyxo\Functions\Utils::invalidate_user_cache();
            break;
        }
    case 'history_detail':
        {
            (new HistoryRepository($conn))->deleteAll();
            break;
        }
    case 'history_summary':
        {
            (new HistorySummaryRepository($conn))->deleteAll();
            break;
        }
    case 'sessions':
        {
    // pwg_session_gc(); @TODO : sessions handler could be files so no db cleanup
            break;
        }
    case 'feeds':
        {
            (new UserFeedRepository($conn))->deleteUserFeedNotChecked();
            break;
        }
    case 'database':
        {
            if ($conn->do_maintenance_all_tables()) {
                $page['infos'][] = \Phyxo\Functions\Language::l10n('All optimizations have been successfully completed.');
            } else {
                $page['errors'][] = \Phyxo\Functions\Language::l10n('Optimizations have been completed with some errors.');
            }
            break;
        }
    case 'search':
        {
            (new SearchRepository($conn))->delete();
            break;
        }
    case 'compiled-templates':
        {
            $template->delete_compiled_templates();
            FileCombiner::clear_combined_files();
            break;
        }
    case 'derivatives':
        {
            \Phyxo\Functions\Utils::clear_derivative_cache($_GET['type']);
            break;
        }
    default:
        {
            break;
        }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$url_format = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=maintenance&amp;action=%s&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token();

$purge_urls[\Phyxo\Functions\Language::l10n('All')] = sprintf($url_format, 'derivatives') . '&amp;type=all';
foreach (\Phyxo\Image\ImageStdParams::get_defined_type_map() as $params) {
    $purge_urls[\Phyxo\Functions\Language::l10n($params->type)] = sprintf($url_format, 'derivatives') . '&amp;type=' . $params->type;
}
$purge_urls[\Phyxo\Functions\Language::l10n(IMG_CUSTOM)] = sprintf($url_format, 'derivatives') . '&amp;type=' . IMG_CUSTOM;

$template->assign(
    [
        'U_MAINT_CATEGORIES' => sprintf($url_format, 'categories'),
        'U_MAINT_IMAGES' => sprintf($url_format, 'images'),
        'U_MAINT_ORPHAN_TAGS' => sprintf($url_format, 'delete_orphan_tags'),
        'U_MAINT_USER_CACHE' => sprintf($url_format, 'user_cache'),
        'U_MAINT_HISTORY_DETAIL' => sprintf($url_format, 'history_detail'),
        'U_MAINT_HISTORY_SUMMARY' => sprintf($url_format, 'history_summary'),
        'U_MAINT_SESSIONS' => sprintf($url_format, 'sessions'),
        'U_MAINT_FEEDS' => sprintf($url_format, 'feeds'),
        'U_MAINT_DATABASE' => sprintf($url_format, 'database'),
        'U_MAINT_SEARCH' => sprintf($url_format, 'search'),
        'U_MAINT_COMPILED_TEMPLATES' => sprintf($url_format, 'compiled-templates'),
        'U_MAINT_DERIVATIVES' => sprintf($url_format, 'derivatives'),
        'purge_derivatives' => $purge_urls,
        //'U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=maintenance',
    ]
);


if ($conf['gallery_locked']) {
    $template->assign(
        [
            'U_MAINT_UNLOCK_GALLERY' => sprintf($url_format, 'unlock_gallery'),
        ]
    );
} else {
    $template->assign(
        [
            'U_MAINT_LOCK_GALLERY' => sprintf($url_format, 'lock_gallery'),
        ]
    );
}

// +-----------------------------------------------------------------------+
// | Define advanced features                                              |
// +-----------------------------------------------------------------------+

$advanced_features = [];

//$advanced_features is array of array composed of CAPTION & URL
$advanced_features = \Phyxo\Functions\Plugin::trigger_change(
    'get_admin_advanced_features_links',
    $advanced_features
);

$template->assign('advanced_features', $advanced_features);

$template_filename = 'maintenance';
