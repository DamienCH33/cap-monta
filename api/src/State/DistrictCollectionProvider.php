<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\DistrictResource;
use App\Repository\AccommodationRepository;

/**
 * @implements ProviderInterface<DistrictResource>
 */
final readonly class DistrictCollectionProvider implements ProviderInterface
{
    public function __construct(private AccommodationRepository $accommodations)
    {
    }

    /**
     * @return list<DistrictResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (array $row): DistrictResource => new DistrictResource(
                $row['district'],
                $row['resort'],
                (int) $row['accommodationCount'],
            ),
            $this->accommodations->countByDistrict(),
        );
    }
}
