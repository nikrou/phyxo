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

use App\Entity\ImageAlbum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImageAlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageAlbum::class);
    }

    public function countTotalImages(array $forbidden_categories = [], string $access_type, array $image_ids = []) : int
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->select('count(distinct(ia.image))');

        if (count($forbidden_categories) > 0) {
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if (count($image_ids) > 0) {
            if ($access_type === 'NOT IN') {
                $qb->andWhere($qb->expr()->notIn('ia.image', $image_ids));
            } else {
                $qb->andWhere($qb->expr()->in('ia.image', $image_ids));
            }
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findRandomRepresentant(int $album_id): int
    {
        // avoid rand() in sql query
        $qb = $this->createQueryBuilder('ia');
        $qb->select('count(1)');
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        $nb_images = $qb->getQuery()->getSingleScalarResult();

        if ($nb_images > 0) {
            $qb = $this->createQueryBuilder('ia');
            $qb->select('IDENTITY(ia.image)');
            $qb->where('ia.album = :album_id');
            $qb->setParameter('album_id', $album_id);
            $qb->setFirstResult(random_int(0, $nb_images - 1));
            $qb->setMaxResults(1);

            return $qb->getQuery()->getSingleScalarResult();
        }

        return 0;
    }

    public function dateOfAlbums(array $album_ids)
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->select('MAX(i.date_creation) AS _to, MIN(i.date_creation) AS _from');
        $qb->leftJoin('ia.image', 'i');
        $qb->where($qb->expr()->in('ia.album', $album_ids));
        $qb->groupBy('ia.album');

        return $qb->getQuery()->getResult();
    }

    public function countImagesByAlbum(): array
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->addSelect('IDENTITY(ia.album) AS album, COUNT(1) AS counter');
        $qb->groupBy('ia.album');

        $results = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $results[$row['album']] = $row['counter'];
        }

        return $results;
    }
}
