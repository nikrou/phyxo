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

use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    public function addSite(Site $site)
    {
        $this->_em->persist($site);
        $this->_em->flush();
    }

    public function isSiteExists(string $url): bool
    {
        $qb = $this->createQueryBuilder('s');
        $qb->select('count(s.id)');
        $qb->where('galleries_url', ':url');
        $qb->setParameter('url', $url);

        return (int) $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function deleteById(int $id)
    {
        $qb = $this->createQueryBuilder('l');
        $qb->where('id', ':id');
        $qb->setParameter('id', $id);
        $qb->delete();

        return $qb->getQuery()->getResult();
    }
}
