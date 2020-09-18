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

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function addUser(User $user): int
    {
        $this->_em->persist($user);
        $this->_em->flush();

        return $user->getId();
    }

    public function updateUser(User $user): void
    {
        $this->_em->persist($user);
        $this->_em->flush();
    }

    public function findOneByStatus(string $status): ?User
    {
        $qb = $this->createQueryBuilder('u');
        $qb->leftJoin('u.userInfos', 'ui');
        $qb->where('ui.status = :status');
        $qb->setParameter('status', $status);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getList(): array
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('u', 'ui', 'g');
        $qb->leftJoin('u.userInfos', 'ui');
        $qb->leftJoin('u.groups', 'g');

        return $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function isEmailExistsExceptUser(string $mail_address, int $user_id) : bool
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('count(1)');
        $qb->where('u.mail_address = :mail_address');
        $qb->andWhere('u.id != :id');
        $qb->setParameter('mail_address', $mail_address);
        $qb->setParameter('id', $user_id);

        return $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function isUsernameExistsExceptUser(string $username, int $user_id) : bool
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('count(1)');
        $qb->where('u.username = :username');
        $qb->andWhere('u.id != :id');
        $qb->setParameter('username', $username);
        $qb->setParameter('id', $user_id);

        return $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function isUserExists(string $value, string $field = 'username') : bool
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('count(1)');
        $qb->where('u.' . $field . ' = :value');
        $qb->setParameter('value', $value);

        return $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function findUserByUsernameOrEmail(string $value): ?User
    {
        $qb = $this->createQueryBuilder('u');
        $qb->where('u.username = :username');
        $qb->orWhere('u.mail_address = :mail_address');
        $qb->setParameters(['username' => $value, 'mail_address' => $value]);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function deleteById(int $user_id): void
    {
        $qb = $this->createQueryBuilder('u');
        $qb->delete();
        $qb->where('u.id = :id');
        $qb->setParameter('id', $user_id);

        $qb->getQuery()->getResult();
    }
}
