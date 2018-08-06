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
// |                           initialization                              |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_GUEST);

trigger_notify('loc_begin_notification');

// +-----------------------------------------------------------------------+
// |                          new feed creation                            |
// +-----------------------------------------------------------------------+

$page['feed'] = md5(uniqid(true));

$query = 'INSERT INTO ' . USER_FEED_TABLE . ' (id, user_id, last_check) VALUES (\'' . $page['feed'] . '\', ' . $user['id'] . ', NULL);';
$conn->db_query($query);

$feed_url = \Phyxo\Functions\URL::get_root_url() . 'feed.php';
if ($services['users']->isGuest()) {
    $feed_image_only_url = $feed_url;
    $feed_url .= '?feed=' . $page['feed'];
} else {
    $feed_url .= '?feed=' . $page['feed'];
    $feed_image_only_url = $feed_url . '&amp;image_only';
}

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+

$title = \Phyxo\Functions\Language::l10n('Notification');
$page['body_id'] = 'theNotificationPage';
$page['meta_robots'] = array('noindex' => 1, 'nofollow' => 1);

$template->set_filenames(array('notification' => 'notification.tpl'));
$template->assign(
    array(
        'U_FEED' => $feed_url,
        'U_FEED_IMAGE_ONLY' => $feed_image_only_url,
    )
);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (!isset($themeconf['hide_menu_on']) or !in_array('theNotificationPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+
include(PHPWG_ROOT_PATH . 'include/page_header.php');
trigger_notify('loc_end_notification');
flush_page_messages();
include(PHPWG_ROOT_PATH . 'include/page_tail.php');
$template->pparse('notification');
