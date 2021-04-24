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
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

class ImageRepository extends ServiceEntityRepository
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

    public function updateFieldForImages(array $ids, string $field, $value)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->update();
        $qb->set('i.' . $field, ':value');
        $qb->setParameter('value', $value);
        $qb->where($qb->expr()->in('i.id', $ids));

        $qb->getQuery()->getResult();
    }

    public function updateLevel(int $image_id, int $level = 0)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->update();
        $qb->set('i.level', ':level');
        $qb->setParameter('level', $level);
        $qb->where('i.id = :image_id');
        $qb->setParameter('image_id', $image_id);

        $qb->getQuery()->getResult();
    }

    public function updateRatingScore(int $image_id, ?float $rating_score)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->update();
        $qb->set('i.rating_score', ':rating_score');
        $qb->setParameter('rating_score', $rating_score);
        $qb->where('i.id = :image_id');
        $qb->setParameter('image_id', $image_id);

        $qb->getQuery()->getResult();
    }

    public function findWithNoStorageOrStorageForAlbums(array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where($qb->expr()->isNull('i.storage_category_id'));

        if (count($album_ids) > 0) {
            $qb->orWhere($qb->expr()->notIn('i.storage_category_id', $album_ids));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * this list does not contain images that are not in at least an authorized category
     */
    public function getForbiddenImages(array $forbidden_categories = [], int $level = 0)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('i.level > :level');
        $qb->setParameter('level', $level);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function findMostVisited(array $forbidden_categories = [], array $sorts = [], ?int $limit = null)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('i.hit > 0');

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if (count($sorts) > 0) {
            foreach ($sorts as $order_by) {
                $qb->orderBy('i.' . $order_by[0], $order_by[1] ?? null);
            }
        }

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findRecentImages(array $forbidden_categories = [], \DateTimeInterface $recent_date, array $sorts = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        $qb->where('i.date_available >= :recent_date');
        $qb->setParameter('recent_date', $recent_date);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if (count($sorts) > 0) {
            foreach ($sorts as $order_by) {
                $qb->orderBy('i.' . $order_by[0], $order_by[1] ?? null);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findBestRatedImages(int $limit)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->orderBy('i.rating_score', 'DESC');
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function findBestRated(array $forbidden_categories = [], array $sorts = [], int $limit)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->isNotNull('i.rating_score'));

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if (count($sorts) > 0) {
            foreach ($sorts as $order_by) {
                $qb->orderBy('i.' . $order_by[0], $order_by[1] ?? null);
            }
        }

        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function findRandomImages(array $forbidden_categories = [], int $max): array
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
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

    public function getList(array $ids, array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->in('i.id', $ids));

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

    public function getNewElements(array $forbidden_categories = [], \DateTimeInterface $start = null, \DateTimeInterface $end = null, bool $count_only = false)
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

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if ($count_only) {
            return $qb->getQuery()->getSingleScalarResult();
        } else {
            return $qb->getQuery()->getResult();
        }
    }

    public function getUpdatedAlbums(array $forbidden_categories = [], \DateTimeInterface $start = null, \DateTimeInterface $end = null, bool $count_only = false)
    {
        return $this->getNewElements($forbidden_categories, $start, $end, $count_only);
    }

    public function getRecentImages(array $forbidden_categories = [], \DateTimeInterface $date_available = null, int $limit)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(a.uppercats) AS upp, COUNT(i.id) AS img_count');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->leftJoin('ia.album', 'a');
        $qb->where('i.date_available = :date_avaiable');
        $qb->setParameter('date_avaiable', $date_available);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
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

    public function getRecentPostedImages(array $forbidden_categories = [], int $limit)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.date_available, COUNT(DISTINCT(i.id)) AS nb_elements');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
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

    public function searchDistinctId(array $forbidden_categories = [], string $order_by, ?int $limit = null)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(i.id) AS id');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        // $qb->orderBy($order_by);

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function searchDistinctIdInAlbum(int $album_id, array $forbidden_categories = [], string $order_by, ?int $limit = null)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(i.id) AS id');
        $qb->leftJoin('i.imageAlbums', 'ia');

        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        // $qb->orderBy($order_by);

        if (!is_null($limit)) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    // Calendar queries
    public function findImagesInPeriodsByIds(string $level, array $ids = [], string $date_where = '')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(i.' . $level . ') as period, COUNT(DISTINCT i.id) as nb_images)');
        $qb->where($qb->expr()->in('i.id', $ids));
        $qb->andWhere($date_where);
        $qb->groupBy('period');

        return $qb->getQuery()->getResult();
    }

    public function findImagesInPeriods(string $level, string $date_where = '', array $forbidden_categories = [], array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(i.' . $level . ') as period, COUNT(DISTINCT i.id) as nb_images');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if ($date_where) {
            $qb->andWhere($date_where);
        }

        $qb->groupBy('period');

        return $qb->getQuery()->getResult();
    }

    public function findNextPrevPeriodByIds(array $ids = [], array $date_elements, array $calendar_levels, string $date_field = '')
    {
        $sub_queries = [];
        $nb_elements = count($date_elements);
        for ($i = 0; $i < $nb_elements; $i++) {
            if ($date_elements[$i] !== 'any') { // @TODO: replace by null ?
                //$sub_queries[] = $this->conn->db_cast_to_text($calendar_levels[$i]['sql']);
                $sub_queries[] = $calendar_levels[$i]['sql'];
            }
        }

        $sub_queries = array_map(
            function($field) {
                return 'i.' . $field;
            }, $sub_queries
        );
        $qb = $this->createQueryBuilder('i');
        $qb->select('CONCAT(' . implode(',\'-\',', $sub_queries) . ') AS period');
        $qb->where($qb->expr()->in('i.id', $ids));
        $qb->andWhere($qb->expr()->isNotNull('i.' . $date_field));
        $qb->groupBy('period');

        return $qb->getQuery()->getResult();
    }

    // calendar query
    public function findNextPrevPeriod(array $date_elements, array $calendar_levels, string $date_field = '', array $forbidden_categories = [], array $album_ids = [])
    {
        $sub_queries = [];
        $nb_elements = count($date_elements);
        for ($i = 0; $i < $nb_elements; $i++) {
            if ($date_elements[$i] !== 'any') { // @TODO: replace by null ?
                // $sub_queries[] = $this->conn->db_cast_to_text($calendar_levels[$i]['sql']);
                $sub_queries[] = $calendar_levels[$i]['sql'];
            }
        }

        $sub_queries = array_map(
            function($field) {
                return 'i.' . $field;
            }, $sub_queries
        );
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->select('CONCAT(' . implode(',\'-\',', $sub_queries) . ') AS period');
        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        $qb->andWhere($qb->expr()->isNotNull('i.' . $date_field));
        $qb->groupBy('period');

        return $qb->getQuery()->getResult();
    }

    public function findDistincIds(array $forbidden_categories = [], array $album_ids = [], array $sorts = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(i.id)');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if (count($sorts) > 0) {
            foreach ($sorts as $order_by) {
                $qb->orderBy('i.' . $order_by[0], $order_by[1] ?? null);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findDayOfMonthPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.' . $date_field . ' AS period, COUNT(DISTINCT(i.id)) as count');
        $qb->where($qb->expr()->in('i.id', $ids));

        if ($date_where) {
            $qb->andWhere($date_where);
        }
        $qb->groupBy('period');
        $qb->orderBy('period', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findDayOfMonthPeriodAndImagesCount(string $date_field, string $date_where = '', array $forbidden_categories = [], array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.' . $date_field . ' AS period, COUNT(DISTINCT(i.id)) as count');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }

        if ($date_where) {
            $qb->andWhere($date_where);
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        $qb->groupBy('period');
        $qb->orderBy('period', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findYYYYMMPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.' . $date_field . ' AS period, COUNT(DISTINCT(i.id)) as count');
        $qb->where($qb->expr()->in('i.id', $ids));

        if ($date_where) {
            $qb->andWhere($date_where);
        }
        $qb->groupBy('period');
        $qb->orderBy('period', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findYYYYMMPeriodAndImagesCount(string $date_field, string $date_where = '', array $forbidden_categories = [], array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.' . $date_field . ' AS period, COUNT(DISTINCT(i.id)) as count');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if ($date_where) {
            $qb->andWhere($date_where);
        }

        $qb->groupBy('period');
        $qb->orderBy('period', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findMMDDPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.' . $date_field . ' AS period, COUNT(DISTINCT(i.id)) as count');
        $qb->where($qb->expr()->in('i.id', $ids));
        if ($date_where) {
            $qb->andWhere($date_where);
        }

        $qb->groupBy('period');
        $qb->orderBy('period', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findMMDDPeriodAndImagesCount(string $date_field, string $date_where = '', array $forbidden_categories = [], array $album_ids = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.' . $date_field . ' AS period, COUNT(DISTINCT(i.id)) as count');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if ($date_where) {
            $qb->andWhere($date_where);
        }

        $qb->groupBy('period');
        $qb->orderBy('period', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findOneRandomInWeekByIds(string $date_where = '', array $ids)
    {
        // avoid rand() in sql query
        $qb = $this->createQueryBuilder('i');
        $qb->select('COUNT(1)');
        $qb->where($qb->expr()->in('i.id', $ids));
        if ($date_where) {
            $qb->andWhere($date_where);
        }

        $nb_images = $qb->getQuery()->getSingleScalarResult();

        $qb = $this->createQueryBuilder('i');
        $qb->where($qb->expr()->in('i.id', $ids));
        if ($date_where) {
            $qb->andWhere($date_where);
        }
        $qb->setFirstResult(random_int(0, $nb_images));
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneRandomInWeek(string $date_where = '', array $forbidden_categories = [], array $album_ids = [])
    {
        // avoid rand() in sql query
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->select('COUNT(1)');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }
        if ($date_where) {
            $qb->andWhere($date_where);
        }

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        $nb_images = $qb->getQuery()->getSingleScalarResult();

        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($album_ids) > 0) {
            $qb->where($qb->expr()->in('ia.album', $album_ids));
        }
        if ($date_where) {
            $qb->andWhere($date_where);
        }
        $qb->setFirstResult(random_int(0, $nb_images));
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findList(array $ids, array $forbidden_categories = [], string $order_by)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('DISTINCT(i.id)');
        $qb->leftJoin('i.imageAlbums', 'ia');

        $qb->where($qb->expr()->in('i.id', $ids));
        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        return $qb->getQuery()->getResult();
    }

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

        return [$results['count'], new \DateTime($results['min_date']), new \DateTime($results['max_date'])];
    }

    public function findImagesInAlbum(int $album_id, string $order_by)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('ia.album = :album_id');
        $qb->setParameter('album_id', $album_id);

        return $qb->getQuery()->getResult();
    }

    public function getImagesFromAlbums(array $album_ids, int $limit, int $offset = 0)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->in('ia.album', $album_ids));

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    public function findImagesInVirtualAlbum(array $image_ids, int $album_id)
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

    public function findVirtualAlbumsWithImages(array $image_ids)
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

    public function findByImageIdsAndAlbumId(array $image_ids, ? int $album_id = null, string $order_by, int $limit, int $offset = 0)
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

        return $qb->getQuery()->getResult();
    }

    public function findMaxDateAvailable() : ?\DateTimeInterface
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MAX(i.date_available) AS max_date');

        $single_result = $qb->getQuery()->getSingleResult();

        if (!is_null($single_result)) {
            return new \DateTime($single_result['max_date']);
        }

        return null;
    }

    public function findMinDateAvailable(): ?\DateTimeInterface
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MIN(i.date_available) AS min_date');

        $single_result = $qb->getQuery()->getSingleResult();

        if (!is_null($single_result)) {
            return new \DateTime($single_result['min_date']);
        }

        return null;
    }

    public function findImagesFromLastImport(\DateTimeInterface $max_date)
    {
        $max_date_one_day_before = new \DateTime();
        $max_date_one_day_before->setTimestamp($max_date->getTimestamp());
        $max_date_one_day_before->sub(new \DateInterval('P1D'));

        $qb = $this->createQueryBuilder('i');
        $qb->where('i.date_available >= :date1');
        $qb->setParameter('date1', $max_date_one_day_before);
        $qb->andWhere('i.date_available <= :max_date');
        $qb->setParameter('max_date', $max_date);

        return $qb->getQuery()->getResult();
    }

    public function findImagesByWidth(int $width, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.width ' . $operator . ' :width');
        $qb->setParameter('width', $width);

        return $qb->getQuery()->getResult();
    }

    public function findImagesByHeight(int $height, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.height ' . $operator . ' :height');
        $qb->setParameter('height', $height);

        return $qb->getQuery()->getResult();
    }

    public function findImagesByRatio(float $ratio, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.width/i.height ' . $operator . ' :ratio');
        $qb->setParameter('ratio', $ratio);

        return $qb->getQuery()->getResult();
    }

    public function findImagesByFilesize(float $filesize, string $operator = '<=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.filesize ' . $operator . ' :filesize');
        $qb->setParameter('filesize', $filesize);

        return $qb->getQuery()->getResult();
    }

    public function findImageWithNoAlbum()
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->isNull('ia.album'));

        return $qb->getQuery()->getResult();
    }

    public function findDuplicates(array $fields)
    {
        // $query = 'SELECT ' . $this->conn->db_group_concat('id') . ' AS ids FROM ' . self::IMAGES_TABLE;
        // $query .= ' GROUP BY ' . implode(', ', $fields);
        // $query .= ' HAVING COUNT(*) > 1';

        // return $this->conn->db_query($query);

        return [];
    }

    public function filterByLevel(int $level, string $operator = '=')
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.level ' . $operator . ' :level');
        $qb->setParameter('level', $level);

        return $qb->getQuery()->getResult();
    }

    public function findAlbumWithLastImageAdded()
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where($qb->expr()->isNull('ia.album'));
        $qb->orderBy('i.id', 'DESC');
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findGroupByAuthor(array $forbidden_categories = [])
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($forbidden_categories) > 0) {
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_categories));
        }
        $qb->andWhere($qb->expr()->isNotNull('i.author'));
        $qb->groupBy('i.author');
        $qb->addGroupBy('i.id');
        $qb->orderBy('i.author');

        return $qb->getQuery()->getResult();
    }

    public function getReferenceDateForAlbums(string $field, string $minmax, array $album_ids)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->select('IDENTITY(ia.album) AS album_id, ' . $minmax . '(' . $field . ') AS ref_date');
        $qb->where($qb->expr()->in('ia.album', $album_ids));
        $qb->groupBy('ia.album');

        return $qb->getQuery()->getResult();
    }

    public function qSearchImages(array $forbidden_categories = [], string $words)
    {
        $search_value = '%' . str_replace(' ', '%', trim(strtolower($words))) . '%';

        $qb = $this->createQueryBuilder('i');
        if (count($forbidden_categories) > 0) {
            $qb->leftJoin('i.imageAlbums', 'ia');
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        $qb->andWhere(
            $qb->expr()->orX($qb->expr()->like($qb->expr()->lower('i.name'), ':name')),
            $qb->expr()->orX($qb->expr()->like($qb->expr()->lower('i.comment'), ':name'))
        );

        $qb->setParameter('name', $search_value);

        return $qb->getQuery()->getResult();
    }

    public function searchImages(array $forbidden_categories = [], array $rules)
    {
        $whereMethod = $rules['mode'] === 'AND' ? 'andWhere' : 'orWhere';

        $qb = $this->createQueryBuilder('i');
        if (count($forbidden_categories) > 0 || isset($rules['fields']['cat'])) {
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
        if (count($clauses) > 0) {
            $qb->$whereMethod(...$clauses);
        }

        if (isset($rules['fields']['cat'])) {
            $qb->andWhere($qb->expr()->in('ia.album', $rules['fields']['cat']['words']));
        }

        if (isset($rules['fields']['allwords'])) {
            $fields = ['file', 'name', 'comment'];

            if (isset($rules['fields']['allwords']['fields']) && count($rules['fields']['allwords']['fields']) > 0) {
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

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
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

    public function deleteByIds(array $ids)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->delete();
        $qb->where($qb->expr()->in('i.id', $ids));

        $qb->getQuery()->getResult();
    }

    public function getMaxLastModified()
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MAX(i.last_modified) as max, COUNT(1) as count');

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function findMaxIdAndCount()
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MAX(i.last_modified) as max, COUNT(1) as count');

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function findFirstDate()
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('MIN(i.date_available)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function isAuthorizedToUser(array $forbidden_categories = [], int $image_id)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('COUNT(1)');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->where('i.id = :image_id');
        $qb->setParameter('image_id', $image_id);

        if (count($forbidden_categories) > 0) {
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function getImageIdsForTags(array $forbidden_categories = [], array $tag_ids = [], string $mode = 'AND')
    {
        if (empty($tag_ids)) {
            return [];
        }

        $qb = $this->createQueryBuilder('i');
        $qb->leftJoin('i.imageTags', 'it');
        $qb->where($qb->expr()->in('it.tag', $tag_ids));

        if (count($forbidden_categories) > 0) {
            $qb->leftJoin('i.imageAlbums', 'ia');
            $qb->andWhere($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if ($mode === 'AND') {
            $qb->groupBy('i.id');
            $qb->having('COUNT(DISTINCT(it.tag)) = ' . count($tag_ids));
        }

        return $qb->getQuery()->getResult();
    }
}
