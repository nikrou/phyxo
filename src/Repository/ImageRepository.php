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

use DateTimeInterface;
use DateTime;
use DateInterval;
use App\Entity\Album;
use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    use MaxLastModifiedTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function addOrUpdateImage(Image $image): int
    {
        $this->getEntityManager()->persist($image);
        $this->getEntityManager()->flush();

        return $image->getId();
    }

    // deal with ./ at the beginning of path
    public function findOneByUnsanePath(string $path): ?Image
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.path = :path');
        $qb->orWhere('i.path = :path_with_slash');
        $qb->setParameter('path', $path);
        $qb->setParameter('path_with_slash', './' . $path);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int[] $ids
     */
    public function updateFieldForImages(array $ids, string $field, string|DateTimeInterface|null $value): void
    {
        $qb = $this->createQueryBuilder('i');
        $qb->update();
        $qb->set('i.' . $field, ':value');
        $qb->setParameter('value', $value);
        $qb->where($qb->expr()->in('i.id', $ids));

        $qb->getQuery()->getResult();
    }

    public function updateLevel(int $image_id, int $level = 0): void
    {
        $qb = $this->createQueryBuilder('i');
        $qb->update();
        $qb->set('i.level', ':level');
        $qb->setParameter('level', $level);
        $qb->where('i.id = :image_id');
        $qb->setParameter('image_id', $image_id);

        $qb->getQuery()->getResult();
    }

    public function updateRatingScore(int $image_id, ?float $rating_score): void
    {
        $qb = $this->createQueryBuilder('i');
        $qb->update();
        $qb->set('i.rating_score', ':rating_score');
        $qb->setParameter('rating_score', $rating_score);
        $qb->where('i.id = :image_id');
        $qb->setParameter('image_id', $image_id);

        $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     *
     * @return Image[]
     */
    public function findWithNoStorageOrStorageForAlbums(array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where($qb->expr()->isNull('i.storage_category_id'));

        if ($album_ids !== []) {
            $qb->orWhere($qb->expr()->notIn('i.storage_category_id', $album_ids));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * this list does not contain images that are not in at least an authorized album
     *
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function getForbiddenImages(array $forbidden_albums = [], int $level = 0)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('i.level > :level');
        $qb->setParameter('level', $level);

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     * @param array<array<int, string>> $sorts
     *
     * @return Image[]
     */
    public function findMostVisited(array $forbidden_albums = [], array $sorts = [], ?int $limit = null)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('i.hit > 0');

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        foreach ($sorts as $order_by) {
            $qb->orderBy('i.' . $order_by[0], $order_by[1] ?? null);
        }

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     * @param array<array<int, string>> $sorts
     *
     * @return Image[]
     */
    public function findRecentImages(DateTimeInterface $recent_date, array $forbidden_albums = [], array $sorts = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        $qb->where('i.date_available >= :recent_date');
        $qb->setParameter('recent_date', $recent_date);

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        foreach ($sorts as $order_by) {
            $qb->orderBy('i.' . $order_by[0], $order_by[1] ?? null);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Image[]
     */
    public function findBestRatedImages(int $limit)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->orderBy('i.rating_score', 'DESC');
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     * @param array<array<int, string>> $sorts
     *
     * @return Image[]
     */
    public function findBestRated(int $limit, array $forbidden_albums = [], array $sorts = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->isNotNull('i.rating_score'));

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        foreach ($sorts as $order_by) {
            $qb->orderBy('i.' . $order_by[0], $order_by[1] ?? null);
        }

        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return int[]
     */
    public function findRandomImages(int $max, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        $qb_count = clone $qb;
        $qb_count->select('COUNT(DISTINCT(i.id))');

        if ($qb_count->getQuery()->getSingleScalarResult() > 10000) { // arbitrary max to avoid too much memory usage
            return [];
        }

        $qb->setMaxResults($max);

        $ids = [];
        foreach ($qb->getQuery()->getResult() as $image) {
            $ids[] = $image->getId();
        }
        shuffle($ids);

        return $ids;
    }

    /**
     * Find a random photo among all photos inside an album (including sub-albums)
     *
     * @param int[] $forbidden_albums
     */
    public function getRandomImageInAlbum(int $album_id, string $uppercats = '', array $forbidden_albums = [], bool $recursive = true): ?Image
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('count(1)');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->leftJoin('ia.album', 'a');
        $qb->where('a.id = :album_id');
        $qb->setParameter('album_id', $album_id);

        if ($recursive) {
            $qb->orWhere($qb->expr()->like('a.uppercats', ':uppercats'));
            $qb->setParameter('uppercats', $uppercats . ',%');
        }

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_albums));
        }
        $nb_images = $qb->getQuery()->getSingleScalarResult();

        if ($nb_images > 0) {
            $qb = $this->createQueryBuilder('i');
            $qb->leftJoin('i.imageAlbums', 'ia');
            $qb->leftJoin('ia.album', 'a');
            $qb->where('a.id = :album_id');
            $qb->setParameter('album_id', $album_id);

            if ($recursive) {
                $qb->orWhere($qb->expr()->like('a.uppercats', ':uppercats'));
                $qb->setParameter('uppercats', $uppercats . ',%');
            }

            if ($forbidden_albums !== []) {
                $qb->andWhere($qb->expr()->notIn('a.id', $forbidden_albums));
            }
            $qb->setFirstResult(random_int(0, $nb_images - 1));
            $qb->setMaxResults(1);

            return $qb->getQuery()->getOneOrNullResult();
        }

        return null;
    }

    /**
     * @param array<int> $ids
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function getList(array $ids, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->in('i.id', $ids));

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Image[]|int
     */
    public function getNewElements(array $forbidden_albums = [], ?DateTimeInterface $start = null, ?DateTimeInterface $end = null, bool $count_only = false): array|int
    {
        $qb = $this->createQueryBuilder('i');
        if ($count_only) {
            $qb->select('COUNT(1)');
        }
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (!is_null($start)) {
            $qb->where('i.date_available > :start');
            $qb->setParameter('start', $start);
        }

        if (!is_null($end)) {
            $qb->andWhere('i.date_available <= :end');
            $qb->setParameter('end', $end);
        }

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        if ($count_only) {
            return $qb->getQuery()->getSingleScalarResult();
        } else {
            return $qb->getQuery()->getResult();
        }
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Album[]|int
     */
    public function getUpdatedAlbums(array $forbidden_albums = [], ?DateTimeInterface $start = null, ?DateTimeInterface $end = null, bool $count_only = false): array|int
    {
        return $this->getNewElements($forbidden_albums, $start, $end, $count_only);
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return array<string, array{upp: ?string, img_count: int|string}>
     */
    public function getRecentImages(int $limit, ?DateTimeInterface $date_available = null, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(a.uppercats) AS upp, COUNT(i.id) AS img_count');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->leftJoin('ia.album', 'a');
        $qb->where('i.date_available = :date_avaiable');
        $qb->setParameter('date_avaiable', $date_available);

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        $qb->groupBy('a.id, a.uppercats');
        $qb->orderBy('img_count', 'DESC');
        $qb->setMaxResults($limit);

        $results = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $results[$row['upp']] = $row;
        }

        return $results;
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return array<int, array{date_available: DateTimeInterface, nb_elements: (int<0, max> | numeric-string)}>
     */
    public function getRecentPostedImages(int $limit, array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.date_available, COUNT(DISTINCT(i.id)) AS nb_elements');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        $qb->groupBy('i.date_available');
        $qb->orderBy('i.date_available', 'DESC');
        $qb->setMaxResults($limit);

        $results = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $results[] = $row;
        }

        return $results;
    }

    /**
     * @param int[] $forbidden_albums
     * @param array<array<int, string>> $order_by
     *
     * @return Image[]
     */
    public function searchDistinctId(array $forbidden_albums = [], array $order_by = [], ?int $limit = null)
    {
        $qb = $this->createQueryBuilder('i');
        // $qb->select('DISTINCT(i.id) AS id');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        foreach ($order_by as $order_by_element) {
            $qb->addOrderBy('i.' . $order_by_element[0], $order_by_element[1]);
        }

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     * @param array<array<int, string>> $order_by
     *
     * @return Image[]
     */
    public function searchDistinctIdInAlbum(int $album_id, array $forbidden_albums = [], array $order_by = [], ?int $limit = null)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        foreach ($order_by as $order_by_element) {
            $qb->addOrderBy('i.' . $order_by_element[0], $order_by_element[1]);
        }

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    // Calendar images
    protected function getFieldFromDateType(string $date_type = 'posted'): string
    {
        return ($date_type === 'posted') ? 'date_available' : 'date_creation';
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return array<array{year: int, nb_images: int}>
     */
    public function countImagesByYear(string $date_type = 'posted', array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('YEAR(i.' . $this->getFieldFromDateType($date_type) . ') as year, COUNT(i.id) as nb_images');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->groupBy('year');
        $qb->addGroupBy('ia.album');

        $andXExpression = [];

        if ($forbidden_albums !== []) {
            $andXExpression[] = $qb->expr()->notIn('ia.album', ':ids');
            $qb->setParameter('ids', $forbidden_albums);
        }

        if ($andXExpression !== []) {
            $qb->having($qb->expr()->andX(...$andXExpression));
        }

        $qb->orderBy('year', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $years
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function findOneImagePerYear(array $years = [], string $date_type = 'posted', array $forbidden_albums = [])
    {
        if ($years === []) {
            return [];
        }

        $fmt = '(SELECT * FROM phyxo_images AS i';
        $fmt .= ' LEFT JOIN phyxo_image_category ic ON i.id = ic.image_id';
        $fmt .= ' WHERE';

        if ($forbidden_albums !== []) {
            $fmt .= ' ic.category_id NOT IN(%s) AND';
        }

        $fmt .= ' EXTRACT(YEAR FROM %s) = ?';
        $fmt .= ' LIMIT 1)';

        if ($forbidden_albums !== []) {
            $sql_select = sprintf($fmt, implode(', ', $forbidden_albums), $this->getFieldFromDateType($date_type));
        } else {
            /** @phpstan-ignore-next-line */
            $sql_select = sprintf($fmt, $this->getFieldFromDateType($date_type));
        }

        $sql_parts = array_fill(0, count($years), $sql_select);
        $sql = implode(' UNION ALL ', $sql_parts);

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata(Image::class, 'i');

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        foreach ($years as $index => $year) {
            $query->setParameter($index, $year);
        }

        return $query->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return array<array{month: int, nb_images: int}>
     */
    public function countImagesByMonth(int $year, string $date_type = 'posted', array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MONTH(i.' . $this->getFieldFromDateType($date_type) . ') as month, COUNT(i.id) as nb_images');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('YEAR(i.' . $this->getFieldFromDateType($date_type) . ') = :year');
        $qb->setParameter('year', $year);
        $qb->groupBy('month');
        $qb->orderBy('month', 'ASC');

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int, string> $months
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function findOneImagePerMonth(int $year, array $months = [], string $date_type = 'posted', array $forbidden_albums = [])
    {
        if ($months === []) {
            return [];
        }

        $fmt = '(SELECT * FROM phyxo_images AS i';
        $fmt .= ' LEFT JOIN phyxo_image_category ic ON i.id = ic.image_id';
        $fmt .= ' WHERE';

        if ($forbidden_albums !== []) {
            $fmt .= ' ic.category_id NOT IN(%s) AND';
        }

        $fmt .= ' EXTRACT(MONTH FROM %s) = ?';
        $fmt .= ' AND EXTRACT(YEAR FROM %s) = :year';
        $fmt .= ' LIMIT 1)';

        if ($forbidden_albums !== []) {
            $sql_select = sprintf($fmt, implode(', ', $forbidden_albums), $this->getFieldFromDateType($date_type), $this->getFieldFromDateType($date_type));
        } else {
            /** @phpstan-ignore-next-line */
            $sql_select = sprintf($fmt, $this->getFieldFromDateType($date_type), $this->getFieldFromDateType($date_type));
        }

        $sql_parts = array_fill(0, count($months), $sql_select);
        $sql = implode(' UNION ALL ', $sql_parts);

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata(Image::class, 'i');

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('year', $year);
        foreach ($months as $index => $month) {
            $query->setParameter($index, $month);
        }

        return $query->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return array<array{day: int, nb_images: int}>
     */
    public function countImagesByDay(int $year, int $month, string $date_type = 'posted', array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DAY(i.' . $this->getFieldFromDateType($date_type) . ') as day, COUNT(i.id) as nb_images');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->groupBy('day');
        $qb->where('YEAR(i.' . $this->getFieldFromDateType($date_type) . ') = :year');
        $qb->andWhere('MONTH(i.' . $this->getFieldFromDateType($date_type) . ') = :month');
        $qb->setParameter('year', $year);
        $qb->setParameter('month', $month);
        $qb->orderBy('day', 'ASC');

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int, string> $days
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function findOneImagePerDay(int $year, int $month, array $days = [], string $date_type = 'posted', array $forbidden_albums = [])
    {
        if ($days === []) {
            return [];
        }

        $fmt = '(SELECT * FROM phyxo_images AS i';
        $fmt .= ' LEFT JOIN phyxo_image_category ic ON i.id = ic.image_id';
        $fmt .= ' WHERE';

        if ($forbidden_albums !== []) {
            $fmt .= ' ic.category_id NOT IN(%s) AND';
        }

        $fmt .= ' EXTRACT(DAY FROM %s) = ?';
        $fmt .= ' AND EXTRACT(MONTH FROM %s) = :month';
        $fmt .= ' AND EXTRACT(YEAR FROM %s) = :year';
        $fmt .= ' LIMIT 1)';

        if ($forbidden_albums !== []) {
            $sql_select = sprintf(
                $fmt,
                implode(', ', $forbidden_albums),
                $this->getFieldFromDateType($date_type),
                $this->getFieldFromDateType($date_type),
                $this->getFieldFromDateType($date_type)
            );
        } else {
            /** @phpstan-ignore-next-line */
            $sql_select = sprintf($fmt, $this->getFieldFromDateType($date_type), $this->getFieldFromDateType($date_type), $this->getFieldFromDateType($date_type));
        }

        $sql_parts = array_fill(0, count($days), $sql_select);
        $sql = implode(' UNION ALL ', $sql_parts);

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata(Image::class, 'i');

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('year', $year);
        $query->setParameter('month', $month);
        foreach ($days as $index => $day) {
            $query->setParameter($index, $day);
        }

        return $query->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function findImagesPerDate(DateTimeInterface $date, string $date_type = 'posted', array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('DATE(i.' . $this->getFieldFromDateType($date_type) . ') = :date');
        $qb->setParameter('date', $date);
        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{int, DateTimeInterface, DateTimeInterface}|array{}
     */
    public function getImagesInfosInAlbum(int $album_id)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->select('COUNT(i.id) AS count, MIN(i.date_available) as min_date, MAX(i.date_available) as max_date');
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        $results = $qb->getQuery()->getOneOrNullResult();
        if (is_null($results)) {
            return [];
        }

        return [$results['count'], new DateTime($results['min_date']), new DateTime($results['max_date'])];
    }

    /**
     * @param array<array<int, string>> $order_by
     *
     * @return Image[]
     */
    public function findImagesInAlbum(int $album_id, array $order_by = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        foreach ($order_by as $order_by_element) {
            $qb->addOrderBy('ia.' . $order_by_element[0], $order_by_element[1]);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     *
     * @return Image[]
     */
    public function getImagesFromAlbums(array $album_ids, int $limit, int $offset = 0)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->in('ia.album', $album_ids));

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $image_ids
     *
     * @return Image[]
     */
    public function findImagesByAlbum(array $image_ids, int $album_id)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);
        $qb->andWhere($qb->expr()->in('i.id', $image_ids));
        $qb->andWhere($qb->expr()->orX(
            $qb->expr()->neq('ia.album', 'i.storage_category_id'),
            $qb->expr()->isNull('i.storage_category_id')
        ));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $image_ids
     *
     * @return array<int, array<string, int|string|null>>
     */
    public function findAlbumsWithImages(array $image_ids): array
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('IDENTITY(ia.album) AS id');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->in('i.id', $image_ids));
        $qb->andWhere($qb->expr()->orX(
            $qb->expr()->neq('ia.album', 'i.storage_category_id'),
            $qb->expr()->isNull('i.storage_category_id')
        ));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $image_ids
     * @param array<array<int, string>> $order_by
     *
     * @return Image[]
     */
    public function findByImageIdsAndAlbumId(array $image_ids, array $order_by, int $limit, ?int $album_id = null, int $offset = 0)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->in('i.id', $image_ids));

        if (!is_null($album_id)) {
            $qb->andWhere('ia.album = :album_id');
            $qb->setParameter('album_id', $album_id);
        }

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        foreach ($order_by as $order_by_element) {
            $qb->addOrderBy('i.' . $order_by_element[0], $order_by_element[1]);
        }

        return $qb->getQuery()->getResult();
    }

    public function findMaxDateAvailable() : ?DateTimeInterface
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MAX(i.date_available) AS max_date');

        $single_result = $qb->getQuery()->getSingleResult();

        if (!is_null($single_result)) {
            return new DateTime($single_result['max_date']);
        }

        return null;
    }

    public function findMinDateAvailable(): ?DateTimeInterface
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MIN(i.date_available) AS min_date');

        $single_result = $qb->getQuery()->getSingleResult();

        if (!is_null($single_result)) {
            return new DateTime($single_result['min_date']);
        }

        return null;
    }

    /**
     * @return Image[]
     */
    public function findImagesFromLastImport(DateTimeInterface $max_date)
    {
        $max_date_one_day_before = new DateTime();
        $max_date_one_day_before->setTimestamp($max_date->getTimestamp());
        $max_date_one_day_before->sub(new DateInterval('P1D'));

        $qb = $this->createQueryBuilder('i');
        $qb->where('i.date_available >= :date1');
        $qb->setParameter('date1', $max_date_one_day_before);
        $qb->andWhere('i.date_available <= :max_date');
        $qb->setParameter('max_date', $max_date);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Image[]
     */
    public function findImagesByWidth(int $width, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.width ' . $operator . ' :width');
        $qb->setParameter('width', $width);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Image[]
     */
    public function findImagesByHeight(int $height, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.height ' . $operator . ' :height');
        $qb->setParameter('height', $height);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Image[]
     */
    public function findImagesByRatio(float $ratio, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.width/i.height ' . $operator . ' :ratio');
        $qb->setParameter('ratio', $ratio);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Image[]
     */
    public function findImagesByFilesize(float $filesize, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.filesize ' . $operator . ' :filesize');
        $qb->setParameter('filesize', $filesize);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Image[]
     */
    public function findImageWithNoAlbum()
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->isNull('ia.album'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $fields
     *
     * @return string[]
     */
    public function findDuplicates(array $fields)
    {
        // $query = 'SELECT ' . $this->conn->db_group_concat('id') . ' AS ids FROM ' . self::IMAGES_TABLE;
        // $query .= ' GROUP BY ' . implode(', ', $fields);
        // $query .= ' HAVING COUNT(*) > 1';

        // return $this->conn->db_query($query);

        return [];
    }

    /**
     * @return Image[]
     */
    public function filterByLevel(int $level, string $operator = '=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.level ' . $operator . ' :level');
        $qb->setParameter('level', $level);

        return $qb->getQuery()->getResult();
    }

    public function findAlbumWithLastImageAdded(): ?Image
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->isNull('ia.album'));
        $qb->orderBy('i.id', 'DESC');
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function findGroupByAuthor(array $forbidden_albums = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if ($forbidden_albums !== []) {
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_albums));
        }
        $qb->andWhere($qb->expr()->isNotNull('i.author'));
        $qb->groupBy('i.author');
        $qb->addGroupBy('i.id');
        $qb->orderBy('i.author');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $album_ids
     *
     * @return Image[]
     */
    public function getReferenceDateForAlbums(string $field, string $minmax, array $album_ids)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->select('IDENTITY(ia.album) AS album_id, ' . $minmax . '(' . $field . ') AS ref_date');
        $qb->where($qb->expr()->in('ia.album', $album_ids));
        $qb->groupBy('ia.album');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function qSearchImages(string $words, array $forbidden_albums = [])
    {
        $search_value = '%' . str_replace(' ', '%', trim(strtolower($words))) . '%';

        $qb = $this->createQueryBuilder('i');
        if ($forbidden_albums !== []) {
            $qb->leftJoin('i.imageAlbums', 'ia');
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        $qb->andWhere(
            $qb->expr()->orX($qb->expr()->like("lower('i.name')", ':name')),
            $qb->expr()->orX($qb->expr()->like("lower('i.comment')", ':name'))
        );

        $qb->setParameter('name', $search_value);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, array<string, array<string, array<string>|string>>> $rules
     * @param int[] $forbidden_albums
     *
     * @return Image[]
     */
    public function searchImages(array $rules, array $forbidden_albums = [])
    {
        $whereMethod = $rules['mode'] == 'AND' ? 'andWhere' : 'orWhere';

        $qb = $this->createQueryBuilder('i');
        if ($forbidden_albums !== [] || isset($rules['fields']['cat'])) {
            $qb->leftJoin('i.imageAlbums', 'ia');
        }

        $clauses = [];
        foreach (['file', 'name', 'comment', 'author'] as $field) {
            if (isset($rules['fields'][$field])) {
                foreach ($rules['fields'][$field]['words'] as $i => $word) {
                    if ($field === 'author') {
                        $clauses[] = $qb->expr()->eq('i.' . $field, ':value' . $i);
                        $qb->setParameter('value' . $i, $word);
                    } else {
                        $clauses[] = $qb->expr()->like('i.' . $field, ':value' . $i);
                        $qb->setParameter('value' . $i, '%' . $word . '%');
                    }
                }
            }
        }
        if ($clauses !== []) {
            $qb->$whereMethod(...$clauses);
        }

        if (isset($rules['fields']['cat'])) {
            $qb->andWhere($qb->expr()->in('ia.album', $rules['fields']['cat']['words']));
        }

        if (isset($rules['fields']['allwords'])) {
            $fields = ['file', 'name', 'comment'];

            if (isset($rules['fields']['allwords']['fields']) && (is_countable($rules['fields']['allwords']['fields']) ? count($rules['fields']['allwords']['fields']) : 0) > 0) {
                $fields = array_intersect($fields, $rules['fields']['allwords']['fields']);
            }

            $clauses = [];
            foreach ($rules['fields']['allwords']['words'] as $i => $word) {
                $orClauses = [];
                foreach ($fields as $field) {
                    $orClauses[] = $qb->expr()->like('i.' . $field, ':word' . $i);
                    $qb->setParameter('word' . $i, '%' . $word . '%');
                }
                $clauses[] = $qb->expr()->orX(...$orClauses);
            }
            $qb->$whereMethod(...$clauses);
        }

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        foreach (['date_available', 'date_creation'] as $datefield) {
            foreach (['after', 'before'] as $suffix) {
                $key = $datefield . '-' . $suffix;

                if (isset($rules['fields'][$key])) {
                    $sign = $suffix === 'after' ? ' >' : ' <';
                    $sign .= $rules['fields'][$key]['inc'] ? '=' : '';

                    $qb->$whereMethod('i.' . $datefield . $sign . ':date_value');
                    $qb->setParameter('date_value', $rules['fields'][$key]['date']);
                }
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        $qb = $this->createQueryBuilder('i');
        $qb->delete();
        $qb->where($qb->expr()->in('i.id', $ids));

        $qb->getQuery()->getResult();
    }

    public function findFirstDate(): int
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MIN(i.date_available)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int[] $forbidden_albums
     */
    public function isAuthorizedToUser(int $image_id, array $forbidden_albums = []): bool
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('COUNT(1)');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('i.id = :image_id');
        $qb->setParameter('image_id', $image_id);

        if ($forbidden_albums !== []) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @param int[] $forbidden_albums
     * @param string[] $tag_ids
     *
     * @return Image[]
     */
    public function getImageIdsForTags(array $forbidden_albums = [], array $tag_ids = [], string $mode = 'AND')
    {
        if ($tag_ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageTags', 'it');
        $qb->where($qb->expr()->in('it.tag', $tag_ids));

        if ($forbidden_albums !== []) {
            $qb->leftJoin('i.imageAlbums', 'ia');
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        if ($mode === 'AND') {
            $qb->groupBy('i.id');
            $qb->having('COUNT(DISTINCT(it.tag)) = ' . count($tag_ids));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Image[]
     */
    public function findImagesInPhysicalAlbums(): array
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where($qb->expr()->notLike('i.path', ':upload'));
        $qb->setParameter('upload', '%upload%');

        return $qb->getQuery()->getResult();
    }
}
