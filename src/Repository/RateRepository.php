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

use App\Entity\Rate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

class RateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rate::class);
    }

    public function addOrUpdateRate(Rate $rate)
    {
        $this->_em->persist($rate);
        $this->_em->flush();
    }

    public function calculateRateSummary(int $image_id)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('COUNT(r.rate) AS count, AVG(r.rate) AS average');
        $qb->where('r.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR);
    }

    public function countImagesRatedForUser(int $user_id, string $operator = '') : int
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('COUNT(DISTINCT(r.image))');

        if ($operator != '') {
            $qb->where('r.user ' . $operator . ' :user_id');
            $qb->setParameter('user_id', $user_id);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getRatePerImage(int $user_id, string $operator = null, string $order, int $limit, int $offset = 0)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->leftJoin('r.image', 'i');
        $qb->select('i.id, i.path, i.file, i.representative_ext, i.rating_score AS score');
        $qb->addSelect('MAX(r.date) AS recently_rated, AVG(r.rate) AS avg_rates');
        $qb->addSelect('COUNT(r.rate) AS nb_rates, SUM(r.rate) AS sum_rates');

        if ($operator != '') {
            $qb->where('r.user ' . $operator . ' :user_id');
            $qb->setParameter('user_id', $user_id);
        }

        $qb->groupBy('i.id, i.path, i.file, i.representative_ext, i.rating_score, r.image');
        // $qb->orderBy($order);
        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    public function updateAnonymousIdField(string $new_anonymous_id, int $user_id, string $anonymous_id)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->update();
        $qb->set('r.anonymous_id', ':new_anonymous_id');
        $qb->setParameter('new_anonymous_id', $new_anonymous_id);
        $qb->where('r.user = :user_id');
        $qb->setParameter('user_id', $user_id);
        $qb->andWhere('r.anonymous_id = :anonymous_id');
        $qb->setParameter('anonymous_id', $anonymous_id);

        $qb->getQuery()->getResult();
    }

    public function deleteImageRateForUser(int $user_id, int $image_id, ?string $anonymous_id = null)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->delete();
        $qb->where('r.user = :user_id');
        $qb->setParameter('user_id', $user_id);
        $qb->andWhere('r.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        if (!is_null($anonymous_id)) {
            $qb->andWhere('r.anonymous_id = :anonymous_id');
            $qb->setParameter('anonymous_id', $anonymous_id);
        }

        $qb->getQuery()->getResult();
    }

    public function calculateRateByImage()
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('IDENTITY(r.image) AS image, COUNT(r.rate) AS rcount, SUM(r.rate) AS rsum');
        $qb->groupBy('r.image');

        return $qb->getQuery()->getResult();
    }

    public function calculateAverageByImage()
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('IDENTITY(r.image) AS image, AVG(r.rate) AS avg');
        $qb->groupBy('r.image');

        return $qb->getQuery()->getResult();
    }

    public function delete(Rate $rate)
    {
        $this->_em->remove($rate);
        $this->_em->flush();
    }

    public function deleteByImageIds(array $ids)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->delete();
        $qb->where($qb->expr()->in('r.image', $ids));

        $qb->getQuery()->getResult();
    }

    public function deleteWithConditions(int $user_id, ?int $image_id = null, ?string $anonymous_id)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->delete();
        $qb->where('r.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        if (!is_null($image_id)) {
            $qb->andWhere('r.image = :image_id');
            $qb->setParameter('image_id', $image_id);
        }

        if (!is_null($anonymous_id)) {
            $qb->andWhere('r.anonymous_id = :anonymous_id');
            $qb->setParameter('anonymous_id', $anonymous_id);
        }

        $qb->getQuery()->getResult();
    }
}
