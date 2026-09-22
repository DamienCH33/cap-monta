<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\CreateAccommodationInput;
use App\Dto\UpdateAccommodationInput;
use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Service\Photo\PhotoStorage;
use App\State\ChangeAccommodationStatusProcessor;
use App\State\CreateOwnerAccommodationProcessor;
use App\State\OwnerAccommodationCollectionProvider;
use App\State\OwnerAccommodationItemProvider;
use App\State\UpdateOwnerAccommodationProcessor;

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
            // An unknown field (slug, status...) is refused, not silently ignored.
            denormalizationContext: ['allow_extra_attributes' => false],
            processor: CreateOwnerAccommodationProcessor::class,
        ),
        // read: false, the processor loads the entity and asks the voter itself.
        new Patch(
            uriTemplate: '/owner/accommodations/{slug}',
            input: UpdateAccommodationInput::class,
            denormalizationContext: ['allow_extra_attributes' => false],
            read: false,
            processor: UpdateOwnerAccommodationProcessor::class,
        ),
        // No body: the URL says it all. 200, not 201: nothing is created.
        new Post(
            uriTemplate: '/owner/accommodations/{slug}/publish',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ChangeAccommodationStatusProcessor::class,
            extraProperties: ['transition' => ChangeAccommodationStatusProcessor::PUBLISH],
        ),
        new Post(
            uriTemplate: '/owner/accommodations/{slug}/archive',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ChangeAccommodationStatusProcessor::class,
            extraProperties: ['transition' => ChangeAccommodationStatusProcessor::ARCHIVE],
        ),
    ],
)]
final class OwnerAccommodationResource
{
    /**
     * @param list<string>                                                                    $amenities
     * @param list<array{id: string, url: string, thumbUrl: string, width: int, height: int}> $photos    the cover first
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        public string $status,
        public string $resort,
        public string $type,
        public ?string $district,
        public ?string $districtSlug,
        public int $capacity,
        public int $bedrooms,
        public ?int $surface,
        public array $amenities,
        public string $description,
        public array $photos = [],
        /** Last check of the calendar by the owner, ISO 8601; null if never. */
        public ?\DateTimeImmutable $calendarCheckedAt = null,
        /** Same rule as the public badge: checked less than 30 days ago. */
        public bool $calendarUpToDate = false,
        public string $petsPolicy = 'on_request',
    ) {
    }

    /**
     * The storage only gives the photos their public addresses: they change with it
     * (local disk in development, object storage in production).
     */
    public static function fromEntity(Accommodation $accommodation, PhotoStorage $storage): self
    {
        return new self(
            $accommodation->getSlug(),
            $accommodation->getStatus()->value,
            $accommodation->getResort()->value,
            $accommodation->getType()->value,
            $accommodation->getDistrict()?->getName(),
            $accommodation->getDistrict()?->getSlug(),
            $accommodation->getCapacity(),
            $accommodation->getBedrooms(),
            $accommodation->getSurface(),
            $accommodation->getAmenities(),
            $accommodation->getDescription(),
            array_map(static fn (Photo $photo): array => [
                'id' => $photo->getId()->toRfc4122(),
                'url' => $storage->url($photo),
                'thumbUrl' => $storage->thumbUrl($photo),
                'width' => $photo->getWidth(),
                'height' => $photo->getHeight(),
            ], $accommodation->getPhotos()),
            $accommodation->getCalendarCheckedAt(),
            $accommodation->isCalendarUpToDate(new \DateTimeImmutable()),
            $accommodation->getPetsPolicy()->value,
        );
    }
}
