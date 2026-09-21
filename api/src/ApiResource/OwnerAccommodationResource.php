<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\CreateAccommodationInput;
use App\Entity\Accommodation;
use App\State\CreateOwnerAccommodationProcessor;
use App\State\OwnerAccommodationCollectionProvider;
use App\State\OwnerAccommodationItemProvider;

/**
 * What an owner sees of his own accommodations: every status, drafts included.
 * Never used on a public page.
 *
 * Access: access_control on ^/api/owner (401 without a session), then the
 * AccommodationVoter on each item (404 when it belongs to someone else, so a
 * draft's slug never leaks).
 */
#[ApiResource(
    shortName: 'OwnerAccommodation',
    // Session cookie auth (lot 3a): the stateless default would turn every session read into a 500.
    stateless: false,
    operations: [
        new GetCollection(
            uriTemplate: '/owner/accommodations',
            paginationEnabled: false,
            provider: OwnerAccommodationCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/owner/accommodations/{slug}',
            provider: OwnerAccommodationItemProvider::class,
        ),
        new Post(
            uriTemplate: '/owner/accommodations',
            input: CreateAccommodationInput::class,
            processor: CreateOwnerAccommodationProcessor::class,
        ),
    ],
)]
final class OwnerAccommodationResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        public string $status,
        public string $resort,
        public string $type,
        public ?string $district,
        public int $capacity,
        public int $bedrooms,
    ) {
    }

    public static function fromEntity(Accommodation $accommodation): self
    {
        return new self(
            $accommodation->getSlug(),
            $accommodation->getStatus()->value,
            $accommodation->getResort()->value,
            $accommodation->getType()->value,
            $accommodation->getDistrict()?->getName(),
            $accommodation->getCapacity(),
            $accommodation->getBedrooms(),
        );
    }
}
