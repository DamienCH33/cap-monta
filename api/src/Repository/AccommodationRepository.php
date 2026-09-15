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
        int $guests,
        ?Resort $resort = null,
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
}
