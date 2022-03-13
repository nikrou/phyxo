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

use App\Entity\Search;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Search>
 */
class SearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Search::class);
    }

    public function updateLastSeen(int $id): void
    {
        $qb = $this->createQueryBuilder('s');
        $qb->update();
        $qb->set('s.last_seen', ':last_seen');
        $qb->setParameter('last_seen', new \DateTime());
        $qb->where('s.id = :id');
        $qb->setParameter('id', $id);

        $qb->getQuery()->getResult();
    }

    public function addSearch(Search $search): void
    {
        $this->_em->persist($search);
        $this->_em->flush();
    }

    public function purge(): void
    {
        $qb = $this->createQueryBuilder('s');
        $qb->delete();

        $qb->getQuery()->getResult();
    }
}
