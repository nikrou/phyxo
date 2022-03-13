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

use App\Entity\UserCacheAlbum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserCacheAlbum>
 */
class UserCacheAlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCacheAlbum::class);
    }

    public function addOrUpdateUserCacheAlbum(UserCacheAlbum $userCacheAlbum): void
    {
        $this->_em->persist($userCacheAlbum);
        $this->_em->flush();
    }

    public function deleteAll(): void
    {
        $qb = $this->createQueryBuilder('ucc');
        $qb->delete();

        $qb->getQuery()->getResult();
    }

    public function deleteForUser(int $user_id): void
    {
        $qb = $this->createQueryBuilder('ucc');
        $qb->delete();
        $qb->where('ucc.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     */
    public function deleteForAlbums(array $album_ids): void
    {
        $qb = $this->createQueryBuilder('ucc');
        $qb->delete();
        $qb->where($qb->expr()->in('ucc.album', $album_ids));

        $qb->getQuery()->getResult();
    }

    public function updateUserRepresentativePicture(int $user_id, int $album_id, int $image_id): void
    {
        $qb = $this->createQueryBuilder('ucc');
        $qb->update();
        $qb->set('ucc.user_representative_picture', $image_id);
        $qb->where('ucc.user = :user_id');
        $qb->setParameter('user_id', $user_id);
        $qb->andWhere('ucc.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        $qb->getQuery()->getResult();
    }

    public function unsetUserRepresentativePictureForAlbum(int $album_id): void
    {
        $qb = $this->createQueryBuilder('ucc');
        $qb->update();
        $qb->set('ucc.user_representative_picture', ':user_representative_picture');
        $qb->setParameter('user_representative_picture', null);
        $qb->where('ucc.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        $qb->getQuery()->getResult();
    }
}
