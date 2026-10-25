<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StayAlert;
use App\Enum\Resort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StayAlert>
 */
class StayAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StayAlert::class);
    }

    /** The same search left twice by the same address: one alert, not two. */
    public function findSame(string $email, \DateTimeImmutable $arrival, \DateTimeImmutable $departure, int $guests, ?Resort $resort): ?StayAlert
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.email = :email')
            ->andWhere('s.arrival = :arrival')
            ->andWhere('s.departure = :departure')
            ->andWhere('s.guests = :guests')
            ->setParameter('email', $email)
            ->setParameter('arrival', $arrival, 'date_immutable')
            ->setParameter('departure', $departure, 'date_immutable')
            ->setParameter('guests', $guests)
            ->setMaxResults(1);

        null === $resort
            ? $qb->andWhere('s.resort IS NULL')
            : $qb->andWhere('s.resort = :resort')->setParameter('resort', $resort);

        /** @var StayAlert|null $alert */
        $alert = $qb->getQuery()->getOneOrNullResult();

        return $alert;
    }

    public function countForEmail(string $email): int
    {
        return $this->count(['email' => $email]);
    }

    /**
     * Confirmed alerts whose stay has not started yet.
     *
     * @return list<StayAlert>
     */
    public function findActive(\DateTimeImmutable $today): array
    {
        /** @var list<StayAlert> $alerts */
        $alerts = $this->createQueryBuilder('s')
            ->where('s.confirmedAt IS NOT NULL')
            ->andWhere('s.arrival > :today')
            ->setParameter('today', $today, 'date_immutable')
            ->orderBy('s.arrival', 'ASC')
            ->getQuery()
            ->getResult();

        return $alerts;
    }

    /**
     * What guests are waiting for, by month of arrival and resort: the figure to show owners
     * (« 12 familles cherchent encore une semaine en juillet »). Counts only, no address.
     *
     * @return list<array{month: string, resort: string|null, alerts: int}>
     */
    public function demandByMonth(\DateTimeImmutable $today): array
    {
        /** @var list<array{month: string, resort: string|null, alerts: int|string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT to_char(arrival, 'YYYY-MM') AS month, resort, count(*) AS alerts
                FROM stay_alert
                WHERE confirmed_at IS NOT NULL AND arrival > :today
                GROUP BY 1, 2
                ORDER BY 1, 2 NULLS FIRST
                SQL,
            ['today' => $today->format('Y-m-d')],
        );

        return array_map(static fn (array $row): array => [
            'month' => $row['month'],
            'resort' => $row['resort'],
            'alerts' => (int) $row['alerts'],
        ], $rows);
    }
}
