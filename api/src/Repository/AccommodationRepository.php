<?php

namespace App\Repository;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Enum\Resort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Accommodation>
 */
class AccommodationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Accommodation::class);
    }

    /**
     * @return list<Accommodation>
     */
    public function searchAvailable(
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        int $guests = 1,
        ?Resort $resort = null,
        ?string $district = null,
    ): array {
        $overlapping = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Unavailability::class, 'u')
            ->where('u.accommodation = a');

        StayOverlap::apply($overlapping, 'u');

        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.maxCapacity >= :guests')
            ->andWhere('NOT EXISTS ('.$overlapping->getDQL().')')
            ->orderBy('a.slug', 'ASC')
            ->setParameter('guests', $guests)
            ->setParameter('arrival', $arrival)
            ->setParameter('departure', $departure);

        if (null !== $resort) {
            $qb->andWhere('a.resort = :resort')
                ->setParameter('resort', $resort);
        }

        if (null !== $district) {
            $qb->andWhere('a.district = :district')
               ->setParameter('district', $district);
        }

        /** @var list<Accommodation> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Lowest published weekly price for each accommodation, in cents.
     *
     * @param list<string> $slugs
     *
     * @return array<string, int|null> slug => price, null when no rate is published
     */
    public function findPriceFromBySlugs(array $slugs): array
    {
        if ([] === $slugs) {
            return [];
        }

        /** @var list<array{slug: string, priceFrom: int|string|null}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.slug AS slug', 'MIN(p.weeklyPrice) AS priceFrom')
            ->leftJoin(PricePeriod::class, 'p', Join::WITH, 'p.accommodation = a')
            ->andWhere('a.slug IN (:slugs)')
            ->groupBy('a.slug')
            ->setParameter('slugs', $slugs)
            ->getQuery()
            ->getResult();

        $prices = [];

        foreach ($rows as $row) {
            $prices[$row['slug']] = null === $row['priceFrom'] ? null : (int) $row['priceFrom'];
        }

        return $prices;
    }

    /**
     * @return list<array{district: string, resort: Resort, accommodationCount: int}>
     */
    public function countByDistrict(): array
    {
        /** @var list<array{district: string, resort: Resort|string, accommodationCount: int|string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.district AS district', 'a.resort AS resort', 'COUNT(a.id) AS accommodationCount')
            ->andWhere('a.district IS NOT NULL')
            ->groupBy('a.district')
            ->addGroupBy('a.resort')
            ->orderBy('accommodationCount', 'DESC')
            ->addOrderBy('district', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'district' => $row['district'],
                'resort' => $row['resort'] instanceof Resort
                    ? $row['resort']
                    : Resort::from($row['resort']),
                'accommodationCount' => (int) $row['accommodationCount'],
            ],
            $rows,
        );
    }

    /**
     * Nearest stays of the same length, shifted by up to 14 days,
     * with the number of accommodations free on each.
     *
     * @return list<array{arrival: string, departure: string, available_count: int}>
     */
    public function findNearestAvailableStays(
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        \DateTimeImmutable $today,
        int $guests = 1,
        ?Resort $resort = null,
        ?string $district = null,
    ): array {
        $sql = <<<'SQL'
            WITH slot AS (
                SELECT
                    s.shift,
                    CAST(:arrival AS date)   + s.shift AS arrival,
                    CAST(:departure AS date) + s.shift AS departure
                FROM generate_series(-14, 14) AS s(shift)
                WHERE s.shift <> 0
            )
            SELECT
                slot.arrival,
                slot.departure,
                COUNT(a.id) AS available_count
            FROM slot
            CROSS JOIN accommodation a
            WHERE slot.arrival >= CAST(:today AS date)
              AND a.max_capacity >= :guests
              AND (CAST(:resort AS text)   IS NULL OR a.resort   = :resort)
              AND (CAST(:district AS text) IS NULL OR a.district = :district)
              AND NOT EXISTS (
                  SELECT 1
                  FROM unavailability u
                  WHERE u.accommodation_id = a.id
                    AND u.start_date < slot.departure
                    AND u.end_date   > slot.arrival
              )
            GROUP BY slot.shift, slot.arrival, slot.departure
            ORDER BY abs(slot.shift), slot.shift
            LIMIT 3
            SQL;

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, [
                'arrival' => $arrival->format('Y-m-d'),
                'departure' => $departure->format('Y-m-d'),
                'today' => $today->format('Y-m-d'),
                'guests' => $guests,
                'resort' => $resort?->value,
                'district' => $district,
            ])
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'arrival' => (string) $row['arrival'],
                'departure' => (string) $row['departure'],
                'available_count' => (int) $row['available_count'],
            ],
            $rows,
        );
    }
}
