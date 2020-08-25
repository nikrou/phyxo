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

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;

/**
 * @method EntityManagerInterface getEntityManager()
 */
trait BaseRepositoryTrait
{
    /**
     * This is just an example. Use ->count([]) for the same result.
     */
    public function countAll(): int
    {
        return (int) $this->getEntityManager()
            ->createQuery(sprintf('SELECT COUNT(a) FROM %s a', $this->getClassName()))
            ->getSingleScalarResult();
    }
}
