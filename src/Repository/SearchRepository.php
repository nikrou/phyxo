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

class SearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Search::class);
    }

    public function findByRules(string $rules): ?Search
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('rules', ':rules');
        $qb->setParameter('rules', $rules);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function updateLastSeen(int $id)
    {
        $qb = $this->createQueryBuilder('s');
        $qb->update();
        $qb->set('last_seen', ':last_seen');
        $qb->setParameter('last_seen', new \DateTime());
        $qb->where('id', ':id');
        $qb->setParameter('id', $id);

        return $qb->getQuery()->getResult();
    }

    public function addSearch(Search $search)
    {
        $this->_em->persist($search);
        $this->_em->flush();
    }

    public function purge()
    {
        $qb = $this->createQueryBuilder('s');
        $qb->delete();

        return $qb->getQuery()->getResult();
    }
}
