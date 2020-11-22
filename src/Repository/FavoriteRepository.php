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

use App\Entity\Favorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function addOrUpdateFavorite(Favorite $favorite)
    {
        $this->_em->persist($favorite);
        $this->_em->flush();
    }

    public function findUserFavorites(int $user_id, array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        if (count($forbidden_categories) > 0) {
            $qb->leftJoin('f.image', 'i');
            $qb->leftJoin('i.imageAlbums', 'ia');
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function isFavorite(int $user_id, int $image_id) : bool
    {
        $qb = $this->createQueryBuilder('f');
        $qb->select('COUNT(1)');
        $qb->where('f.user = :user_id');
        $qb->setParameter('user_id', $user_id);
        $qb->andWhere('f.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        return (int) $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function deleteUserFavorite(int $user_id, int $image_id)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->delete();
        $qb->where('f.user = :user_id');
        $qb->setParameter('user_id', $user_id);
        $qb->andWhere('f.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        $qb->getQuery()->getResult();
    }

    public function deleteAllUserFavorites(int $user_id)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->delete();
        $qb->where('f.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }

    public function deleteImagesFromFavorite(array $image_ids)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->delete();
        $qb->where($qb->expr()->in('f.image', $image_ids));

        $qb->getQuery()->getResult();
    }
}
