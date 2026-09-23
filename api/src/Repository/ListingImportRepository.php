<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ListingImport;
use App\Entity\User;
use App\Enum\ListingImportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<ListingImport>
 */
class ListingImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListingImport::class);
    }

    /**
     * The same text from the same owner, still running or read successfully not long ago:
     * returned instead of paying (or queueing) the same reading twice.
     */
    public function findReusable(User $owner, string $text, \DateTimeImmutable $since): ?ListingImport
    {
        return $this->createQueryBuilder('i')
            ->where('i.owner = :owner')
            ->andWhere('i.textHash = :hash')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('owner', $owner->getId(), UuidType::NAME)
            ->setParameter('hash', ListingImport::hash($text))
            ->setParameter('statuses', [ListingImportStatus::Pending->value, ListingImportStatus::Done->value])
            ->setParameter('since', $since)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
