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

use App\Entity\Config;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Config>
 */
class ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function addOrUpdateConfig(Config $config): void
    {
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();
    }

    public function addParam(Config $config): void
    {
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Config[]
     */
    public function findMatching(string $expression)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where($qb->expr()->like('c.param', ':expression'));
        $qb->setParameter('expression', $expression);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $params
     */
    public function delete(array $params = []): void
    {
        $entities = $this->findBy(['param' => array_values($params)]);
        foreach ($entities as $entity) {
            $this->getEntityManager()->remove($entity);
        }

        if (count($entities) > 0) {
            $this->getEntityManager()->flush();
        }
    }
}
