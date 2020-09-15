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

namespace App\Repository;

use App\Entity\UserFeed;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserFeedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFeed::class);
    }

    public function addOrUpdateUserFeed(UserFeed $user_feed): int
    {
        $this->_em->persist($user_feed);
        $this->_em->flush();

        return $user_feed->getId();
    }

    public function deleteUserFeedNotChecked(): void
    {
        $qb = $this->createQueryBuilder('u');
        $qb->delete();
        $qb->andWhere($qb->expr()->isNull('u.last_check'));

        $qb->getQuery()->getResult();
    }

    public function deleteByUser(int $user_id): void
    {
        $qb = $this->createQueryBuilder('u');
        $qb->delete();
        $qb->where('u.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }
}
