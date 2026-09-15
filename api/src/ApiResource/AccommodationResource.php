<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Accommodation;
use App\State\AccommodationCollectionProvider;
use App\State\AccommodationItemProvider;

/**
 * What the public API exposes of an accommodation.
 *
 * A resource, not the entity: the owner's email, the internal identifier and the
 * timestamps stay in the domain. Adding a field here is a deliberate act.
 */
#[ApiResource(
    shortName: 'Accommodation',
    operations: [
        new GetCollection(
            uriTemplate: '/accommodations',
            provider: AccommodationCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/accommodations/{slug}',
            provider: AccommodationItemProvider::class,
        ),
    ],
)]
final class AccommodationResource
{
    /**
     * @param list<string> $amenities
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        public string $resort,
        public string $type,
        public ?string $district,
        public int $capacity,
        public int $maxCapacity,
        public int $bedrooms,
        public ?int $surface,
        public array $amenities,
        public string $description,
    ) {
    }

    public static function fromEntity(Accommodation $accommodation): self
    {
        return new self(
            $accommodation->getSlug(),
            $accommodation->getResort()->value,
            $accommodation->getType()->value,
            $accommodation->getDistrict(),
            $accommodation->getCapacity(),
            $accommodation->getMaxCapacity(),
            $accommodation->getBedrooms(),
            $accommodation->getSurface(),
            $accommodation->getAmenities(),
            $accommodation->getDescription(),
        );
    }
}
