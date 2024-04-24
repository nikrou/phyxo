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

use App\Entity\HistorySummary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistorySummary>
 */
class HistorySummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistorySummary::class);
    }

    public function addOrUpdateHistorySummary(HistorySummary $historySummary): int
    {
        $this->getEntityManager()->persist($historySummary);
        $this->getEntityManager()->flush();

        return $historySummary->getId();
    }

    public function deleteAll(): void
    {
        $qb = $this->createQueryBuilder('h');
        $qb->delete();

        $qb->getQuery()->getResult();
    }

    /**
     * @return HistorySummary[]
     */
    public function getSummaryToUpdate(int $year, ? int $month = null, ? int $day = null, ? int $hour = null)
    {
        $qb = $this->createQueryBuilder('h');
        $qb->where('h.year = :year');
        $qb->setParameter('year', $year);

        if (is_null($month)) {
            $qb->andWhere($qb->expr()->isNull('h.month'));
        } else {
            $qb->andWhere('h.month = :month');
            $qb->setParameter('month', $month);
        }

        if (is_null($day)) {
            $qb->andWhere($qb->expr()->isNull('h.day'));
        } else {
            $qb->andWhere('h.day = :day');
            $qb->setParameter('day', $day);
        }

        if (is_null($hour)) {
            $qb->andWhere($qb->expr()->isNull('h.hour'));
        } else {
            $qb->andWhere('h.hour = :hour');
            $qb->setParameter('hour', $hour);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return HistorySummary[]
     */
    public function getSummary(? int $year = null, ? int $month = null, ? int $day = null)
    {
        $qb = $this->createQueryBuilder('h');

        if (!is_null($day)) {
            $qb->where('h.year = :year');
            $qb->andWhere('h.month = :month');
            $qb->andWhere('h.day = :day');
            $qb->andWhere($qb->expr()->isNotNull('h.hour'));
            $qb->orderBy('h.year, h.month, h.day, h.hour', 'ASC');
            $qb->setParameters(new ArrayCollection(['year' => $year, 'month' => $month, 'day' => $day]));
        } elseif (!is_null($month)) {
            $qb->where('h.year = :year');
            $qb->andWhere('h.month = :month');
            $qb->andWhere($qb->expr()->isNotNull('h.day'));
            $qb->andWhere($qb->expr()->isNull('h.hour'));
            $qb->orderBy('h.year, h.month, h.day', 'ASC');
            $qb->setParameters(new ArrayCollection(['year' => $year, 'month' => $month]));
        } elseif (!is_null($year)) {
            $qb->where('h.year = :year');
            $qb->andWhere($qb->expr()->isNotNull('h.month'));
            $qb->andWhere($qb->expr()->isNull('h.day'));
            $qb->orderBy('h.year, h.month', 'ASC');
            $qb->setParameter('year', $year);
        } else {
            $qb->where($qb->expr()->isNotNull('h.year'));
            $qb->andWhere($qb->expr()->isNull('h.month'));
            $qb->orderBy('h.year', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}
