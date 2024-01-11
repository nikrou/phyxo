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
use App\Form\Model\SearchRulesModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<History>
 */
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

    /**
     * @param array<string> $types
     *
     * @return History[]
     */
    public function getHistory(SearchRulesModel $rules, array $types, int $limit, int $offset = 0, bool $count_only = false)
    {
        $qb = $this->createQueryBuilder('h');

        if ($rules->getStart()) {
            $qb->where('h.date >= :start');
            $qb->setParameter('start', $rules->getStart());
        }

        if ($rules->getEnd()) {
            $qb->andWhere('h.date <= :end');
            $qb->setParameter('end', $rules->getEnd());
        }

        if ($rules->getTypes()) {
            $orXExpressions = [];
            foreach (array_keys($types) as $i => $type) {
                if (in_array($type, $rules->getTypes())) {
                    if ($type === 'none') {
                        $orXExpressions[] = $qb->expr()->isNull('h.image_type');
                    } else {
                        $orXExpressions[] = $qb->expr()->eq('h.image_type', ':type' . $i);
                        $qb->setParameter('type' . $i, $type);
                    }
                }
            }
            if (count($orXExpressions) > 0) {
                $qb->andWhere($qb->expr()->orX(...$orXExpressions));
            }
        }

        if ($rules->getUser()) {
            $qb->andWhere('h.user = :user');
            $qb->setParameter('user', $rules->getUser());
        }

        if ($rules->getImageId()) {
            $qb->andWhere('h.image = :image_id');
            $qb->setParameter('image_id', $rules->getImageId());
        }

        if (count($rules->getImageIds()) > 0) {
            $qb->andWhere($qb->expr()->in('h.image', $rules->getImageIds()));
        }

        if ($count_only) {
            $qb->select('COUNT(1)');

            /** @phpstan-ignore-next-line */
            return $qb->getQuery()->getSingleScalarResult();
        } else {
            $qb->setMaxResults($limit);
            $qb->setFirstResult($offset);
            $qb->orderBy('h.date', 'DESC');
            $qb->addOrderBy('h.time', 'DESC');

            return $qb->getQuery()->getResult();
        }
    }

    /**
     * @param int[] $user_ids
     */
    public function getMaxIdForUsers(array $user_ids): int
    {
        $qb = $this->createQueryBuilder('h');
        $qb->select('MAX(h.id)');
        $qb->where($qb->expr()->in('h.user', $user_ids));
        $qb->groupBy('h.user');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function deleteAll(): void
    {
        $qb = $this->createQueryBuilder('h');
        $qb->delete();

        $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $ids
     */
    public function deleteElements(array $ids = []): void
    {
        $qb = $this->createQueryBuilder('h');
        $qb->delete();
        $qb->where($qb->expr()->in('h.image', $ids));

        $qb->getQuery()->getResult();
    }

    /**
     * @return array<array{History, max_id: int, nb_pages: int}>
     */
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

    public function setSummarizedForUnsummarized(int $max_id): void
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
