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

use App\Entity\UserCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserCache>
 */
class UserCacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCache::class);
    }

    public function addOrUpdateUserCache(UserCache $userCache): void
    {
        $this->getEntityManager()->persist($userCache);
        $this->getEntityManager()->flush();
    }

    public function deleteAll(): void
    {
        $qb = $this->createQueryBuilder('uc');
        $qb->delete();

        $qb->getQuery()->getResult();
    }

    public function forceRefresh(): void
    {
        $qb = $this->createQueryBuilder('uc');
        $qb->update();
        $qb->set('uc.need_update', true);

        $qb->getQuery()->getResult();
    }

    public function deleteForUser(int $user_id): void
    {
        $qb = $this->createQueryBuilder('uc');
        $qb->delete();
        $qb->where('uc.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }

    public function invalidateNumberAvailableComments(?int $user_id = null): void
    {
        $qb = $this->createQueryBuilder('uc');
        $qb->update();
        $qb->set('uc.nb_available_comments', 0);

        if (!is_null($user_id)) {
            $qb->where('uc.user = :user_id');
            $qb->setParameter('user_id', $user_id);
        }

        $qb->getQuery()->getResult();
    }

    public function invalidateNumberbAvailableTags(?int $user_id = null): void
    {
        $qb = $this->createQueryBuilder('uc');
        $qb->update();
        $qb->set('uc.nb_available_tags', 0);

        if (!is_null($user_id)) {
            $qb->where('uc.user = :user_id');
            $qb->setParameter('user_id', $user_id);
        }

        $qb->getQuery()->getResult();
    }
}
