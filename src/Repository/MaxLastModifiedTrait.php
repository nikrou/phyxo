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

use DateTimeInterface;
use Doctrine\ORM\AbstractQuery;

trait MaxLastModifiedTrait
{
    /**
     * @return array{max: ?DateTimeInterface, count: int}
     */
    public function getMaxLastModified()
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('MAX(a.last_modified) as max, COUNT(1) as count');

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }
}
