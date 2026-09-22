<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * The cover (position 0) of each accommodation, indexed by slug.
     *
     * One query for a whole page of search results: going through
     * Accommodation::getPhotos() would load every photo, one query per card.
     *
     * @param list<string> $slugs
     *
     * @return array<string, Photo>
     */
    public function findCoversBySlugs(array $slugs): array
    {
        if ([] === $slugs) {
            return [];
        }

        /** @var list<array{0: Photo, slug: string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p', 'a.slug AS slug')
            ->join('p.accommodation', 'a')
            ->andWhere('a.slug IN (:slugs)')
            ->andWhere('p.position = 0')
            ->setParameter('slugs', $slugs)
            ->getQuery()
            ->getResult();

        $covers = [];

        foreach ($rows as $row) {
            $covers[$row['slug']] = $row[0];
        }

        return $covers;
    }
}
