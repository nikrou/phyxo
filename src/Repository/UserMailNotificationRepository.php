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

use App\Entity\User;
use App\Entity\UserMailNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMailNotification>
 */
class UserMailNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMailNotification::class);
    }

    public function addOrUpdateUserMailNotification(UserMailNotification $user_mail_notification): void
    {
        $this->getEntityManager()->persist($user_mail_notification);
        $this->getEntityManager()->flush();
    }

    /**
     * @param array<string> $check_keys
     * @param array<string> $orders
     *
     * @return UserMailNotification[]
     */
    public function findInfosForUsers(bool $send, ?bool $enabled_filter_value, array $check_keys = [], array $orders = [])
    {
        $qb = $this->createQueryBuilder('n');
        $qb->leftJoin('n.user', 'u');
        if ($send) {
            $qb->where('u.mail_address != \'\'');
            $qb->andWhere($qb->expr()->isNotNull('u.mail_address'));
            $qb->andWhere('n.enabled = true');
        }

        if (!is_null($enabled_filter_value)) {
            $qb->andWhere('n.enabled = :enabled');
            $qb->setParameter('enabled', $enabled_filter_value);
        }

        if ($check_keys !== []) {
            $qb->andWhere($qb->expr()->in('n.check_key', $check_keys));
        }

        foreach ($orders as $order_by) {
            $qb->orderBy($order_by);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return User[]
     */
    public function findUsersWithNoMailNotificationInfos()
    {
        $subQuery = $this->createQueryBuilder('n');
        $subQuery->select('identity(n.user)');

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->from(User::class, 'u');
        $qb->select('u');
        $qb->where('u.mail_address != \'\'');
        $qb->andWhere($qb->expr()->isNotNull('u.mail_address'));
        $qb->andWhere($qb->expr()->notIn('u.id', $subQuery->getDQL()));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string> $check_keys
     */
    public function deleteByCheckKeys(array $check_keys): void
    {
        $qb = $this->createQueryBuilder('n');
        $qb->delete();
        $qb->where($qb->expr()->in('n.check_key', $check_keys));

        $qb->getQuery()->getResult();
    }

    public function deleteByUserId(int $user_id): void
    {
        $qb = $this->createQueryBuilder('n');
        $qb->delete();
        $qb->where('n.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }
}
