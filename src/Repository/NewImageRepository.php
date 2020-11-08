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

use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NewImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function addOrUpdateImage(Image $image): int
    {
        $this->_em->persist($image);
        $this->_em->flush();

        return $image->getId();
    }

    public function findWithNoStorageOrStorageForAlbums(array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where($qb->expr()->isNull('i.storage_category'));

        if (count($album_ids) > 0) {
            $qb->orWhere($qb->expr()->notIn('i.storage_category', $album_ids));
        }

        return $qb->getQuery()->getResult();
    }
}
