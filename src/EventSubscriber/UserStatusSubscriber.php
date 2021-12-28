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

use App\Entity\User;
use App\Entity\UserInfos;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class UserStatusSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad
        ];
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof UserInfos) {
            if ($entity->getUser() !== null) {
                $entity->getUser()->addRole(User::getRoleFromStatus($entity->getStatus()));
            }
        } elseif ($entity instanceof User) {
            if ($entity->getUserInfos() !== null) {
                $entity->addRole(User::getRoleFromStatus($entity->getUserInfos()->getStatus()));
            }
        }
    }
}
