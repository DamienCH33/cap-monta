<?php

namespace App\Repository;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unavailability>
 */
class UnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unavailability::class);
    }

    public function hasOverlap(
        Accommodation $accommodation,
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
    ): bool {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.accommodation = :accommodation')
            ->setParameter('accommodation', $accommodation)
            ->setParameter('arrival', $arrival)
            ->setParameter('departure', $departure);

        StayOverlap::apply($qb, 'u');

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Unavailabilities touching the window, including those starting before it.
     *
     * StayOverlap binds :arrival and :departure, hence the parameter names.
     *
     * @return list<Unavailability>
     */
    public function findForPeriod(
        Accommodation $accommodation,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.accommodation = :accommodation')
            ->orderBy('u.startDate', 'ASC')
            ->setParameter('accommodation', $accommodation)
            ->setParameter('arrival', $from)
            ->setParameter('departure', $to);

        StayOverlap::apply($qb, 'u');

        /** @var list<Unavailability> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
