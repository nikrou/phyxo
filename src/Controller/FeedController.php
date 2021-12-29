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

use App\Entity\UserFeed;
use Phyxo\Conf;
use Phyxo\MenuBar;
use App\Repository\UserFeedRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Notification;
use App\Security\AppUserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class FeedController extends CommonController
{
    public function notification(Request $request, Conf $conf, MenuBar $menuBar, TranslatorInterface $translator, UserFeedRepository $userFeedRepository, AppUserService $appUserService)
    {
        $tpl_params = [];

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification');

        $feed = $userFeedRepository->findOneBy(['user' => $appUserService->getUser()]);
        if (is_null($feed)) {
            $feed = new UserFeed();
            $feed->setUser($appUserService->getUser());
            $userFeedRepository->addOrUpdateUserFeed($feed);
        }

        if ($appUserService->getUser()->isGuest()) {
            $tpl_params['U_FEED'] = $this->generateUrl('feed', ['feed_id' => $feed->getUuid()]);
            $tpl_params['U_FEED_IMAGE_ONLY'] = $this->generateUrl('feed_image_only', ['feed_id' => $feed->getUuid()]);
        } else {
            $tpl_params['U_FEED'] = $this->generateUrl('feed', ['feed_id' => $feed->getUuid()]);
            $tpl_params['U_FEED_IMAGE_ONLY'] = $this->generateUrl('feed_image_only', ['feed_id' => $feed->getUuid()]);
        }

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('notification.html.twig', $tpl_params);
    }

    public function notificationSubscribe()
    {
        return new Response('Not yet');
    }

    public function notificationUnsubscribe()
    {
        return new Response('Not yet');
    }

    public function feed(
        string $feed_id,
        Conf $conf,
        AppUserService $appUserService,
        UserFeedRepository $userFeedRepository,
        string $cacheDir,
        Notification $notification,
        TranslatorInterface $translator,
        bool $image_only = false
    ) {
        $feed = $userFeedRepository->findOneBy(['uuid' => $feed_id]);
        if (is_null($feed)) {
            throw $this->createNotFoundException($translator->trans('Unknown feed identifier'));
        }

        $now = new \DateTime();

        $rss = new \UniversalFeedCreator();
        $rss->title = $conf['gallery_title'];
        $rss->title .= ' (as ' . $appUserService->getUser()->getUserIdentifier() . ')';

        $rss->link = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $news = [];
        if (!$image_only) {
            $news = $notification->news($feed->getLastCheck(), $now, true, true);
            if (count($news) > 0) {
                $item = new \FeedItem();
                $item->title = $translator->trans('New on {date}', ['date' => $now->format('Y-m-d H:m:i')]);
                $item->link = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

                // content creation
                $item->description = '<ul>';
                foreach ($news as $line) {
                    $item->description .= '<li>' . $line . '</li>';
                }
                $item->description .= '</ul>';
                $item->descriptionHtmlSyndicated = true;

                $item->date = $now->format('c');
                $item->author = $conf['rss_feed_author'];
                $item->guid = sprintf('%s', $now->getTimestamp());

                $rss->addItem($item);

                $feed->setLastCheck($now);
                $userFeedRepository->addOrUpdateUserFeed($feed);
            }
        }

        if (!is_null($feed) && empty($news)) { // update the last check from time to time to avoid deletion by maintenance tasks
            if (is_null($feed->getLastCheck()) || $now->diff($feed->getLastCheck())->format('s') > (30 * 24 * 3600)) {
                $feed->setLastCheck($now->add(new \DateInterval('P15D')));
                $userFeedRepository->addOrUpdateUserFeed($feed);
            }
        }

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
                    'year' => $date->format('Y'),
                    'month' => $date->format('m'),
                    'day' => $date->format('d'),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $item->description .= '<a href="' . $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . '">' . $conf['gallery_title'] . '</a><br> ';
            $item->description .= $notification->get_html_description_recent_post_date($date_detail, $conf['picture_ext']);

            $item->descriptionHtmlSyndicated = true;

            $item->date = $date->format('c');
            $item->author = $conf['rss_feed_author'];
            $item->guid = sprintf('%s', 'pics-' . $date->format('c'));

            $rss->addItem($item);
        }

        $fileName = $cacheDir . '/feed.xml';
        echo $rss->saveFeed('RSS2.0', $fileName, true);
    }
}
