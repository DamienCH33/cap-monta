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
}
