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

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function addTheme(Theme $theme)
    {
        $this->_em->persist($theme);
        $this->_em->flush($theme);
    }

    public function findExcept(array $ids)
    {
        $qb = $this->createQueryBuilder('t');
        $qb->where($qb->expr()->notIn('id', $ids));

        return $qb->getQuery()->getResult();
    }

    public function deleteById(string $theme_id)
    {
        return $this->deleteByIds([$theme_id]);
    }

    public function deleteByIds(array $theme_ids)
    {
        $qb = $this->createQueryBuilder('t');
        $qb->where($qb->expr()->in('id', $theme_ids));
        $qb->delete();

        return $qb->getQuery()->getResult();
    }
}
