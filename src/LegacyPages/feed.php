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

include_once(__DIR__ . '/../../include/common.inc.php');

use App\Repository\UserFeedRepository;
use App\Repository\BaseRepository;
use Phyxo\Functions\Notification;

// +-----------------------------------------------------------------------+
// |                               functions                               |
// +-----------------------------------------------------------------------+

/**
 * creates a Unix timestamp (number of seconds since 1970-01-01 00:00:00
 * GMT) from a MySQL datetime format (2005-07-14 23:01:37)
 *
 * @param string mysql datetime format
 * @return int timestamp
 */
function datetime_to_ts($datetime)
{
    return strtotime($datetime);
}

/**
 * creates an ISO 8601 format date (2003-01-20T18:05:41+04:00) from Unix
 * timestamp (number of seconds since 1970-01-01 00:00:00 GMT)
 *
 * function copied from Dotclear project http://dotclear.net
 *
 * @param int timestamp
 * @return string ISO 8601 date format
 */
function ts_to_iso8601($ts)
{
    $tz = date('O', $ts);
    $tz = substr($tz, 0, -2) . ':' . substr($tz, -2);
    return date('Y-m-d\\TH:i:s', $ts) . $tz;
}

// +-----------------------------------------------------------------------+
// |                            initialization                             |
// +-----------------------------------------------------------------------+

\Phyxo\Functions\Utils::check_input_parameter('feed', $_GET, false, '/^[0-9a-z]*$/i');

$feed_id = isset($_GET['feed']) ? $_GET['feed'] : '';
$image_only = isset($_GET['image_only']);

if (!empty($feed_id)) {
    $result = (new UserFeedRepository($conn))->findById($feed_id);
    $feed_row = $conn->db_fetch_assoc($result);
    if (empty($feed_row)) {
        \Phyxo\Functions\HTTP::page_not_found(\Phyxo\Functions\Language::l10n('Unknown feed identifier'));
    }
    if ($feed_row['user_id'] != $user['id']) { // new user
        $user = $userMapper->buildUser($feed_row['user_id'], true);
    }
} else {
    $image_only = true;
    if (!$userMapper->isGuest()) { // auto session was created - so switch to guest
        $user = $userMapper->buildUser($conf['guest_id'], true);
    }
}

$dbnow = (new BaseRepository($conn))->getNow();
\Phyxo\Functions\URL::set_make_full_url();

$rss = new UniversalFeedCreator();
$rss->title = $conf['gallery_title'];
$rss->title .= ' (as ' . stripslashes($app_user->getUsername()) . ')';

$rss->link = \Phyxo\Functions\URL::get_gallery_home_url();

// +-----------------------------------------------------------------------+
// |                            Feed creation                              |
// +-----------------------------------------------------------------------+

$news = [];
$notification = new Notification($conn, $userMapper, $categoryMapper);
if (!$image_only) {
    $news = $notification->news($feed_row['last_check'], $dbnow, true, true);

    if (count($news) > 0) {
        $item = new FeedItem();
        $item->title = \Phyxo\Functions\Language::l10n('New on %s', \Phyxo\Functions\DateTime::format_date($dbnow));
        $item->link = \Phyxo\Functions\URL::get_gallery_home_url();

        // content creation
        $item->description = '<ul>';
        foreach ($news as $line) {
            $item->description .= '<li>' . $line . '</li>';
        }
        $item->description .= '</ul>';
        $item->descriptionHtmlSyndicated = true;

        $item->date = ts_to_iso8601(datetime_to_ts($dbnow));
        $item->author = $conf['rss_feed_author'];
        $item->guid = sprintf('%s', $dbnow);;

        $rss->addItem($item);

        (new UserFeedRepository($conn))->updateUserFeed(['last_check' => $dbnow], $feed_id);
    }
}

if (!empty($feed_id) and empty($news)) {// update the last check from time to time to avoid deletion by maintenance tasks
    if (!isset($feed_row['last_check']) or time() - datetime_to_ts($feed_row['last_check']) > 30 * 24 * 3600) {
        (new UserFeedRepository($conn))->updateUserFeed(['last_check' => $conn->db_get_recent_period_expression(-15, $dbnow)], $feed_id);
    }
}

$dates = $notification->get_recent_post_dates_array($conf['recent_post_dates']['RSS']);

foreach ($dates as $date_detail) { // for each recent post date we create a feed item
    $item = new FeedItem();
    $date = $date_detail['date_available'];
    $item->title = $notification->get_title_recent_post_date($date_detail);
    $item->link = \Phyxo\Functions\URL::make_index_url(
        [
            'chronology_field' => 'posted',
            'chronology_style' => 'monthly',
            'chronology_view' => 'calendar',
            'chronology_date' => explode('-', substr($date, 0, 10))
        ]
    );

    $item->description .= '<a href="' . \Phyxo\Functions\URL::make_index_url() . '">' . $conf['gallery_title'] . '</a><br> ';
    $item->description .= $notification->get_html_description_recent_post_date($date_detail);

    $item->descriptionHtmlSyndicated = true;

    $item->date = ts_to_iso8601(datetime_to_ts($date));
    $item->author = $conf['rss_feed_author'];
    $item->guid = sprintf('%s', 'pics-' . $date);;

    $rss->addItem($item);
}

$fileName = __DIR__ . '/../../' . $conf['data_location'] . 'tmp';
\Phyxo\Functions\Utils::mkgetdir($fileName); // just in case
$fileName .= '/feed.xml';
// send XML feed
echo $rss->saveFeed('RSS2.0', $fileName, true);
