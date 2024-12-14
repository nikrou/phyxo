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

use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    use MaxLastModifiedTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function addOrUpdateGroup(Group $group): int
    {
        $this->getEntityManager()->persist($group);
        $this->getEntityManager()->flush();

        return $group->getId();
    }

    public function isGroupNameExists(string $name) : bool
    {
        $qb = $this->createQueryBuilder('g');
        $qb->select('count(1)');
        $qb->where('g.name = :name');
        $qb->setParameter('name', $name);

        return (int) $qb->getQuery()->getSingleScalarResult() === 1;
    }

    /**
     * @return Group[]
     */
    public function findUsersInGroups()
    {
        $qb = $this->createQueryBuilder('g');
        $qb->leftJoin('g.users', 'u');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Group[]
     */
    public function findDefaultGroups()
    {
        $qb = $this->createQueryBuilder('g');
        $qb->where('g.is_default = true');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $group_ids
     */
    public function deleteByGroupIds(array $group_ids): void
    {
        $qb = $this->createQueryBuilder('g');
        $qb->where($qb->expr()->in('g.id', $group_ids));

        foreach ($qb->getQuery()->getResult() as $group) {
            foreach ($group->getGroupAccess() as $access) {
                $group->removeGroupAccess($access);
            }
            foreach ($group->getUsers() as $user) {
                $group->removeUser($user);
            }
            $this->getEntityManager()->remove($group);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @param int[] $group_ids
     */
    public function toggleIsDefault(array $group_ids): void
    {
        $qb = $this->createQueryBuilder('g');
        $qb->update();
        $qb->set('g.is_default', 'CASE WHEN g.is_default = true THEN false ELSE true END');
        $qb->where($qb->expr()->in('g.id', $group_ids));

        $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $group_ids
     *
     * @return Group[]
     */
    public function findByNameOrGroupIds(?string $name = null, array $group_ids = [], ?string $order = null, ?int $limit = null, int $offset = 0)
    {
        $qb = $this->createQueryBuilder('g');
        $qb->leftJoin('g.users', 'u'); // @TODO : no lazy because of count

        if (!is_null($name)) {
            $qb->where($qb->expr()->eq("lower('g.name')", ':name'));
            $qb->setParameter('name', $name);
        }

        if ($group_ids !== []) {
            $qb->andWhere($qb->expr()->in('g.id', $group_ids));
        }

        if (!is_null($order)) {
            if (!preg_match('`^g\.`', $order)) {
                $order = 'g.' . $order;
            }
            $qb->orderBy($order);
        }

        $qb->setFirstResult($offset);

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     *
     * @return Group[]
     */
    public function findWithAlbumsAccess(array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('g');
        $qb->leftJoin('g.group_access', 'ga');

        if ($album_ids !== []) {
            $qb->where($qb->expr()->in('ga.cat_id', $album_ids));
        }

        return $qb->getQuery()->getResult();
    }
}
