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

/**
 * @extends ServiceEntityRepository<Plugin>
 */
class PluginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plugin::class);
    }

    /**
     * @return Plugin[]
     */
    public function findAllByState(string $state = '')
    {
        $qb = $this->createQueryBuilder('p');

        if ($state !== '') {
            $qb->where('p.state = :state');
            $qb->setParameter('state', $state);
        }

        return $qb->getQuery()->getResult();
    }

    public function addPlugin(Plugin $plugin): void
    {
        $this->getEntityManager()->persist($plugin);
        $this->getEntityManager()->flush();
    }

    public function updateVersion(string $plugin_id, string $version): void
    {
        $qb = $this->createQueryBuilder('p');
        $qb->update();
        $qb->set('p.version', ':version');
        $qb->where('p.id = :id');
        $qb->setParameter('id', $plugin_id);
        $qb->setParameter('version', $version);

        $qb->getQuery()->getResult();
    }

    public function updateState(string $plugin_id, string $state): void
    {
        $qb = $this->createQueryBuilder('p');
        $qb->update();
        $qb->set('p.state', ':state');
        $qb->where('p.id = :id');
        $qb->setParameter('id', $plugin_id);
        $qb->setParameter('state', $state);

        $qb->getQuery()->getResult();
    }

    public function deactivateNonStandardPlugins(): void
    {
        $qb = $this->createQueryBuilder('p');
        $qb->update();
        $qb->set('p.state', ':state_inactive');
        $qb->setParameter('state_inactive', Plugin::INACTIVE);
        $qb->where('p.state = :state_active');
        $qb->setParameter('state_active', Plugin::ACTIVE);

        $qb->getQuery()->getResult();
    }

    public function deleteById(string $plugin_id): void
    {
        $qb = $this->createQueryBuilder('p');
        $qb->where('p.id = :id');
        $qb->setParameter('id', $plugin_id);
        $qb->delete();

        $qb->getQuery()->getResult();
    }
}
