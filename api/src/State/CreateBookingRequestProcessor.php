<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BookingRequestResource;
use App\Repository\AccommodationRepository;
use App\Service\Booking\BookingRequestCreator;
use App\Service\Booking\NewBookingRequest;
use App\Service\Http\FloodGuard;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * POST /api/booking-requests.
 *
 * Translates HTTP into the domain and back. Every rule lives in
 * BookingRequestCreator; a refusal travels as an exception and API Platform
 * turns it into a 409 (see exceptionToStatus on the operation).
 *
 * @implements ProcessorInterface<mixed, BookingRequestResource>
 */
final readonly class CreateBookingRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private BookingRequestCreator $creator,
        private FloodGuard $floodGuard,
        #[Target('booking_requests')]
        private RateLimiterFactoryInterface $bookingRequestsLimiter,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BookingRequestResource
    {
        // Cinq demandes par quart d'heure et par adresse. Au-delà, c'est un script.
        $this->floodGuard->check($this->bookingRequestsLimiter);

        if (!$data instanceof BookingRequestResource) {
            throw new \LogicException(sprintf('Expected a %s.', BookingRequestResource::class));
        }

        // Guaranteed by the validator; reaching this means the object was built
        // outside the HTTP flow, which is a programming error, not a bad input.
        if (null === $data->arrival || null === $data->departure) {
            throw new \LogicException('Validation should have rejected a request without dates.');
        }

        $accommodation = $this->accommodations->findOnePublishedBySlug($data->accommodationSlug);

        if (null === $accommodation) {
            // Not a refusal: the resource itself does not exist.
            throw new NotFoundHttpException(sprintf('No accommodation with slug "%s".', $data->accommodationSlug));
        }

        $request = $this->creator->create($accommodation, new NewBookingRequest(
            arrival: $data->arrival,
            departure: $data->departure,
            adults: $data->adults,
            guestName: $data->guestName,
            guestEmail: $data->guestEmail,
            children: $data->children,
            guestPhone: $data->guestPhone,
            message: $data->message,
            infants: $data->infants,
            pets: $data->pets,
        ));

        return BookingRequestResource::fromEntity($request);
    }
}
