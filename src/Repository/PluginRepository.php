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

use App\Entity\Plugin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PluginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plugin::class);
    }

    public function findAllByState(string $state = '')
    {
        $qb = $this->createQueryBuilder('p');

        if ($state !== '') {
            $qb->where('state', ':state');
            $qb->setParameter('state', $state);
        }

        return $qb->getQuery()->getResult();
    }

    public function addPlugin(Plugin $plugin)
    {
        $this->_em->persist($plugin);
        $this->_em->flush();
    }

    public function updateVersion(string $plugin_id, string $version)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->update();
        $qb->set('version', ':version');
        $qb->where('id = :id');
        $qb->setParameter('id', $plugin_id);
        $qb->setParameter('version', $version);

        return $qb->getQuery()->getResult();
    }

    public function updateState(string $plugin_id, string $state)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->update();
        $qb->set('state', ':state');
        $qb->where('id = :id');
        $qb->setParameter('id', $plugin_id);
        $qb->setParameter('state', $state);

        return $qb->getQuery()->getResult();
    }

    public function deactivateNonStandardPlugins()
    {
        $qb = $this->createQueryBuilder('p');
        $qb->update();
        $qb->set('state', Plugin::INACTIVE);
        $qb->where('state = :state');
        $qb->setParameter('state', Plugin::ACTIVE);

        return $qb->getQuery()->getResult();
    }

    public function deleteById(string $plugin_id)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->where('id', ':id');
        $qb->setParameter('id', $plugin_id);
        $qb->delete();

        return $qb->getQuery()->getResult();
    }
}
