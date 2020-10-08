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

use App\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function addOrUpdateAlbum(Album $album): int
    {
        $this->_em->persist($album);
        $this->_em->flush();

        return $album->getId();
    }

    public function updateAlbumRepresentative(int $id, int $representative_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->update();
        $qb->set('a.representative_picture_id', ':representative_id');
        $qb->setParameter('representative_id', $representative_id);
        $qb->where('a.id = :id');
        $qb->setParameter('id', $id);

        $qb->getQuery()->getResult();
    }

    public function updateAlbums(array $fields, array $ids)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->update();
        foreach ($fields as $field => $value) {
            $qb->set('a.' . $field, ':value');
            $qb->setParameter('value', $value);
        }

        $qb->where($qb->expr()->in('a.id', $ids));

        $qb->getQuery()->getResult();
    }

    public function findWithSite(int $id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.site', 's');
        $qb->where('a.id = :id');
        $qb->setParameter('id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findPhysicalAlbums(array $ids = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.site', 's');
        if (count($ids) > 0) {
            $qb->where($qb->expr()->in('a.id', $ids));
        }
        $qb->andWhere($qb->expr()->isNotNull('a.dir'));

        return $qb->getQuery()->getResult();
    }

    public function findVirtualAlbums()
    {
        $qb = $this->createQueryBuilder('a');
        $qb->andWhere($qb->expr()->isNull('a.dir'));

        return $qb->getQuery()->getResult();
    }

    public function countByType(bool $virtual = false): int
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('count(1)');
        if ($virtual) {
            $qb->where($qb->expr()->isNull('a.dir'));
        } else {
            $qb->where($qb->expr()->isNotNull('a.dir'));
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns all sub-album identifiers of given category ids
     */
    public function getSubcatIds(array $ids): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('DISTINCT(a.id) AS id');
        foreach ($ids as $id) {
            $qb->orWhere($qb->expr()->like('a.uppercats', ':comma_before'));
            $qb->setParameter('comma_before', '%,' . $id);

            $qb->orWhere($qb->expr()->like('a.uppercats', ':two_comma'));
            $qb->setParameter('two_comma', '%,' . $id . ',%');

            $qb->orWhere($qb->expr()->like('a.uppercats', ':comma_after'));
            $qb->setParameter('comma_after', $id . ',%');

            $qb->orWhere('a.uppercats = :id');
            $qb->setParameter('id', $id);
        }

        $subalbums = [];
        foreach ($qb->getQuery()->getResult() as $album) {
            $subalbums[] = $album['id'];
        }

        return $subalbums;
    }

    public function findByIdsAndStatus(array $ids, string $status = Album::STATUS_PUBLIC)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where('a.status = :status');
        $qb->setParameter('status', $status);
        $qb->andWhere($qb->expr()->in('a.id', $ids));

        return $qb->getQuery()->getResult();
    }

    public function findAllowedAlbums(array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('a');

        if (count($forbidden_categories) > 0) {
            $qb->where($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllowedSubAlbums(string $uppercats, array $forbidden_categories = []): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->like('a.uppercats', ':uppercats'));
        $qb->setParameter('uppercats', $uppercats . ',%');

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        $subalbums = [];
        foreach ($qb->getQuery()->getResult() as $album) {
            $subalbums[] = $album['id'];
        }

        return $subalbums;
    }

    public function findUnauthorized(array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where('a.status = :status');
        $qb->setParameter('status', Album::STATUS_PRIVATE);

        if (count($album_ids) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $album_ids));
        }

        return $qb->getQuery()->getResult();
    }

    public function findParentAlbumsByUser(int $user_id, string $order_by)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbum', 'uc');
        $qb->where('uc.user = :id');
        $qb->setParameter('id', $user_id);
        $qb->andWhere($qb->expr()->isNotNull('a.id_uppercat'));
        $qb->orderBy('a.' . $order_by);

        return $qb->getQuery()->getResult();
    }

    public function findRandomRepresentantAmongSubAlbums(string $uppercats)
    {
        // avoid rand() in sql query
        $qb = $this->createQueryBuilder('a');
        $qb->select('count(1)');
        $qb->where($qb->expr()->like('a.uppercats', ':uppercats'));
        $qb->setParameter('uppercats', $uppercats . ',%');
        $qb->andWhere($qb->expr()->isNotNull('a.representative_picture_id'));

        $nb_albums = $qb->getQuery()->getSingleScalarResult();

        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->like('a.uppercats', ':uppercats'));
        $qb->setParameter('uppercats', $uppercats . ',%');
        $qb->andWhere($qb->expr()->isNotNull('a.representative_picture_id'));
        $qb->setFirstResult(random_int(0, $nb_albums));
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function deleteAlbums(array $ids)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->delete();
        $qb->where($qb->expr()->in('a.id', $ids));

        $qb->getQuery()->getResult();
    }
}
