<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\DistrictResource;
use App\Repository\DistrictRepository;

/**
 * @implements ProviderInterface<DistrictResource>
 */
final readonly class DistrictCollectionProvider implements ProviderInterface
{
    public function __construct(private DistrictRepository $districts)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<DistrictResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (array $row): DistrictResource => DistrictResource::fromEntity(
                $row['district'],
                $row['accommodationCount'],
            ),
            $this->districts->findAllWithAccommodationCount(),
        );
    }
}
