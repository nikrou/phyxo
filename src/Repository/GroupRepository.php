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
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function addOrUpdateGroup(Group $group): int
    {
        $this->_em->persist($group);
        $this->_em->flush();

        return $group->getId();
    }

    public function getMaxLastModified()
    {
        $qb = $this->createQueryBuilder('g');
        $qb->select('MAX(g.last_modified) as max, COUNT(1) as count');

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function isGroupNameExists(string $name) : bool
    {
        $qb = $this->createQueryBuilder('g');
        $qb->select('count(1)');
        $qb->where('g.name = :name');
        $qb->setParameter('name', $name);

        return (int) $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function findUsersInGroups()
    {
        $qb = $this->createQueryBuilder('g');
        $qb->leftJoin('g.users', 'u');

        return $qb->getQuery()->getResult();
    }

    public function findDefaultGroups()
    {
        $qb = $this->createQueryBuilder('g');
        $qb->where('g.is_default = true');

        return $qb->getQuery()->getResult();
    }

    public function deleteByGroupIds(array $group_ids)
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
            $this->_em->remove($group);
        }
        $this->_em->flush();
    }

    public function toggleIsDefault(array $group_ids)
    {
        $qb = $this->createQueryBuilder('g');
        $qb->update();
        $qb->set('g.is_default', 'CASE WHEN g.is_default = true THEN false ELSE true END');
        $qb->where($qb->expr()->in('g.id', $group_ids));

        $qb->getQuery()->getResult();
    }

    public function findByNameOrGroupIds(?string $name = null, array $group_ids = [], string $order, int $limit, int $offset = 0)
    {
        $qb = $this->createQueryBuilder('g');
        $qb->leftJoin('g.users', 'u'); // @TODO : no lazy because of count

        if (!is_null($name)) {
            $qb->where($qb->expr()->lower('g.name'), ':name');
            $qb->setParameter('name', $name);
        }

        if (count($group_ids) > 0) {
            $qb->andWhere($qb->expr()->in('g.id', $group_ids));
        }

        if (!preg_match('`^g\.`', $order)) {
            $order = 'g.' . $order;
        }
        $qb->orderBy($order);
        $qb->setFirstResult($offset);
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function findWithAlbumsAccess(array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('g');
        $qb->leftJoin('g.group_access', 'ga');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ga.cat_id', $album_ids));
        }

        return $qb->getQuery()->getResult();
    }
}
