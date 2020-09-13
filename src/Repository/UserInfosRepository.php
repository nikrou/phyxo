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

use App\Entity\UserInfos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;

class UserInfosRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInfos::class);
    }

    public function getMaxLastModified()
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('MAX(u.lastmodified) as max, COUNT(1) as count');

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function updateFieldForUsers(string $field, string $value, array $user_ids): void
    {
        $qb = $this->createQueryBuilder('u');
        $qb->update();
        $qb->set('u.' . $field, ':value');
        $qb->setParameter('value', $value);
        $qb->where($qb->expr()->in('u.user_id', $user_ids));

        $qb->getQuery()->getResult();
    }

    public function updateFieldsForUsers(array $fields, array $user_ids)
    {
        $qb = $this->createQueryBuilder('u');
        $qb->update();
        foreach ($fields as $field => $value) {
            $qb->set('u.' . $field, ':value');
            $qb->setParameter('value', $value);
        }
        $qb->where($qb->expr()->in('u.user_id', $user_ids));

        $qb->getQuery()->getResult();
    }

    public function getNewUsers(\DateTimeInterface $start = null, \DateTimeInterface $end = null)
    {
        $qb = $this->createQueryBuilder('u');
        $this->addBetweenDateCondition($qb, $start, $end);

        $qb->getQuery()->getResult();
    }

    public function countNewUsers(\DateTimeInterface $start = null, \DateTimeInterface $end = null): int
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('count(1)');
        $this->addBetweenDateCondition($qb, $start, $end);

        return $qb->getQuery()->getSingleScalarResult();
    }

    private function addBetweenDateCondition(QueryBuilder $qb, \DateTimeInterface $start = null, \DateTimeInterface $end = null)
    {
        if (!is_null($start)) {
            $qb->andWhere('u.registration_date > :start');
            $qb->setParameter('start', $start);
        }

        if (!is_null($end)) {
            $qb->andWhere('u.registration_date <= :end');
            $qb->setParameter('end', $end);
        }

        return $qb;
    }
}
