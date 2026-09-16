<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Entity\BookingRequest;
use App\Service\Booking\BookingRefusedException;
use App\State\BookingRequestItemProvider;
use App\State\CreateBookingRequestProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A booking request, as seen from the outside.
 *
 * Fields above the separator are written by the guest, fields below are filled
 * by the processor once the domain has accepted the request.
 */
#[ApiResource(
    shortName: 'BookingRequest',
    operations: [
        new Post(
            uriTemplate: '/booking-requests',
            processor: CreateBookingRequestProcessor::class,
            exceptionToStatus: [BookingRefusedException::class => 409],
        ),
        new Get(
            uriTemplate: '/booking-requests/{id}',
            provider: BookingRequestItemProvider::class,
        ),
    ],
)]
final class BookingRequestResource
{
    #[ApiProperty(identifier: true, writable: false)]
    public ?string $id = null;

    #[Assert\NotBlank]
    public string $accommodationSlug = '';

    #[Assert\NotNull]
    public ?\DateTimeImmutable $arrival = null;

    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'arrival', message: 'The departure must come after the arrival.')]
    public ?\DateTimeImmutable $departure = null;

    #[Assert\Positive]
    public int $adults = 1;

    #[Assert\PositiveOrZero]
    public int $children = 0;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $guestName = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public string $guestEmail = '';

    #[Assert\Length(max: 30)]
    #[Assert\Regex(
        pattern: '/^(?:\+33|0)\s*[1-9](?:[\s.\-]*\d{2}){4}$/',
        message: 'Numéro de téléphone français attendu, par exemple 06 12 34 56 78.',
    )]
    public ?string $guestPhone = null;

    #[Assert\Length(max: 2000)]
    public ?string $message = null;

    // ---- filled by the processor, never by the client ----

    #[ApiProperty(writable: false)]
    public ?string $status = null;

    #[ApiProperty(writable: false)]
    public ?int $estimatedPrice = null;

    #[ApiProperty(writable: false)]
    public ?\DateTimeImmutable $expiresAt = null;

    public static function fromEntity(BookingRequest $request): self
    {
        $resource = new self();

        $resource->id = $request->getId()->toRfc4122();
        $resource->accommodationSlug = $request->getAccommodation()->getSlug();
        $resource->arrival = $request->getStartDate();
        $resource->departure = $request->getEndDate();
        $resource->adults = $request->getAdults();
        $resource->children = $request->getChildren();
        $resource->guestName = $request->getGuestName();
        $resource->guestEmail = $request->getGuestEmail();
        $resource->guestPhone = $request->getGuestPhone();
        $resource->message = $request->getMessage();
        $resource->status = $request->getStatus()->value;
        $resource->estimatedPrice = $request->getEstimatedPrice();
        $resource->expiresAt = $request->getExpiresAt();

        return $resource;
    }
}
