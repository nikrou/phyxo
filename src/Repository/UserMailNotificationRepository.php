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

use App\Entity\UserMailNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserMailNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMailNotification::class);
    }

    public function addOrUpdateUserMailNotification(UserMailNotification $user_mail_notification)
    {
        $this->_em->persist($user_mail_notification);
        $this->_em->flush();
    }

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

        if (count($check_keys) > 0) {
            $qb->andWhere($qb->expr()->in('n.check_key', $check_keys));
        }


        if (count($orders) > 0) {
            foreach ($orders as $order_by) {
                $qb->orderBy($order_by);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findUsersWithNoMailNotificationInfos()
    {
        $subQuery = $this->createQueryBuilder('n');
        $subQuery->select('identity(n.user)');

        $qb = $this->_em->createQueryBuilder();
        $qb->from('App\Entity\User', 'u');
        $qb->select('u');
        $qb->where('u.mail_address != \'\'');
        $qb->andWhere($qb->expr()->isNotNull('u.mail_address'));
        $qb->andWhere($qb->expr()->notIn('u.id', $subQuery->getDQL()));

        return $qb->getQuery()->getResult();
    }

    public function deleteByCheckKeys(array $check_keys)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->delete();
        $qb->where($qb->expr()->in('n.check_key', $check_keys));

        $qb->getQuery()->getResult();
    }
}
