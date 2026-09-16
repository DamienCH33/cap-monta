<?php

namespace App\Repository;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricePeriod>
 */
class PricePeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricePeriod::class);
    }

    /**
     * Price periods overlapping the stay, in chronological order.
     *
     * @return list<PricePeriod>
     */
    public function findCoveringStay(
        Accommodation $accommodation,
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.accommodation = :accommodation')
            ->orderBy('p.startDate', 'ASC')
            ->setParameter('accommodation', $accommodation)
            ->setParameter('arrival', $arrival)
            ->setParameter('departure', $departure);

        StayOverlap::apply($qb, 'p');

        /** @var list<PricePeriod> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Les périodes tarifaires encore d'actualité, de la plus proche à la plus lointaine.
     *
     * @return list<PricePeriod>
     */
    public function findUpcoming(Accommodation $accommodation, \DateTimeImmutable $from): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.accommodation = :accommodation')
            ->andWhere('p.endDate > :from')
            ->orderBy('p.startDate', 'ASC')
            ->setParameter('accommodation', $accommodation)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE);

        /** @var list<PricePeriod> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
