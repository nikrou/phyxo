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
use App\Entity\History;
use App\Events\HistoryEvent;
use App\Repository\HistoryRepository;
use Phyxo\Conf;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HistorySubscriber implements EventSubscriberInterface
{
    private $conf, $userMapper, $historyRepository;

    public function __construct(Conf $conf, UserMapper $userMapper, HistoryRepository $historyRepository)
    {
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->historyRepository = $historyRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HistoryEvent::class => 'onVisit'
        ];
    }

    public function onVisit(HistoryEvent $event)
    {
        if ($this->userMapper->isAdmin() && !$this->conf['history_admin']) {
            return;
        }

        if ($this->userMapper->isGuest() && !$this->conf['history_guest']) {
            return;
        }

        $now = new \DateTime();
        $history = new History();
        $history->setDate($now);
        $history->setTime($now);
        $history->setSection($event->getSection());
        $history->setUser($this->userMapper->getUser());
        if ($event->getSection() === 'categories' && !is_null($event->getAlbum())) {
            $history->setAlbum($event->getAlbum());
        }
        $history->setImage($event->getImage());
        $history->setIp(md5($event->getIp()));

        $this->historyRepository->addOrUpdateHistory($history);
    }
}
