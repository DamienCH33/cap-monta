<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Accommodation;
use App\Entity\BookingRequest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates a booking request: an intent, not a reservation.
 *
 * The availability check here is advisory. Dates may be taken between this
 * request and the owner's answer; the PostgreSQL exclusion constraint settles
 * it for good when the request is accepted.
 */
final class BookingRequestCreator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuoteCalculator $quotes,
    ) {
    }

    /**
     * @throws BookingRefusedException
     */
    public function create(Accommodation $accommodation, NewBookingRequest $input): BookingRequest
    {
        $quote = $this->quotes->assert(
            $accommodation,
            $input->arrival,
            $input->departure,
            $input->guests(),
        );

        $request = new BookingRequest(
            $accommodation,
            $input->arrival,
            $input->departure,
            $input->adults,
            $input->guestName,
            $input->guestEmail,
        );

        $request
            ->setChildren($input->children)
            ->setGuestPhone($input->guestPhone)
            ->setMessage($input->message)
            ->setEstimatedPrice($quote->total);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }
}
