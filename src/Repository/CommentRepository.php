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
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function addOrUpdateComment(Comment $comment): int
    {
        $this->getEntityManager()->persist($comment);
        $this->getEntityManager()->flush();

        return $comment->getId();
    }

    public function doestAuthorPostMessageAfterThan(int $user_id, DateTimeInterface $anti_flood_time, ?string $anonymous_id = null): bool
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

        return (int) $qb->getQuery()->getSingleScalarResult() === 1;
    }

    /**
     * @param int[] $comment_ids
     */
    public function deleteByIds(array $comment_ids, ?int $user_id = null): void
    {
        $qb = $this->createQueryBuilder('c');
        $qb->delete();
        $qb->where($qb->expr()->in('c.id', $comment_ids));

        if (!is_null($user_id)) {
            $qb->andWhere('c.user = :user_id');
            $qb->setParameter('user_id', $user_id);
        }

        $qb->getQuery()->getResult();
    }

    public function deleteByUserId(int $user_id): void
    {
        $qb = $this->createQueryBuilder('c');
        $qb->delete();
        $qb->where('c.user = :user_id');
        $qb->setParameter('user_id', $user_id);

        $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $image_ids
     */
    public function deleteByImage(array $image_ids): void
    {
        $qb = $this->createQueryBuilder('c');
        $qb->delete();
        $qb->where($qb->expr()->in('c.image', $image_ids));

        $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $comment_ids
     */
    public function validateUserComment(array $comment_ids): void
    {
        $qb = $this->createQueryBuilder('c');
        $qb->update();
        $qb->set('c.validated', ':validated');
        $qb->setParameter('validated', true);
        $qb->set('c.validation_date', ':validation_date');
        $qb->setParameter('validation_date', new DateTime());
        $qb->where($qb->expr()->in('c.id', $comment_ids));

        $qb->getQuery()->getResult();
    }

    /**
     * @return Comment[]
     */
    public function getCommentsOnImages(bool $validated, int $limit, int $offset = 0)
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

    /**
     * @param int[] $image_ids
     *
     * @return array<array{image_id: int, nb_comments: int}>
     */
    public function countGroupByImage(array $image_ids, bool $validated = true)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('IDENTITY(c.image) AS image_id, COUNT(1) AS nb_comments');
        $qb->where('c.validated = :validated');
        $qb->setParameter('validated', $validated);
        $qb->andWhere($qb->expr()->in('c.image', $image_ids));
        $qb->groupBy('c.image');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<array{counter: int, validated: bool}>
     */
    public function countGroupByValidated()
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(1) AS counter, c.validated');
        $qb->groupBy('c.validated');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, string|int[]> $filter_params
     *
     * @return Comment[]|int
     */
    public function getLastComments(array $filter_params = [], int $offset = 0, int $limit = 0, bool $count_only = false): array|int
    {
        $qb = $this->createQueryBuilder('c');

        if ($count_only) {
            $qb->select('COUNT(1)');
        }

        $qb->leftJoin('c.image', 'i');
        $qb->leftJoin('i.imageAlbums', 'ia');
        $qb->leftJoin('c.user', 'u');

        if (!$count_only) {
            $qb->setFirstResult($offset);
            $qb->setMaxResults($limit);
        }

        if (isset($filter_params['forbidden_categories']) && (is_countable($filter_params['forbidden_categories']) ? count($filter_params['forbidden_categories']) : 0) > 0) {
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

        if (!$count_only) {
            $qb->groupBy('c.id, ia.album, u.mail_address, c.date');
            $qb->orderBy('c.' . $filter_params['sort_by'], $filter_params['sort_order']);
        }

        if ($count_only) {
            return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR) ?? 0;
        } else {
            /* @phpstan-ignore-next-line */
            return $qb->getQuery()->getResult();
        }
    }

    public function countForImage(int $image_id, bool $isAdmin = false): int
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

    /**
     * @return Comment[]
     */
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

    /**
     * @return Comment[]
     */
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

    /**
     * @param int[] $forbidden_albums
     *
     * @return Comment[]|int
     */
    public function getNewComments(array $forbidden_albums = [], ?DateTimeInterface $start = null, ?DateTimeInterface $end = null, bool $count_only = false): array|int
    {
        $qb = $this->createQueryBuilder('c');

        if ($count_only) {
            $qb->select('COUNT(1)');
        }

        $qb->leftJoin('c.image', 'i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if ($forbidden_albums !== []) {
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_albums));
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

    /**
     * @return Comment[]|int
     */
    public function getUnvalidatedComments(bool $count_only, ?DateTimeInterface $start = null, ?DateTimeInterface $end = null): array|int
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

    /**
     * @param int[] $forbidden_albums
     */
    public function countAvailableComments(array $forbidden_albums = [], bool $isAdmin = false): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(DISTINCT(c.id))');
        $qb->leftJoin('c.image', 'i');
        $qb->leftJoin('i.imageAlbums', 'ia');

        if ($forbidden_albums !== []) {
            $qb->where($qb->expr()->notIn('ia.album', $forbidden_albums));
        }

        if (!$isAdmin) {
            $qb->andWhere('c.validated = :validated');
            $qb->setParameter('validated', true);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}
