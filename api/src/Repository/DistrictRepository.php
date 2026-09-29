<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Accommodation;
use App\Entity\District;
use App\Enum\Resort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<District>
 */
class DistrictRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, District::class);
    }

    /**
     * Names of the CHM districts, in plan order: the only ones the listing import may pick.
     * Euronat's sectors stay out: "Europe", "Asie", "Afrique" are ordinary words a model would
     * pick from any text ("clientèle européenne"), "Europe" is one letter away from the CHM's
     * "Europa", and "Polynésie" exists in both resorts. At Euronat, the owner picks his sector.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(
            static fn (District $district): string => $district->getName(),
            $this->findBy(['resort' => Resort::Chm], ['position' => 'ASC']),
        );
    }

    /**
     * Every district, including those without any accommodation yet.
     *
     * @return list<array{district: District, accommodationCount: int}>
     */
    public function findAllWithAccommodationCount(): array
    {
        /** @var list<array{0: District, accommodationCount: int|string}> $rows */
        $rows = $this->createQueryBuilder('d')
            ->select('d', 'COUNT(a.id) AS accommodationCount')
            ->leftJoin(Accommodation::class, 'a', Join::WITH, 'a.district = d')
            ->groupBy('d.id')
            // CHM first, then Euronat, each in plan order: positions restart at 0 per resort.
            ->orderBy('d.resort', 'ASC')
            ->addOrderBy('d.position', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'district' => $row[0],
                'accommodationCount' => (int) $row['accommodationCount'],
            ],
            $rows,
        );
    }
}
