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
use Doctrine\Common\Persistence\ManagerRegistry;

class ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function addOrUpdate(Config $config): void
    {
        $_config = $this->find($config->getParam());
        $_config = $this->_em->merge($config);
        $this->_em->persist($_config);
        $this->_em->flush($_config);
    }

    public function addParam(Config $config)
    {
        $this->_em->persist($config);
        $this->_em->flush($config);
    }

    public function findMatching(string $expression): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where($qb->expr()->like('c.param', ':expression'));
        $qb->setParameter('expression', $expression);

        return $qb->getQuery()->getResult();
    }

    public function delete(array $params = []): void
    {
        $this->findAll(['params' => $params])->remove();
    }
}
