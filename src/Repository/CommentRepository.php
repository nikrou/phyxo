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

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function addOrUpdateComment(Comment $comment): int
    {
        $this->_em->persist($comment);
        $this->_em->flush();

        return $comment->getId();
    }

    public function doestAuthorPostMessageAfterThan(int $user_id, \DateTimeInterface $anti_flood_time, ?string $anonymous_id = null)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(1)');
        $qb->where('c.date > :flood_time');
        $qb->setParameter('flood_time', $anti_flood_time);
        $qb->andWhere('c.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        if (!is_null($anonymous_id)) {
            $qb->andWhere($qb->expr()->like('c.anonymous_id', ':anonymous_id'));
            $qb->setParameter('anonymous_id', $anonymous_id . '%');
        }

        return $qb->getQuery()->getSingleScalarResult() === 1;
    }

    public function deleteByIds(array $comment_ids, ?int $user_id = null)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where($qb->expr()->in('c.id', $comment_ids));

        if (!is_null($user_id)) {
            $qb->andWhere('c.user = :user_id');
            $qb->setParameter('user_id', $user_id);
        }

        $qb->getQuery()->getResult();
    }

    public function deleteByUserId(int $user_id)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->delete();
        $qb->where('c.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }

    public function deleteByImage(array $image_ids)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->delete();
        $qb->where($qb->expr()->in('c.image', $image_ids));

        $qb->getQuery()->getResult();
    }

    public function validateUserComment(array $comment_ids)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->update();
        $qb->set('c.validated', ':validated');
        $qb->setParameter('validated', true);
        $qb->set('c.validation_date', new \DateTime());
        $qb->where($qb->expr()->in('c.id', $comment_ids));

        $qb->getQuery()->getResult();
    }

    public function getCommentsOnImages(int $limit, int $offset = 0, bool $validated)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->leftJoin('c.user', 'u');
        $qb->leftJoin('c.image', 'i');
        $qb->andWhere('c.validated = :validated');
        $qb->setParameter('validated', $validated);
        $qb->orderBy('c.date', 'ASC');
        $qb->setFirstResult($offset);
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countGroupByImage(array $image_ids, bool $validated = true)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(1)');
        $qb->where('c.validated = :validated');
        $qb->setParameter('validated', $validated);
        $qb->andWhere($qb->expr()->in('c.image', $image_ids));
        $qb->groupBy('c.image');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countGroupByValidated()
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(1) AS counter, c.validated');
        $qb->groupBy('c.validated');

        return $qb->getQuery()->getResult();
    }

    public function getLastComments(array $filter_params = [], int $offset = 0, int $limit, bool $count_only = false)
    {
        $qb = $this->createQueryBuilder('c');

        if ($count_only) {
            $qb->select('COUNT(1)');
        }
        $qb->leftJoin('c.image', 'i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->leftJoin('c.user', 'u');

        if (!$count_only) {
            $qb->groupBy('c.id, ia.album, u.mail_address');
            $qb->setFirstResult($offset);
            $qb->setMaxResults($limit);
        }

        if (isset($filter_params['forbidden_categories']) && count($filter_params['forbidden_categories']) > 0) {
            $qb->where($qb->expr()->notIn('ia.album', $filter_params['forbidden_categories']));
        }


        if (isset($filter_params['album_ids'])) {
            $qb->where($qb->expr()->in('ia.album', $filter_params['album_ids']));
        }

        if (isset($filter_params['since'])) {
            $qb->andWhere('c.date >= :since');
            $qb->setParameter('since', $filter_params['since']);
        }

        if (isset($filter_params['author'])) {
            $qb->andWhere('c.author = :author');
            $qb->setParameter('author', $filter_params['author']);
        }

        if (isset($filter_params['keywords'])) {
            foreach ($filter_params['keywords'] as $i => $keyword) {
                $qb->andWhere($qb->expr()->like('c.content', ':content' . $i));
                $qb->setParameter('content' . $i, '%' . $keyword . '%');
            }
        }

        $qb->orderBy('c.' . $filter_params['sort_by'], $filter_params['sort_order']);
        if ($count_only) {
            return $qb->getQuery()->getSingleScalarResult();
        } else {
            return $qb->getQuery()->getResult();
        }
    }

    public function countForImage(int $image_id, bool $isAdmin = false) : int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(1)');
        $qb->where('c.image = :image_id');
        $qb->setParameter('image_id', $image_id);
        if (!$isAdmin) {
            $qb->andWhere('c.validated = :validated');
            $qb->setParameter('validated', true);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getCommentsForImagePerPage(int $image_id, int $limit, int $offset = 0)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where('c.image = :image_id');
        $qb->setParameter('image_id', $image_id);
        $qb->orderBy('c.date');
        $qb->setFirstResult($offset);
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function getCommentsOnImage(int $image_id, string $order, int $limit, int $offset = 0, bool $isAdmin = false)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->leftJoin('c.user', 'u');
        $qb->where('c.image = :image_id');
        $qb->setParameter('image_id', $image_id);
        if (!$isAdmin) {
            $qb->andWhere('c.validated = :validated');
            $qb->setParameter('validated', true);
        }
        $qb->orderBy('c.date', $order);
        $qb->setFirstResult($offset);
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function getNewComments(array $forbidden_categories = [], \DateTimeInterface $start = null, \DateTimeInterface $end = null, bool $count_only = false)
    {
        $qb = $this->createQueryBuilder('c');

        if ($count_only) {
            $qb->select('COUNT(1)');
        }

        $qb->leftJoin('c.image', 'i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($forbidden_categories)) {
            $qb->where($qb->expr()->notIn('c.album', $forbidden_categories));
        }

        if (!is_null($start)) {
            $qb->andWhere('c.validation_date > :start');
            $qb->setParameter('start', $start);
        }

        if (!is_null($end)) {
            $qb->andWhere('c.validation_date <= :end');
            $qb->setParameter('end', $end);
        }

        if ($count_only) {
            return $qb->getQuery()->getSingleScalarResult();
        } else {
            return $qb->getQuery()->getResult();
        }
    }

    public function getUnvalidatedComments(\DateTimeInterface $start = null, \DateTimeInterface $end, bool $count_only)
    {
        $qb = $this->createQueryBuilder('c');

        if ($count_only) {
            $qb->select('COUNT(1)');
        }

        $qb->where('c.validated = :validated');
        $qb->setParameter('validated', false);

        if (!is_null($start)) {
            $qb->andWhere('c.validation_date > :start');
            $qb->setParameter('start', $start);
        }

        if (!is_null($end)) {
            $qb->andWhere('c.validation_date <= :end');
            $qb->setParameter('end', $end);
        }

        if ($count_only) {
            return $qb->getQuery()->getSingleScalarResult();
        } else {
            return $qb->getQuery()->getResult();
        }
    }

    public function countAvailableComments(array $forbidden_categories = [], bool $isAdmin = false): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(DISTINCT(c.id))');
        $qb->leftJoin('c.image', 'i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if (count($forbidden_categories) > 0) {
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_categories));
        }

        if (!$isAdmin) {
            $qb->andWhere('c.validated = :validated');
            $qb->setParameter('validated', true);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}
