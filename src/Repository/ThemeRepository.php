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
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function addTheme(Theme $theme): void
    {
        $this->_em->persist($theme);
        $this->_em->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Theme[]
     */
    public function findExcept(array $ids)
    {
        $qb = $this->createQueryBuilder('t');
        $qb->where($qb->expr()->notIn('id', $ids));

        return $qb->getQuery()->getResult();
    }

    public function deleteById(string $theme_id): void
    {
        $this->deleteByIds([$theme_id]);
    }

    /**
     * @param string[] $theme_ids
     */
    public function deleteByIds(array $theme_ids): void
    {
        $qb = $this->createQueryBuilder('t');
        $qb->where($qb->expr()->in('t.id', $theme_ids));
        $qb->delete();

        $qb->getQuery()->getResult();
    }
}
