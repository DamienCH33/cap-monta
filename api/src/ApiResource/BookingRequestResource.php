<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Entity\BookingRequest;
use App\Service\Booking\BookingRefusedException;
use App\Service\Booking\DuplicateBookingRequestException;
use App\State\CreateBookingRequestProcessor;
use App\State\StayQuery;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A booking request, as seen from the outside.
 *
 * Creation only. Reading it back goes through the private tracking link sent to the guest
 * (BookingTrackingController), never by id: the id is not a secret, and the request holds
 * the guest's contact details.
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
            denormalizationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
            exceptionToStatus: [BookingRefusedException::class => 409, DuplicateBookingRequestException::class => 409],
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
    #[Assert\GreaterThan('today', message: 'Choisissez une arrivée à partir de demain : le propriétaire doit avoir le temps de répondre.')]
    #[Assert\LessThanOrEqual('+24 months', message: 'Les calendriers ne vont pas au-delà de deux ans.')]
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public ?\DateTimeImmutable $arrival = null;

    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'arrival', message: 'The departure must come after the arrival.')]
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public ?\DateTimeImmutable $departure = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(30)]
    public int $adults = 1;

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(30)]
    public int $children = 0;

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(5)]
    public int $infants = 0;

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(5)]
    public int $pets = 0;

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    // Un nom, pas un message : il est repris dans « Bonjour … » des emails envoyés. Un lien ou
    // un retour à la ligne permettrait d'y glisser un faux bouton (hameçonnage).
    #[Assert\Regex(
        pattern: '/^(?!.*(?:https?:|www\.|:\/\/))[^\r\n\t<>]+$/iu',
        message: 'Indiquez seulement votre nom, sans lien ni retour à la ligne.',
    )]
    public string $guestName = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public string $guestEmail = '';

    #[Assert\Length(max: 30)]
    // Numéro français, ou étranger au format international : les locataires viennent aussi
    // des Pays-Bas, d'Allemagne, de Belgique…
    #[Assert\Regex(
        pattern: '/^(?:(?:\+33|0)\s*[1-9](?:[\s.\-]*\d{2}){4}|\+[1-9](?:[\s.\-]?\d){6,14})$/',
        message: 'Numéro attendu : 06 12 34 56 78, ou au format international (+31 6 12 34 56 78).',
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

    /** Returned to the guest who just sent the request, so the page can link to it. */
    #[ApiProperty(writable: false)]
    public ?string $trackingToken = null;

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
        $resource->trackingToken = $request->getTrackingToken();
        $resource->infants = $request->getInfants();
        $resource->pets = $request->getPets();

        return $resource;
    }

    /** A holiday stay, not a year-long lease: StayQuery applies the same bound to the search. */
    #[Assert\Callback]
    public function validateStayLength(ExecutionContextInterface $context): void
    {
        if (null === $this->arrival || null === $this->departure) {
            return;
        }

        if ($this->departure > $this->arrival->modify(sprintf('+%d days', StayQuery::MAX_NIGHTS))) {
            $context->buildViolation(sprintf('Un séjour dure %d nuits au plus.', StayQuery::MAX_NIGHTS))
                ->atPath('departure')
                ->addViolation();
        }
    }
}
