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

use App\Entity\History;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, History::class);
    }

    public function addOrUpdateHistory(History $history): int
    {
        $this->_em->persist($history);
        $this->_em->flush();

        return $history->getId();
    }

    public function getHistory($search, $types, int $limit, int $offset = 0, bool $count_only = false)
    {
        $qb = $this->createQueryBuilder('h');

        if (isset($search['fields']['date-after'])) {
            $qb->where('h.date >= :date_after');
            $qb->setParameter('date_after', $search['fields']['date-after']);
        }

        if (isset($search['fields']['date-before'])) {
            $qb->andWhere('h.date <= :date_before');
            $qb->setParameter('date_before', $search['fields']['date-before']);
        }

        if (isset($search['fields']['types'])) {
            foreach (array_keys($types) as $i => $type) {
                if (in_array($type, $search['fields']['types'])) {
                    if ($type === 'none') {
                        $qb->orWhere($qb->expr()->isNull('h.image_type'));
                    } else {
                        $qb->orWhere('h.image_type = :type' . $i);
                        $qb->setParameter('type' . $i, $type);
                    }
                }
            }
        }

        if (isset($search['fields']['user']) && $search['fields']['user'] !== -1) {
            $qb->andWhere('h.user = :user_id');
            $qb->setParameter('user_id', $search['fields']['user']);
        }

        if (isset($search['fields']['image_id'])) {
            $qb->andWhere('h.image = :image_id');
            $qb->setParameter('image_id', $search['fields']['image_id']);
        }

        if (isset($search['fields']['filename'])) {
            if (count($search['image_ids']) > 0) {
                $qb->andWhere($qb->expr()->in('h.image', $search['image_ids']));
            }
        }

        if (isset($search['fields']['ip'])) {
            $qb->andWhere($qb->expr()->like('h.ip', ':ip'));
            $qb->setParameter('ip', $search['fields']['ip']);
        }

        if ($count_only) {
            $qb->select('COUNT(1)');
        }

        if (!$count_only) {
            $qb->setMaxResults($limit);
            $qb->setFirstResult($offset);

            return $qb->getQuery()->getResult();
        } else {
            return $qb->getQuery()->getSingleScalarResult();
        }
    }

    public function getMaxIdForUsers(array $user_ids)
    {
        $qb = $this->createQueryBuilder('h');
        $qb->select('MAX(h.id)');
        $qb->where($qb->expr()->in('h.user', $user_ids));
        $qb->groupBy('h.user');

        return $qb->getQuery()->getResult();
    }

    public function deleteAll()
    {
        $qb = $this->createQueryBuilder('h');
        $qb->delete();

        $qb->getQuery()->getResult();
    }

    public function deleteElements(array $ids = [])
    {
        $qb = $this->createQueryBuilder('h');
        $qb->delete();
        $qb->where($qb->expr()->in('h.image', $ids));

        $qb->getQuery()->getResult();
    }

    public function getDetailsFromNotSummarized()
    {
        $qb = $this->createQueryBuilder('h');
        $qb->addSelect('MAX(h.id) AS max_id, COUNT(1) AS nb_pages');
        $qb->where('h.summarized = :summarized');
        $qb->setParameter('summarized', false);
        $qb->groupBy('h.id, h.date, h.time');
        $qb->orderBy('h.date', 'ASC');
        $qb->addOrderBy('h.time', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function setSummarizedForUnsummarized(int $max_id)
    {
        $qb = $this->createQueryBuilder('h');
        $qb->update();
        $qb->set('h.summarized', ':summarized');
        $qb->setParameter('summarized', true);
        $qb->where('h.summarized = :not_summarized');
        $qb->setParameter('not_summarized', false);
        $qb->andWhere('h.id < :id');
        $qb->setParameter('id', $max_id);

        $qb->getQuery()->getResult();
    }
}
