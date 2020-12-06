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

use App\Entity\ImageTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImageTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageTag::class);
    }

    public function addOrUpdateImageTag(ImageTag $image_tag)
    {
        $this->_em->persist($image_tag);
        $this->_em->flush();
    }

    public function getTagCounters()
    {
        $qb = $this->createQueryBuilder('it');
        $qb->select('IDENTITY(it.tag) AS tag_id, COUNT(it.image) AS counter');
        $qb->groupBy('it.tag');

        return $qb->getQuery()->getResult();
    }

    public function deleteByImagesAndTags(array $images, array $tags)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->delete();
        $qb->where($qb->expr()->in('it.image', $images));
        $qb->andWhere($qb->expr()->in('it.tag', $tags));

        $qb->getQuery()->getResult();
    }

    public function deleteByImageIds(array $image_ids)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->delete();
        $qb->where($qb->expr()->in('it.image', $image_ids));

        $qb->getQuery()->getResult();
    }

    public function deleteForImage(int $image_id)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->delete();
        $qb->where('it.image = :image_id');
        $qb->setParameter('image_id', $image_id);

        $qb->getQuery()->getResult();
    }

    public function deleteImageTags(array $datas)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->delete();

        $bind_param_index = 0;
        foreach ($datas as $image_id => $tag_id) {
            $bind_param_index++;
            $qb->orWhere(
                $qb->expr()->andX(
                    $qb->expr()->eq('it.image', ':image_id' . $bind_param_index),
                    $qb->expr()->eq('it.tag', ':tag_id' . $bind_param_index)
                )
            );
            $qb->setParameter('image_id' . $bind_param_index, $image_id);
            $qb->setParameter('tag_id' . $bind_param_index, $tag_id);
        }

        $qb->getQuery()->getResult();
    }

    public function deleteByImageAndTags(int $image_id, array $tags)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->delete();
        $qb->where('it.image = :image_id');
        $qb->setParameter('image_id', $image_id);
        $qb->andWhere($qb->expr()->in('it.tag', $tags));

        $qb->getQuery()->getResult();
    }

    public function validatedImageTags(array $datas)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->update();
        $qb->set('it.validated', ':validated');
        $qb->setParameter('validated', true);

        $bind_param_index = 0;
        foreach ($datas as $image_id => $tag_id) {
            $bind_param_index++;
            $qb->orWhere(
                $qb->expr()->andX(
                    $qb->expr()->eq('it.image', ':image_id' . $bind_param_index),
                    $qb->expr()->eq('it.tag', ':tag_id' . $bind_param_index)
                )
            );
            $qb->setParameter('image_id' . $bind_param_index, $image_id);
            $qb->setParameter('tag_id' . $bind_param_index, $tag_id);
        }

        $qb->getQuery()->getResult();
    }

    public function findImageTags(array $tag_ids, array $image_ids)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->where($qb->expr()->in('it.tag', $tag_ids));
        $qb->andWhere($qb->expr()->in('it.image', $image_ids));
        $qb->orderBy('it.image');

        return $qb->getQuery()->getResult();
    }

    public function findImageByTags(array $tag_ids)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->where($qb->expr()->in('it.tag', $tag_ids));

        return $qb->getQuery()->getResult();
    }

    public function findImageIds()
    {
        $qb = $this->createQueryBuilder('it');
        $qb->select('DISTINCT(IDENTITY(it.image)) AS image_id');

        return $qb->getQuery()->getResult();
    }

    public function deleteByTagIds(array $ids)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->delete();
        $qb->where($qb->expr()->in('it.tag', $ids));

        $qb->getQuery()->getResult();
    }

    public function deleteMarkDeletedAndValidated()
    {
        $qb = $this->createQueryBuilder('it');
        $qb->delete();
        $qb->where('it.status = :status');
        $qb->setParameter('status', ImageTag::STATUS_TO_DELETE);
        $qb->andWhere('it.validated = :validated');
        $qb->setParameter('validated', true);

        $qb->getQuery()->getResult();
    }

    public function removeCreatedByKey(int $user_id)
    {
        $qb = $this->createQueryBuilder('it');
        $qb->update();
        $qb->set('it.created_by', ':null');
        $qb->setParameter('null', null);

        $qb->where('it.created_by = :created_by');
        $qb->setParameter('created_by', $user_id);

        $qb->getQuery()->getResult();
    }
}
