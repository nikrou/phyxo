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

use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Language::class);
    }

    public function addLanguage(Language $language)
    {
        $this->_em->persist($language);
        $this->_em->flush();
    }

    public function updateVersion(string $language_id, string $version)
    {
        $qb = $this->createQueryBuilder('l');
        $qb->update();
        $qb->set('version', ':version');
        $qb->where('l.id = :id');
        $qb->setParameter('id', $language_id);
        $qb->setParameter('version', $version);

        return $qb->getQuery()->getResult();
    }

    public function deleteById(string $language_id)
    {
        $qb = $this->createQueryBuilder('l');
        $qb->where('l.id = :id');
        $qb->setParameter('id', $language_id);
        $qb->delete();

        return $qb->getQuery()->getResult();
    }
}
