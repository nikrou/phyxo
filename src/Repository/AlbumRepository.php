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
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
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

    public function getAlbumsForMenu(int $user_id, array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'ia', Expr\Join::WITH, 'ia.user = :user_id');
        $qb->addSelect('ia');
        $qb->setParameter('user_id', $user_id);

        if (count($forbidden_categories) > 0) {
            $qb->where($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
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

    protected function addCriteriaSubAlbum(QueryBuilder $qb, int $id): QueryBuilder
    {
        $qb->orWhere($qb->expr()->like('a.uppercats', ':comma_before'));
        $qb->setParameter('comma_before', '%,' . $id);

        $qb->orWhere($qb->expr()->like('a.uppercats', ':two_comma'));
        $qb->setParameter('two_comma', '%,' . $id . ',%');

        $qb->orWhere($qb->expr()->like('a.uppercats', ':comma_after'));
        $qb->setParameter('comma_after', $id . ',%');

        $qb->orWhere('a.uppercats = :id');
        $qb->setParameter('id', $id);

        return $qb;
    }

    /**
     * Returns all sub-album of given album ids
     */
    public function getSubAlbums(array $ids, bool $only_id = false)
    {
        $qb = $this->createQueryBuilder('a');
        if ($only_id) {
            $qb->select('DISTINCT(a.id) AS id');
        }

        foreach ($ids as $id) {
            $this->addCriteriaSubAlbum($qb, $id);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all sub-album identifiers of given album ids
     */
    public function getSubcatIds(array $ids): array
    {
        $subalbums = [];
        foreach ($this->getSubAlbums($ids, $only_id = true) as $album) {
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

    public function findAlbumsForImage(int $image_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);

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

    public function findByParentId(?int $parent_id = null, int $user_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'ia', Expr\Join::WITH, 'ia.user = :user');
        $qb->setParameter('user', $user_id);

        $qb->where('a.parent = :parent');
        $qb->setParameter('parent', $parent_id);

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

    /**
     * Find a random photo among all photos inside an album (including sub-albums)
     */
    public function getRandomImageInAlbum(int $album_id, string $uppercats = '', array $forbidden_categories, bool $recursive = true)
    {
        // avoid rand() in sql query
        $qb = $this->createQueryBuilder('a');
        $qb->select('count(1)');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->leftJoin('ia.image', 'i');
        $qb->where('a.id = :album_id');
        $qb->setParameter('album_id', $album_id);

        if ($recursive) {
            $qb->orWhere($qb->expr()->like('a.uppercats', ':uppercats'));
            $qb->setParameter('uppercats', $uppercats . ',%');
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }
        $nb_images = $qb->getQuery()->getSingleScalarResult();

        if ($nb_images > 0) {
            $qb = $this->createQueryBuilder('a');
            $qb->leftJoin('a.imageAlbums', 'ia');
            $qb->leftJoin('ia.image', 'i');
            $qb->where('a.id = :album_id');
            $qb->setParameter('album_id', $album_id);

            if ($recursive) {
                $qb->orWhere($qb->expr()->like('a.uppercats', ':uppercats'));
                $qb->setParameter('uppercats', $uppercats . ',%');
            }

            if (count($forbidden_categories) > 0) {
                $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
            }
            $qb->setFirstResult(random_int(0, $nb_images - 1));
            $qb->setMaxResults(1);

            return $qb->getQuery()->getOneOrNullResult();
        }

        return null;
    }

    public function deleteAlbums(array $ids)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->delete();
        $qb->where($qb->expr()->in('a.id', $ids));

        $qb->getQuery()->getResult();
    }

    public function findAuthorizedToUser(int $user_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.user_access', 'ua');
        $qb->where('ua.id = :user_id');
        $qb->setParameter('user_id', $user_id);

        return $qb->getQuery()->getResult();
    }

    public function findPrivateWithUserAccessAndNotExclude(int $user_id, array $exclude_album_ids = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.user_access', 'ua');
        $qb->where('ua.id = :user_id');
        $qb->setParameter('user_id', $user_id);
        $qb->andWhere('a.status = :status');
        $qb->setParameter('status', Album::STATUS_PRIVATE);

        if (count($exclude_album_ids) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $exclude_album_ids));
        }

        return $qb->getQuery()->getResult();
    }

    public function findAuthorizedToTheGroupTheUserBelongs(int $user_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.group_access', 'ga');
        $qb->leftJoin('ga.users', 'u');
        $qb->where('u.id = :user_id');
        $qb->setParameter('user_id', $user_id);

        return $qb->getQuery()->getResult();
    }

    public function findPrivateWithGroupAccess(int $group_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.group_access', 'ga');
        $qb->where('ga.id = :group_id');
        $qb->setParameter('group_id', $group_id);
        $qb->andWhere('a.status = :status');
        $qb->setParameter('status', Album::STATUS_PRIVATE);

        return $qb->getQuery()->getResult();
    }

    public function getComputedAlbums(int $level, array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('a.id AS album_id, IDENTITY(a.parent) AS id_uppercat, MAX(i.date_available) as date_last, COUNT(i.id) AS nb_images');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->leftJoin('ia.image', 'i', Expr\Join::WITH, 'i.level <= :level');
        $qb->setParameter('level', $level);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        $qb->groupBy('a.id');

        return $qb->getQuery()->getResult();
    }

    public function findParentAlbums(int $user_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'uca', Expr\Join::WITH, 'uca.user = :user');
        $qb->setParameter('user', $user_id);
        $qb->where($qb->expr()->isNull('a.parent'));

        return $qb->getQuery()->getResult();
    }

    public function findRepresentants(array $ids)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->in('a.representative_picture_id', $ids));

        return $qb->getQuery()->getResult();
    }

    public function findRelative(array $forbidden_categories = [], int $image_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function findRecentAlbums(?DateTimeInterface $recent_date, ?DateTimeInterface $last_photo_date = null)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'ia');
        $qb->where('ia.date_last >= :recent_date');
        $qb->setParameter('recent_date', $recent_date);

        return $qb->getQuery()->getResult();
    }

    public function findNoParentsAuthorizedAlbums(int $user_id, array $forbidden_categories = [], bool $public_and_visible = false)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'uca', Expr\Join::WITH, 'uca.user = :user');
        $qb->setParameter('user', $user_id);

        if ($public_and_visible) {
            $qb->where('a.status = :status');
            $qb->setParameter('status', Album::STATUS_PUBLIC);
            $qb->andWhere('a.visible = :visible');
            $qb->setParameter('visible', true);
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        $qb->andWhere($qb->expr()->isNull('a.parent'));

        return $qb->getQuery()->getResult();
    }

    public function findAuthorizedAlbumsForAlbums(array $album_ids, array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('a');

        foreach ($album_ids as $id) {
            $this->addCriteriaSubAlbum($qb, $id);
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function findAuthorizedAlbumsAndParents(int $user_id, int $album_id, array $forbidden_categories = [], bool $public_and_visible = false)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'uca', Expr\Join::WITH, 'uca.user = :user');
        $qb->setParameter('user', $user_id);

        if ($public_and_visible) {
            $qb->where('a.status = :status');
            $qb->setParameter('status', Album::STATUS_PUBLIC);
            $qb->andWhere('a.visible = :visible');
            $qb->setParameter('visible', true);
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        $qb->andWhere('a.parent = :album_id');
        $qb->orWhere('a.id = :album_id');
        $qb->setParameter('album_id', $album_id);

        return $qb->getQuery()->getResult();
    }

    public function findAuthorizedAlbumsInSubAlbumsForAlbums(array $album_ids, array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('a');

        $qb->where($qb->expr()->in('a.id', $album_ids));

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function findAuthorizedAlbumsInSubAlbums(int $user_id, int $album_id, array $forbidden_categories = [], bool $public_and_visible = false)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'uca', Expr\Join::WITH, 'uca.user = :user');
        $qb->setParameter('user', $user_id);

        if ($public_and_visible) {
            $qb->where('a.status = :status');
            $qb->setParameter('status', Album::STATUS_PUBLIC);
            $qb->andWhere('a.visible = :visible');
            $qb->setParameter('visible', true);
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        $this->addCriteriaSubAlbum($qb, $album_id);

        return $qb->getQuery()->getResult();
    }

    public function findAuthorizedAlbums(int $user_id, array $forbidden_categories = [], bool $public_and_visible = false)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'uca', Expr\Join::WITH, 'uca.user = :user');
        $qb->setParameter('user', $user_id);

        if ($public_and_visible) {
            $qb->where('a.status = :status');
            $qb->setParameter('status', Album::STATUS_PUBLIC);
            $qb->andWhere('a.visible = :visible');
            $qb->setParameter('visible', true);
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function findWrongRepresentant(array $album_ids = []): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('DISTINCT(a.id)');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->leftJoin('ia.image', 'i', Expr\Join::WITH, 'a.representative_picture_id = i.id');
        $qb->where($qb->expr()->isNotNull('a.representative_picture_id'));
        if (count($album_ids) > 0) {
            $qb->andWhere($qb->expr()->in('a.id', $album_ids));
        }
        $qb->andWhere($qb->expr()->isNotNull('i.id'));

        $results = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $results[] = $row[1];
        }

        return $results;
    }

    public function findNeedeedRandomRepresentant(array $album_ids = []): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('DISTINCT(a.id)');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->where($qb->expr()->isNull('a.representative_picture_id'));
        if (count($album_ids) > 0) {
            $qb->andWhere($qb->expr()->in('a.id', $album_ids));
        }
        $qb->andWhere($qb->expr()->isNotNull('ia.image'));

        $results = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $results[] = $row[1];
        }

        return $results;
    }

    public function hasAccessToImage(array $forbidden_categories = [], int $image_id) : bool
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('COUNT(1)');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_categories));
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function getMaxLastModified()
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('MAX(a.last_modified) as max, COUNT(1) as count');

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function findSitesDetail()
    {
        return [];
        // $qb = $this->createQueryBuilder('a');
        // $qb->select('a.site');
        // $qb->addSelect('COUNT(DISTINCT(IDENa.id)) AS nb_albums, COUNT(ia.image) AS nb_images');
        // $qb->leftJoin('a.imageAlbums', 'ia'); //, Expr\Join::WITH, 'a.id = ia.storage_category_id');
        // $qb->where($qb->expr()->isNotNull('a.site_id'));
        // $qb->groupBy('a.site_id');

        // return $qb->getQuery()->getResult();
    }
}
