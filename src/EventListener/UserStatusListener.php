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

namespace App\EventListener;

use App\Entity\User;
use App\Entity\UserInfos;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postLoad)]
class UserStatusListener
{
    public function postLoad(PostLoadEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof UserInfos) {
            if ($entity->getUser() instanceof User) {
                $entity->getUser()->addRole(User::getRoleFromStatus($entity->getStatus()));
            }
        } elseif ($entity instanceof User) {
            $entity->addRole(User::getRoleFromStatus($entity->getUserInfos()->getStatus()));
        }
    }
}
