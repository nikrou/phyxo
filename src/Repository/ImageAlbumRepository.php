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

    public function addOrUpdateImageAlbum(ImageAlbum $image_album)
    {
        $this->_em->persist($image_album);
        $this->_em->flush();
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
        $qb->select('IDENTITY(ia.album) AS album, COUNT(1) AS counter');
        $qb->groupBy('ia.album');

        $results = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $results[$row['album']] = $row['counter'];
        }

        return $results;
    }

    public function isImageAssociatedToAlbum(int $image_id, int $album_id): bool
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->select('COUNT(1)');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);
        $qb->andWhere('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        return $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function maxRankForAlbum(int $album_id) : int
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->select('MAX(ia.rank)');
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findMaxRankForEachAlbums(array $ids): array
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->select('IDENTITY(ia.album) as album, MAX(ia.rank) as max');
        $qb->where($qb->expr()->isNotNull('ia.rank'));
        $qb->andWhere($qb->expr()->in('ia.album', $ids));
        $qb->groupBy('ia.album');

        $results = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $results[$row['album']] = $row['max'];
        }

        return $results;
    }

    public function updateRankForAlbum(int $rank, int $album_id)
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->update();
        $qb->set('ia.rank', 'ia.rank + 1');
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);
        $qb->andWhere($qb->expr()->isNotNull('ia.rank'));
        $qb->andWhere('ia.rank >= :rank');
        $qb->setParameter('rank', $rank);

        $qb->getQuery()->getResult();
    }

    public function updateRankForImage(int $rank, int $image_id, int $album_id)
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->update();
        $qb->set('ia.rank', ':rank');
        $qb->setParameter('rank', $rank);
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);
        $qb->andWhere('ia.image = :image');
        $qb->setParameter('image', $image_id);

        $qb->getQuery()->getResult();
    }

    public function deleteByAlbum(array $ids = [], array $image_ids = [])
    {
        if (count($ids) === 0 && count($image_ids) === 0) {
            return;
        }

        $qb = $this->createQueryBuilder('ia');
        $qb->delete();

        if (count($ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $ids));
        }

        if (count($image_ids) > 0) {
            $qb->andWhere($qb->expr()->in('ia.image', $image_ids));
        }

        $qb->getQuery()->getResult();
    }

    public function deleteByImages(array $ids = [])
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->delete();

        if (count($ids) > 0) {
            $qb->where($qb->expr()->in('ia.image', $ids));
        }

        $qb->getQuery()->getResult();
    }

    public function getAlbumWithLastPhotoAdded()
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->orderBy('ia.image', 'DESC');
        $qb->setMaxResults(1);

        return $qb->getQuery()->getResult();
    }

    public function getRelatedAlbum(int $image_id, array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('ia');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);
        $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));

        return $qb->getQuery()->getResult();
    }
}
