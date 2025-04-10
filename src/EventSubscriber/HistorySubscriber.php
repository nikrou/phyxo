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

namespace App\EventSubscriber;

use App\DataMapper\UserMapper;
use App\Entity\Album;
use App\Entity\History;
use App\Entity\Image;
use App\Events\HistoryEvent;
use App\Repository\HistoryRepository;
use App\Security\AppUserService;
use DateTime;
use Phyxo\Conf;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HistorySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Conf $conf,
        private readonly AppUserService $appUserService,
        private readonly UserMapper $userMapper,
        private readonly HistoryRepository $historyRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HistoryEvent::class => 'onVisit',
        ];
    }

    public function onVisit(HistoryEvent $event): void
    {
        if ($this->userMapper->isAdmin() && !$this->conf['history_admin']) {
            return;
        }

        if ($this->appUserService->isGuest() && !$this->conf['history_guest']) {
            return;
        }

        $now = new DateTime();
        $history = new History();
        $history->setDate($now);
        $history->setTime($now);
        $history->setSection($event->getSection());
        $history->setUser($this->appUserService->getUser());
        if ($event->getAlbum() instanceof Album) {
            $history->setAlbum($event->getAlbum());
        }

        if ($event->getImage() instanceof Image) {
            $history->setImage($event->getImage());
        }

        $history->setIp(md5($event->getIp()));
        $history->setTagIds($event->getTagIds());

        $this->historyRepository->addOrUpdateHistory($history);
    }
}
