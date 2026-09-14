<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * The one and only definition of "this date range overlaps the requested stay".
 *
 * [) bounds: a stay ending on the day another one starts does not overlap.
 * Callers bind the :arrival and :departure parameters themselves.
 */
final class StayOverlap
{
    public static function apply(QueryBuilder $qb, string $alias): void
    {
        $qb->andWhere(sprintf('%s.startDate < :departure', $alias))
            ->andWhere(sprintf('%s.endDate > :arrival', $alias));
    }
}
