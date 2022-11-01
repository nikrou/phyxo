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

/**
 * @extends ServiceEntityRepository<Album>
 */
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

    public function updateAlbumRepresentative(int $id, int $representative_id): void
    {
        $qb = $this->createQueryBuilder('a');
        $qb->update();
        $qb->set('a.representative_picture_id', ':representative_id');
        $qb->setParameter('representative_id', $representative_id);
        $qb->where('a.id = :id');
        $qb->setParameter('id', $id);

        $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, string|bool|int|null> $fields
     * @param int[] $ids
     */
    public function updateAlbums(array $fields, array $ids): void
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

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function getAlbumsForMenu(int $user_id, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'ia', Expr\Join::WITH, 'ia.user = :user_id');
        $qb->addSelect('ia');
        $qb->setParameter('user_id', $user_id);

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $ids
     *
     * @return Album[]
     */
    public function findPhysicalAlbums(array $ids = [])
    {
        $qb = $this->createQueryBuilder('a');
        if (count($ids) > 0) {
            $qb->where($qb->expr()->in('a.id', $ids));
        }
        $qb->andWhere($qb->expr()->isNotNull('a.dir'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Album[]
     */
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

    protected function addCriteriaSubAlbum(QueryBuilder $qb, string $crieria): QueryBuilder
    {
        $qb->orWhere($qb->expr()->like('a.uppercats', ':comma_before'));
        $qb->setParameter('comma_before', '%,' . $crieria);

        $qb->orWhere($qb->expr()->like('a.uppercats', ':two_comma'));
        $qb->setParameter('two_comma', '%,' . $crieria . ',%');

        $qb->orWhere($qb->expr()->like('a.uppercats', ':comma_after'));
        $qb->setParameter('comma_after', $crieria . ',%');

        $qb->orWhere('a.uppercats = :id');
        $qb->setParameter('id', $crieria);

        return $qb;
    }

    /**
     * Returns all sub-album of given album ids
     *
     * @param int[] $ids
     *
     * @return Album[]
     */
    public function getSubAlbums(array $ids, bool $only_id = false)
    {
        $qb = $this->createQueryBuilder('a');
        if ($only_id) {
            $qb->select('DISTINCT(a.id) AS id');
        }

        foreach ($ids as $id) {
            $this->addCriteriaSubAlbum($qb, (string) $id);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all sub-album identifiers of given album ids
     *
     * @param int[] $ids
     *
     * @return int[]
     */
    public function getSubcatIds(array $ids)
    {
        $subalbums = [];
        foreach ($this->getSubAlbums($ids, $only_id = true) as $album) {
            $subalbums[] = $album['id'];
        }

        return $subalbums;
    }

    /**
     * @param int[] $ids
     *
     * @return Album[]
     */
    public function findByIdsAndStatus(array $ids, string $status = Album::STATUS_PUBLIC)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where('a.status = :status');
        $qb->setParameter('status', $status);
        $qb->andWhere($qb->expr()->in('a.id', $ids));

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Album[]
     */
    public function findAlbumsForImage(int $image_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     */
    public function getQueryBuilderForFindAllowedAlbums(array $forbidden_albums = [], QueryBuilder $qb = null): QueryBuilder
    {
        $method = 'andWhere';

        if (is_null($qb)) {
            $qb = $this->createQueryBuilder('a');
            $method = 'where';
        }

        if (count($forbidden_albums) > 0) {
            $qb->$method($qb->expr()->notIn('a.id', $forbidden_albums));
        }

        return $qb;
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findAllowedAlbums(array $forbidden_albums = [])
    {
        return $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums)->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return int[]
     */
    public function findAllowedSubAlbums(string $uppercats, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->like('a.uppercats', ':uppercats'));
        $qb->setParameter('uppercats', $uppercats . ',%');

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        $subalbums = [];
        foreach ($qb->getQuery()->getResult() as $album) {
            $subalbums[] = $album['id'];
        }

        return $subalbums;
    }

    /**
     * @param int[] $album_ids
     *
     * @return Album[]
     */
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

    /**
     * @return Album[]
     */
    public function findByParentId(int $user_id, ?int $parent_id = null)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'ia', Expr\Join::WITH, 'ia.user = :user');
        $qb->setParameter('user', $user_id);

        $qb->where('a.parent = :parent');
        $qb->setParameter('parent', $parent_id);

        return $qb->getQuery()->getResult();
    }

    public function findRandomRepresentantAmongSubAlbums(string $uppercats): ?Album
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
     * @param int[] $ids
     */
    public function deleteAlbums(array $ids): void
    {
        $qb = $this->createQueryBuilder('a');
        $qb->delete();
        $qb->where($qb->expr()->in('a.id', $ids));

        $qb->getQuery()->getResult();
    }

    /**
     * @return Album[]
     */
    public function findAuthorizedToUser(int $user_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.user_access', 'ua');
        $qb->where('ua.id = :user_id');
        $qb->setParameter('user_id', $user_id);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $exclude_album_ids
     *
     * @return Album[]
     */
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

    /**
     * @return Album[]
     */
    public function findAuthorizedToTheGroupTheUserBelongs(int $user_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.group_access', 'ga');
        $qb->leftJoin('ga.users', 'u');
        $qb->where('u.id = :user_id');
        $qb->setParameter('user_id', $user_id);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Album[]
     */
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

    /**
     * @param int[] $forbidden_albums
     *
     * @return array<int, array{album_id: int, id_uppercat: ?int, date_last: string, nb_images: int}>
     */
    public function getComputedAlbums(int $level, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('a.id AS album_id, IDENTITY(a.parent) AS id_uppercat, MAX(i.date_available) as date_last, COUNT(i.id) AS nb_images');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->leftJoin('ia.image', 'i', Expr\Join::WITH, 'i.level <= :level');
        $qb->setParameter('level', $level);

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        $qb->groupBy('a.id');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Album[]
     */
    public function findParentAlbums(int $user_id)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'uca', Expr\Join::WITH, 'uca.user = :user');
        $qb->setParameter('user', $user_id);
        $qb->where($qb->expr()->isNull('a.parent'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $ids
     *
     * @return Album[]
     */
    public function findRepresentants(array $ids)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->in('a.representative_picture_id', $ids));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findRelative(int $image_id, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Album[]
     */
    public function findRecentAlbums(?DateTimeInterface $recent_date, ?DateTimeInterface $last_photo_date = null)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->leftJoin('a.userCacheAlbums', 'ia');
        $qb->where('ia.date_last >= :recent_date');
        $qb->setParameter('recent_date', $recent_date);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findNoParentsAuthorizedAlbums(int $user_id, array $forbidden_albums = [], bool $public_and_visible = false)
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

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        $qb->andWhere($qb->expr()->isNull('a.parent'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findAuthorizedAlbumsForAlbums(array $album_ids, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('a');

        foreach ($album_ids as $id) {
            $this->addCriteriaSubAlbum($qb, (string) $id);
        }

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findAuthorizedAlbumsAndParents(int $user_id, int $album_id, array $forbidden_albums = [], bool $public_and_visible = false)
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

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        $qb->andWhere('a.parent = :album_id');
        $qb->orWhere('a.id = :album_id');
        $qb->setParameter('album_id', $album_id);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findAuthorizedAlbumsInSubAlbumsForAlbums(array $album_ids, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('a');

        $qb->where($qb->expr()->in('a.id', $album_ids));

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findAuthorizedAlbumsInSubAlbums(int $user_id, int $album_id, array $forbidden_albums = [], bool $public_and_visible = false)
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

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        $this->addCriteriaSubAlbum($qb, (string) $album_id);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]
     */
    public function findAuthorizedAlbums(int $user_id, array $forbidden_albums = [], bool $public_and_visible = false)
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

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     *
     * @return array<int, int|string>
     */
    public function findWrongRepresentant(array $album_ids = [])
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

    /**
     * @param int[] $album_ids
     *
     * @return array<int>
     */
    public function findNeedeedRandomRepresentant(array $album_ids = [])
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

    /**
     * @param int[] $forbidden_albums
     */
    public function hasAccessToImage(int $image_id, array $forbidden_albums = []) : bool
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('COUNT(1)');
        $qb->leftJoin('a.imageAlbums', 'ia');
        $qb->where('ia.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        $this->getQueryBuilderForFindAllowedAlbums($forbidden_albums, $qb);

        return (int) $qb->getQuery()->getSingleScalarResult() === 1;
    }

    /**
     * @return array{max: \DateTimeInterface, count: int}
     */
    public function getMaxLastModified()
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('MAX(a.last_modified) as max, COUNT(1) as count');

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }
}
