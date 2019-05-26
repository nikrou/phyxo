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

namespace App\Controller;

use Phyxo\Template\Template;
use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\Extension\Theme;
use Phyxo\Functions\Language;
use Phyxo\EntityManager;
use App\Repository\UserFeedRepository;
use App\Repository\BaseRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\DataMapper\UserMapper;
use App\DataMapper\CategoryMapper;
use Phyxo\Functions\Notification;

class FeedController extends CommonController
{
    private $conf;

    public function notification(Template $template, Conf $conf, EntityManager $em, string $phyxoVersion, string $phyxoWebsite, MenuBar $menuBar, string $themesDir)
    {
        $tpl_params = [];

        $tpl_params = array_merge($this->addThemeParams($template, $conf, $this->getUser(), $themesDir, $phyxoVersion, $phyxoWebsite), $tpl_params);
        $tpl_params['PAGE_TITLE'] = Language::l10n('Notification');

        $feed_id = md5(uniqid(true));
        $em->getRepository(UserFeedRepository::class)->addUserFeed(['id' => $feed_id, 'user_id' => $this->getUser()->getId()]);
        if ($this->getUser()->isGuest()) {
            $tpl_params['U_FEED'] = $this->generateUrl('feed', ['feed_id' => $feed_id]);
            $tpl_params['U_FEED_IMAGE_ONLY'] = $this->generateUrl('feed', ['feed_id' => $feed_id]);
        } else {
            $tpl_params['U_FEED'] = $this->generateUrl('feed', ['feed_id' => $feed_id]);
            $tpl_params['U_FEED_IMAGE_ONLY'] = $this->generateUrl('feed_image_only', ['feed_id' => $feed_id]);
        }

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        \Phyxo\Functions\Plugin::trigger_notify('init');

        return $this->render('notification.tpl', $tpl_params);
    }

    public function feed(string $feed_id, bool $image_only = false, Conf $conf, EntityManager $em, UserMapper $userMapper, CategoryMapper $categoryMapper, string $cacheDir)
    {
        $result = $em->getRepository(UserFeedRepository::class)->findById($feed_id);
        $feed_row = $em->getConnection()->db_fetch_assoc($result);
        if (empty($feed_row)) {
            throw $this->createNotFoundException(Language::l10n('Unknown feed identifier'));
        }

        $dbnow = $em->getRepository(BaseRepository::class)->getNow();

        $rss = new \UniversalFeedCreator();
        $rss->title = $conf['gallery_title'];
        $rss->title .= ' (as ' . $this->getUser()->getUsername() . ')';

        $rss->link = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $news = [];
        $notification = new Notification($em->getConnection(), $userMapper, $categoryMapper);
        if (!$image_only) {
            $news = $notification->news($feed_row['last_check'], $dbnow, true, true);
            if (count($news) > 0) {
                $item = new \FeedItem();
                $item->title = Language::l10n('New on %s', \Phyxo\Functions\DateTime::format_date($dbnow));
                $item->link = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

                // content creation
                $item->description = '<ul>';
                foreach ($news as $line) {
                    $item->description .= '<li>' . $line . '</li>';
                }
                $item->description .= '</ul>';
                $item->descriptionHtmlSyndicated = true;

                $item->date = $this->ts_to_iso8601(strtotime($dbnow));
                $item->author = $conf['rss_feed_author'];
                $item->guid = sprintf('%s', $dbnow);;

                $rss->addItem($item);

                $em->getRepository(UserFeedRepository::class)->updateUserFeed(['last_check' => $dbnow], $feed_id);
            }
        }

        if (!empty($feed_id) and empty($news)) {// update the last check from time to time to avoid deletion by maintenance tasks
            if (!isset($feed_row['last_check']) or time() - strtotime($feed_row['last_check']) > 30 * 24 * 3600) {
                $em->getRepository(UserFeedRepository::class)->updateUserFeed(['last_check' => $em->getConnection()->db_get_recent_period_expression(-15, $dbnow)], $feed_id);
            }
        }

        if ($em->getConnection()->getLayer() === 'mysql') {
            $conf_derivatives = @unserialize(stripslashes($conf['derivatives']));
        } else {
            $conf_derivatives = @unserialize($conf['derivatives']);
        }
        \Phyxo\Image\ImageStdParams::load_from_db($conf_derivatives);

        $dates = $notification->get_recent_post_dates_array($conf['recent_post_dates']['RSS']);

        foreach ($dates as $date_detail) { // for each recent post date we create a feed item
            $item = new \FeedItem();
            $date = $date_detail['date_available'];
            $item->title = $notification->get_title_recent_post_date($date_detail);
            $item->link = $this->generateUrl(
                'calendar_categories_monthly_year_month_day',
                [
                    'date_type' => 'posted',
                    'view_type' => 'calendar',
                    'year' => substr($date, 0, 4),
                    'month' => substr($date, 5, 2),
                    'day' => substr($date, 8, 2)
                ]
            );

            $item->description .= '<a href="' . $this->generateUrl('homepage') . '">' . $conf['gallery_title'] . '</a><br> ';
            $item->description .= $notification->get_html_description_recent_post_date($date_detail, $conf['picture_ext']);

            $item->descriptionHtmlSyndicated = true;

            $item->date = $this->ts_to_iso8601(strtotime($date));
            $item->author = $conf['rss_feed_author'];
            $item->guid = sprintf('%s', 'pics-' . $date);;

            $rss->addItem($item);
        }

        $fileName = $cacheDir . '/feed.xml';
        echo $rss->saveFeed('RSS2.0', $fileName, true);
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
    protected function ts_to_iso8601($ts)
    {
        $tz = date('O', $ts);
        $tz = substr($tz, 0, -2) . ':' . substr($tz, -2);

        return date('Y-m-d\\TH:i:s', $ts) . $tz;
    }
}
