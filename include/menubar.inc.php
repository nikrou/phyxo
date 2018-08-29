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

use Phyxo\Block\BlockManager;

$menu = new BlockManager('menubar');
$menu->load_registered_blocks();
$menu->prepare_display();

if (!empty($page['section']) && $page['section'] == 'search' && isset($page['qsearch_details'])) {
    $template->assign('QUERY_SEARCH', htmlspecialchars($page['qsearch_details']['q']));
}

//--------------------------------------------------------------- external links
if (($block = $menu->get_block('mbLinks')) && !empty($conf['links'])) {
    $block->data = [];
    foreach ($conf['links'] as $url => $url_data) {
        if (!is_array($url_data)) {
            $url_data = ['label' => $url_data];
        }

        if ((!isset($url_data['eval_visible'])) || (eval($url_data['eval_visible']))) {
            $tpl_var = [
                'URL' => $url,
                'LABEL' => $url_data['label']
            ];

            if (!isset($url_data['new_window']) || $url_data['new_window']) {
                $tpl_var['new_window'] = [
                    'NAME' => (isset($url_data['nw_name']) ? $url_data['nw_name'] : ''),
                    'FEATURES' => (isset($url_data['nw_features']) ? $url_data['nw_features'] : '')
                ];
            }
            $block->data[] = $tpl_var;
        }
    }

    if (!empty($block->data)) {
        $block->template = 'menubar_links.tpl';
    }
}

//-------------------------------------------------------------- categories
$block = $menu->get_block('mbCategories');
//------------------------------------------------------------------------ filter
if ($conf['menubar_filter_icon'] && !empty($conf['filter_pages']) && \Phyxo\Functions\Utils::get_filter_page_value('used')) {
    if ($filter['enabled']) {
        $template->assign(
            'U_STOP_FILTER',
            \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::make_index_url([]), ['filter' => 'stop'])
        );
    } else {
        $template->assign(
            'U_START_FILTER',
            \Phyxo\Functions\URL::add_url_params(\Phyxo\Functions\URL::make_index_url([]), ['filter' => 'start-recent-' . $user['recent_period']])
        );
    }
}

if ($block != null) {
    $block->data = [
        'NB_PICTURE' => $user['nb_total_images'],
        'MENU_CATEGORIES' => \Phyxo\Functions\Category::get_categories_menu(),
        'MENU_RECURSIVE_CATEGORIES' => \Phyxo\Functions\Category::get_recursive_categories_menu(),
        'U_CATEGORIES' => \Phyxo\Functions\URL::make_index_url(['section' => 'categories']),
    ];
    $block->template = 'menubar_categories.tpl';
}

//------------------------------------------------------------------------ tags
$block = $menu->get_block('mbTags');
if ($block != null && !empty($page['items']) && 'picture' != \Phyxo\Functions\Utils::script_basename()) {
    if (!empty($page['section']) && 'tags' == $page['section']) {
        $tags = $services['tags']->getCommonTags(
            $user,
            $page['items'],
            $conf['menubar_tag_cloud_items_number'],
            $page['tag_ids']
        );
        $tags = $services['tags']->addLevelToTags($tags);

        foreach ($tags as $tag) {
            $block->data[] = array_merge(
                $tag,
                [
                    'U_ADD' => \Phyxo\Functions\URL::make_index_url(
                        [
                            'tags' => array_merge(
                                $page['tags'],
                                [$tag]
                            )
                        ]
                    ),
                    'URL' => \Phyxo\Functions\URL::make_index_url(['tags' => [$tag]]),
                ]
            );
        }
    } else {
        $selection = array_slice($page['items'], $page['start'], $page['nb_image_page']);
        $tags = $services['tags']->addLevelToTags(
            $services['tags']->getCommonTags($user, $selection, $conf['content_tag_cloud_items_number'])
        );
        foreach ($tags as $tag) {
            $block->data[] = array_merge($tag, ['URL' => \Phyxo\Functions\URL::make_index_url(['tags' => [$tag]])]);
        }
    }
    if (!empty($block->data)) {
        $block->template = 'menubar_tags.tpl';
    }
}

//----------------------------------------------------------- special categories
if (($block = $menu->get_block('mbSpecials')) != null) {
    if (!$services['users']->isGuest()) { // favorites
        $block->data['favorites'] = [
            'URL' => \Phyxo\Functions\URL::make_index_url(['section' => 'favorites']),
            'TITLE' => \Phyxo\Functions\Language::l10n('display your favorites photos'),
            'NAME' => \Phyxo\Functions\Language::l10n('Your favorites')
        ];
    }

    $block->data['most_visited'] = [
        'URL' => \Phyxo\Functions\URL::make_index_url(['section' => 'most_visited']),
        'TITLE' => \Phyxo\Functions\Language::l10n('display most visited photos'),
        'NAME' => \Phyxo\Functions\Language::l10n('Most visited')
    ];

    if ($conf['rate']) {
        $block->data['best_rated'] = [
            'URL' => \Phyxo\Functions\URL::make_index_url(['section' => 'best_rated']),
            'TITLE' => \Phyxo\Functions\Language::l10n('display best rated photos'),
            'NAME' => \Phyxo\Functions\Language::l10n('Best rated')
        ];
    }

    $block->data['recent_pics'] = [
        'URL' => \Phyxo\Functions\URL::make_index_url(['section' => 'recent_pics']),
        'TITLE' => \Phyxo\Functions\Language::l10n('display most recent photos'),
        'NAME' => \Phyxo\Functions\Language::l10n('Recent photos'),
    ];

    $block->data['recent_cats'] = [
        'URL' => \Phyxo\Functions\URL::make_index_url(['section' => 'recent_cats']),
        'TITLE' => \Phyxo\Functions\Language::l10n('display recently updated albums'),
        'NAME' => \Phyxo\Functions\Language::l10n('Recent albums'),
    ];

    $block->data['random'] = [
        'URL' => \Phyxo\Functions\URL::get_root_url() . 'random.php',
        'TITLE' => \Phyxo\Functions\Language::l10n('display a set of random photos'),
        'NAME' => \Phyxo\Functions\Language::l10n('Random photos'),
        'REL' => 'rel="nofollow"'
    ];

    $block->data['calendar'] = [
        'URL' => \Phyxo\Functions\URL::make_index_url(
            [
                'chronology_field' => ($conf['calendar_datefield'] == 'date_available' ? 'posted' : 'created'),
                'chronology_style' => 'monthly',
                'chronology_view' => 'calendar'
            ]
        ),
        'TITLE' => \Phyxo\Functions\Language::l10n('display each day with photos, month per month'),
        'NAME' => \Phyxo\Functions\Language::l10n('Calendar'),
        'REL' => 'rel="nofollow"'
    ];
    $block->template = 'menubar_specials.tpl';
}

//---------------------------------------------------------------------- summary
if (($block = $menu->get_block('mbMenu')) != null) {
    // quick search block will be displayed only if data['qsearch'] is set
    // to "yes"
    $block->data['qsearch'] = true;

    // tags link
    $block->data['tags'] = [
        'TITLE' => \Phyxo\Functions\Language::l10n('display available tags'),
        'NAME' => \Phyxo\Functions\Language::l10n('Tags'),
        'URL' => \Phyxo\Functions\URL::get_root_url() . 'tags.php',
        'COUNTER' => $services['tags']->getNbAvailableTags($user),
    ];

    // search link
    $block->data['search'] = [
        'TITLE' => \Phyxo\Functions\Language::l10n('search'),
        'NAME' => \Phyxo\Functions\Language::l10n('Search'),
        'URL' => \Phyxo\Functions\URL::get_root_url() . 'search.php',
        'REL' => 'rel="search"'
    ];

    if ($conf['activate_comments']) {
        // comments link
        $block->data['comments'] = [
            'TITLE' => \Phyxo\Functions\Language::l10n('display last user comments'),
            'NAME' => \Phyxo\Functions\Language::l10n('Comments'),
            'URL' => \Phyxo\Functions\URL::get_root_url() . 'comments.php',
            'COUNTER' => \Phyxo\Functions\Utils::get_nb_available_comments(),
        ];
    }

    // about link
    $block->data['about'] = [
        'TITLE' => \Phyxo\Functions\Language::l10n('About Phyxo'),
        'NAME' => \Phyxo\Functions\Language::l10n('About'),
        'URL' => \Phyxo\Functions\URL::get_root_url() . 'about.php',
    ];

    // notification
    $block->data['rss'] = [
        'TITLE' => \Phyxo\Functions\Language::l10n('RSS feed'),
        'NAME' => \Phyxo\Functions\Language::l10n('Notification'),
        'URL' => \Phyxo\Functions\URL::get_root_url() . 'notification.php',
        'REL' => 'rel="nofollow"'
    ];
    $block->template = 'menubar_menu.tpl';
}


//--------------------------------------------------------------- identification
if ($services['users']->isGuest()) {
    $template->assign(
        [
            'U_LOGIN' => \Phyxo\Functions\URL::get_root_url() . 'identification.php',
            'U_LOST_PASSWORD' => \Phyxo\Functions\URL::get_root_url() . 'password.php',
            'AUTHORIZE_REMEMBERING' => $conf['authorize_remembering']
        ]
    );
    if ($conf['allow_user_registration']) {
        $template->assign('U_REGISTER', \Phyxo\Functions\URL::get_root_url() . 'register.php');
    }
} else {
    $template->assign('USERNAME', stripslashes($user['username']));
    if ($services['users']->isAuthorizeStatus(ACCESS_CLASSIC)) {
        $template->assign('U_PROFILE', \Phyxo\Functions\URL::get_root_url() . 'profile.php');
    }

    // the logout link has no meaning with Apache authentication : it is not
    // possible to logout with this kind of authentication.
    if (!$conf['apache_authentication']) {
        $template->assign('U_LOGOUT', \Phyxo\Functions\URL::get_root_url() . '?act=logout');
    }
    if ($services['users']->isAdmin()) {
        $template->assign('U_ADMIN', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php');
    }
}

if (($block = $menu->get_block('mbIdentification')) != null) {
    $block->template = 'menubar_identification.tpl';
}

$menu->apply($template, 'MENUBAR', 'blocks');
