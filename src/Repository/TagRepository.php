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

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    use MaxLastModifiedTrait;

    use ValidatedConditionTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function addOrUpdateTag(Tag $tag): int
    {
        $this->getEntityManager()->persist($tag);
        $this->getEntityManager()->flush();

        return $tag->getId();
    }

    /**
     * @return Tag[]
     */
    public function searchAll(string $q = '')
    {
        $qb = $this->createQueryBuilder('t');
        if (!empty($q)) {
            $qb->where($qb->expr()->like("lower('t.name')", ':q'));
            $qb->setParameter('q', '%' . strtolower($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $names
     * @param string[] $url_names
     *
     * @return Tag[]
     */
    public function findByNamesOrUrlNames(array $names, array $url_names)
    {
        return $this->findByIdsOrNamesOrUrlNames([], $names, $url_names);
    }

    /**
     * @param int[] $ids
     * @param string[] $names
     * @param string[] $url_names
     *
     * @return Tag[]
     */
    public function findByIdsOrNamesOrUrlNames(array $ids = [], array $names = [], array $url_names = [])
    {
        $qb = $this->createQueryBuilder('t');

        if (count($ids) > 0) {
            $qb->where($qb->expr()->in('t.id', $ids));
        }

        if (count($names) > 0) {
            $qb->orWhere($qb->expr()->in('t.name', $names));
        }

        if (count($url_names) > 0) {
            $qb->orWhere($qb->expr()->in('t.url_name', $url_names));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $items
     * @param int[] $excluded_tag_ids
     *
     * @return array<array<string|int, Tag|int|string>>
     */
    public function getCommonTags(int $user_id, array $items, int $max_tags, array $excluded_tag_ids = [])
    {
        $qb = $this->createQueryBuilder('t');
        $qb->leftJoin('t.imageTags', 'it');
        $qb->addSelect('COUNT(1) AS counter');

        if (count($items) > 0) {
            $qb->where($qb->expr()->in('it.image', $items));
        }

        $this->addValidatedCondition($qb, $user_id);

        if (count($excluded_tag_ids) > 0) {
            $qb->andWhere($qb->expr()->notIn('t.id', $excluded_tag_ids));
        }

        $qb->groupBy('it.validated, it.created_by, it.status, t.id');

        if ($max_tags > 0) {
            $qb->orderBy('counter', 'DESC');
            $qb->setMaxResults($max_tags);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Tag[]
     */
    public function getRelatedTags(int $user_id, int $image_id, int $max_tags, bool $show_pending_added_tags, bool $show_pending_deleted_tags = false)
    {
        $qb = $this->createQueryBuilder('t');
        $qb->leftJoin('t.imageTags', 'it');
        $qb->addSelect('it');
        $qb->where('it.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        $this->addValidatedCondition($qb, $user_id, $show_pending_added_tags, $show_pending_deleted_tags);

        if ($max_tags > 0) {
            $qb->setMaxResults($max_tags);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Tag[]
     */
    public function getOrphanTags()
    {
        $qb = $this->createQueryBuilder('t');
        $qb->leftJoin('t.imageTags', 'it');
        $qb->where($qb->expr()->isNull('it.tag'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<array<string, string|int>>
     */
    public function findImageWithNoTag()
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('IDENTITY(it.image) AS image_id');
        $qb->leftJoin('t.imageTags', 'it');
        $qb->where($qb->expr()->isNull('it.tag'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $ids
     */
    public function deleteTags(array $ids): void
    {
        $qb = $this->createQueryBuilder('t');
        $qb->delete();
        $qb->where($qb->expr()->in('t.id', $ids));

        $qb->getQuery()->getResult();
    }

    public function delete(Tag $tag): void
    {
        $this->getEntityManager()->remove($tag);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Tag[]
     */
    public function getTagsByImage(int $image_id, ? bool $validated = null)
    {
        $qb = $this->createQueryBuilder('t');
        $qb->leftJoin('t.imageTags', 'it');
        $qb->where('it.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        if (!is_null($validated)) {
            $qb->andWhere('it.validated = :validated');
            $qb->setParameter('validated', $validated);
        }

        return $qb->getQuery()->getResult();
    }
}
