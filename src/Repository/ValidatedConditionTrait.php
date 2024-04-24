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

use Doctrine\ORM\QueryBuilder;

trait ValidatedConditionTrait
{
    protected function addValidatedCondition(QueryBuilder $qb, int $user_id, bool $show_pending_added_tags = false, bool $show_pending_deleted_tags = false): QueryBuilder
    {
        if ($show_pending_added_tags) {
            $addedValidated = $qb->expr()->orX(
                $qb->expr()->eq('it.validated', ':validated_status_1'),
                $qb->expr()->eq('it.created_by', ':user_id_1')
            );
            $qb->setParameter('validated_status_1', true);
            $qb->setParameter('user_id_1', $user_id);
        } else {
            $addedValidated = $qb->expr()->eq('it.validated', ':validated_status_1');
            $qb->setParameter('validated_status_1', true);
        }

        if ($show_pending_deleted_tags) {
            $deletedAndNotValidated = $qb->expr()->andX(
                $qb->expr()->orX(
                    $qb->expr()->eq('it.validated', ':validated_status_0'),
                    $qb->expr()->eq('it.created_by', ':user_id_0')
                ),
                $qb->expr()->eq('it.status', 0)
            );
            $qb->setParameter('validated_status_0', false);
            $qb->setParameter('user_id_0', $user_id);
        } else {
            $deletedAndNotValidated = null;
        }

        $addedAndValidated = $qb->expr()->andX(
            $addedValidated,
            $qb->expr()->eq('it.status', 1)
        );

        $orConditions = [$addedAndValidated];

        if ($deletedAndNotValidated) {
            $orConditions[] = $deletedAndNotValidated;
        }

        $qb->andWhere($qb->expr()->orX(...$orConditions));

        return $qb;
    }
}
