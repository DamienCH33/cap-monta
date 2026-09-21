<?php

namespace App\Repository;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\AccommodationStatus;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

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
     * Une fiche telle que le public la voit : un brouillon ou une annonce
     * archivée n'existe pas pour lui.
     *
     * Une vraie méthode plutôt que le findOneBySlug() magique de Doctrine :
     * une lecture qui porte une règle métier doit pouvoir la nommer.
     */
    public function findOnePublishedBySlug(string $slug): ?Accommodation
    {
        return $this->findOneBy([
            'slug' => $slug,
            'status' => AccommodationStatus::Published,
        ]);
    }

    /**
     * Kept for existing callers: a search on dates, with at most one district.
     *
     * @return list<Accommodation>
     */
    public function searchAvailable(
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        int $guests = 1,
        ?Resort $resort = null,
        ?string $district = null,
    ): array {
        return $this->search(
            arrival: $arrival,
            departure: $departure,
            guests: $guests,
            resort: $resort,
            districts: null === $district ? [] : [$district],
        );
    }

    /**
     * The public search. Dates are optional; every list filter means "any of",
     * except amenities, which the accommodation must all have.
     *
     * @param list<string>            $districts
     * @param list<AccommodationType> $types
     * @param list<string>            $amenities
     *
     * @return list<Accommodation>
     */
    public function search(
        ?\DateTimeImmutable $arrival = null,
        ?\DateTimeImmutable $departure = null,
        int $guests = 1,
        ?Resort $resort = null,
        array $districts = [],
        array $types = [],
        int $bedrooms = 0,
        array $amenities = [],
    ): array {
        // Les deux règles toujours vraies : assez grand, et publié.
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.maxCapacity >= :guests')
            ->andWhere('a.status = :published')
            ->setParameter('guests', $guests)
            ->setParameter('published', AccommodationStatus::Published)
            ->orderBy('a.slug', 'ASC');

        if (null !== $arrival && null !== $departure) {
            $overlapping = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(Unavailability::class, 'u')
                ->where('u.accommodation = a');

            StayOverlap::apply($overlapping, 'u');

            $qb->andWhere('NOT EXISTS ('.$overlapping->getDQL().')')
                ->setParameter('arrival', $arrival)
                ->setParameter('departure', $departure);
        }

        if (null !== $resort) {
            $qb->andWhere('a.resort = :resort')
                ->setParameter('resort', $resort);
        }

        if ([] !== $districts) {
            $qb->innerJoin('a.district', 'd')
                ->andWhere('d.slug IN (:districtSlugs) OR d.name IN (:districtNames)')
                ->setParameter('districtSlugs', $districts, ArrayParameterType::STRING)
                ->setParameter('districtNames', $districts, ArrayParameterType::STRING);
        }

        if ([] !== $types) {
            $qb->andWhere('a.type IN (:types)')
                ->setParameter(
                    'types',
                    array_map(static fn (AccommodationType $type): string => $type->value, $types),
                    ArrayParameterType::STRING,
                );
        }

        if ($bedrooms > 0) {
            $qb->andWhere('a.bedrooms >= :bedrooms')
                ->setParameter('bedrooms', $bedrooms);
        }

        if ([] !== $amenities) {
            $ids = $this->idsWithAllAmenities($amenities);

            if ([] === $ids) {
                return [];
            }

            $qb->andWhere('a.id IN (:ids)')
                ->setParameter('ids', $ids, ArrayParameterType::STRING);
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
     * Nearest stays of the same length, shifted by up to 14 days,
     * with the number of accommodations free on each.
     *
     * The criteria that do not depend on dates are delegated to search(),
     * so both queries can never disagree on what "matching" means.
     *
     * @param list<string>            $districts
     * @param list<AccommodationType> $types
     * @param list<string>            $amenities
     *
     * @return list<array{arrival: string, departure: string, available_count: int}>
     */
    public function findNearestAvailableStays(
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        \DateTimeImmutable $today,
        int $guests = 1,
        ?Resort $resort = null,
        array $districts = [],
        array $types = [],
        int $bedrooms = 0,
        array $amenities = [],
    ): array {
        $candidates = $this->search(
            guests: $guests,
            resort: $resort,
            districts: $districts,
            types: $types,
            bedrooms: $bedrooms,
            amenities: $amenities,
        );

        if ([] === $candidates) {
            return [];
        }

        $ids = array_map(
            static fn (Accommodation $accommodation): string => (string) $accommodation->getId(),
            $candidates,
        );

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
              AND a.id IN (:ids)
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
            ->executeQuery(
                $sql,
                [
                    'arrival' => $arrival->format('Y-m-d'),
                    'departure' => $departure->format('Y-m-d'),
                    'today' => $today->format('Y-m-d'),
                    'ids' => $ids,
                ],
                [
                    'ids' => ArrayParameterType::STRING,
                ],
            )
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

    /**
     * DQL knows nothing about JSON, so this one question goes to PostgreSQL directly.
     *
     * @param list<string> $amenities
     *
     * @return list<string>
     */
    private function idsWithAllAmenities(array $amenities): array
    {
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM accommodation WHERE CAST(amenities AS jsonb) @> CAST(:amenities AS jsonb)',
            ['amenities' => json_encode($amenities, JSON_THROW_ON_ERROR)],
        );

        return $ids;
    }

    /**
     * Every accommodation of an owner, drafts and archived included, for his
     * own space. Never for a public page: use search() or findOnePublishedBySlug().
     *
     * @return list<Accommodation>
     */
    public function findByOwner(User $owner): array
    {
        /** @var list<Accommodation> $result */
        $result = $this->createQueryBuilder('a')
            ->leftJoin('a.district', 'd')
            ->addSelect('d')
            ->andWhere('a.owner = :owner')
            ->setParameter('owner', $owner->getId(), UuidType::NAME)
            ->orderBy('a.slug', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
