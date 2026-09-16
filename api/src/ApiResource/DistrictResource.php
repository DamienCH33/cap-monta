<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\Resort;
use App\State\DistrictCollectionProvider;

#[ApiResource(
    shortName: 'District',
    operations: [
        new GetCollection(
            uriTemplate: '/districts',
            provider: DistrictCollectionProvider::class,
        ),
    ],
)]
final class DistrictResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $name,
        public Resort $resort,
        public int $accommodationCount,
    ) {
    }
}
