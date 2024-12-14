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

use App\Entity\Caddie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Caddie>
 */
class CaddieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Caddie::class);
    }

    public function addOrUpdateCaddie(Caddie $caddie): void
    {
        $this->getEntityManager()->persist($caddie);
        $this->getEntityManager()->flush();
    }

    public function emptyCaddies(int $user_id): void
    {
        $qb = $this->createQueryBuilder('c');
        $qb->delete();
        $qb->where('c.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $image_ids
     */
    public function deleteElements(array $image_ids, ?int $user_id = null): void
    {
        $qb = $this->createQueryBuilder('c');
        $qb->delete();
        $qb->where($qb->expr()->in('c.image', $image_ids));

        if (!is_null($user_id)) {
            $qb->andWhere('c.user = :user_id');
            $qb->setParameter('user_id', $user_id);
        }

        $qb->getQuery()->getResult();
    }
}
