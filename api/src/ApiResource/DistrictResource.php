<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\District;
use App\Enum\Resort;
use App\State\DistrictCollectionProvider;

#[ApiResource(
    shortName: 'District',
    operations: [
        new GetCollection(
            uriTemplate: '/districts',
            provider: DistrictCollectionProvider::class,
            paginationEnabled: false,
        ),
    ],
)]
final class DistrictResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        public string $name,
        public Resort $resort,
        public ?string $area,
        public int $accommodationCount,
    ) {
    }

    public static function fromEntity(District $district, int $accommodationCount): self
    {
        return new self(
            $district->getSlug(),
            $district->getName(),
            $district->getResort(),
            $district->getArea()?->value,
            $accommodationCount,
        );
    }
}
