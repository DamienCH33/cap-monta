<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BookingRequest;
use App\Entity\User;
use App\Enum\BookingRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<BookingRequest>
 */
class BookingRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingRequest::class);
    }

    /**
     * Every request on the owner's accommodations, the most recent first.
     *
     * @return list<BookingRequest>
     */
    public function findForOwner(User $owner): array
    {
        /** @var list<BookingRequest> $result */
        $result = $this->createQueryBuilder('b')
            ->addSelect('a')
            ->join('b.accommodation', 'a')
            ->andWhere('a.owner = :owner')
            ->orderBy('b.createdAt', 'DESC')
            ->setParameter('owner', $owner->getId(), UuidType::NAME)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * The other requests still waiting on the same accommodation for dates that overlap.
     *
     * @return list<BookingRequest>
     */
    public function findPendingOverlapping(BookingRequest $request): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.accommodation = :accommodation')
            ->andWhere('b.status = :pending')
            ->andWhere('b.id != :id')
            ->setParameter('accommodation', $request->getAccommodation())
            ->setParameter('pending', BookingRequestStatus::Pending)
            ->setParameter('id', $request->getId(), UuidType::NAME)
            ->setParameter('arrival', $request->getStartDate())
            ->setParameter('departure', $request->getEndDate());

        StayOverlap::apply($qb, 'b');

        /** @var list<BookingRequest> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Pending requests past their deadline or whose arrival day has come: the command's catch-up
     * when the delayed message was lost (worker stopped, deploy...).
     *
     * @return list<BookingRequest>
     */
    public function findDueForExpiry(\DateTimeImmutable $now): array
    {
        /** @var list<BookingRequest> $result */
        $result = $this->createQueryBuilder('b')
            ->andWhere('b.status = :pending')
            ->andWhere('b.expiresAt <= :now OR b.startDate <= :today')
            ->setParameter('pending', BookingRequestStatus::Pending)
            ->setParameter('now', $now)
            ->setParameter('today', $now->setTime(0, 0), 'date_immutable')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findOneByTrackingToken(string $token): ?BookingRequest
    {
        return $this->findOneBy(['trackingToken' => $token]);
    }
}
