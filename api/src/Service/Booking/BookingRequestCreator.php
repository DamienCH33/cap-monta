<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Accommodation;
use App\Entity\BookingRequest;
use App\Message\ExpireBookingRequest;
use App\Message\RemindOwnerOfBookingRequest;
use App\Repository\BookingRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

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
        private readonly MessageBusInterface $bus,
        private readonly BookingMailer $mailer,
        private readonly BookingRequestRepository $requests,
    ) {
    }

    /**
     * @throws BookingRefusedException
     * @throws DuplicateBookingRequestException
     */
    public function create(Accommodation $accommodation, NewBookingRequest $input): BookingRequest
    {
        if ($this->requests->hasPendingDuplicate($accommodation, $input->guestEmail, $input->arrival, $input->departure)) {
            throw new DuplicateBookingRequestException();
        }

        $quote = $this->quotes->assert(
            $accommodation,
            $input->arrival,
            $input->departure,
            $input->guests(),
            $input->pets,
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
            ->setInfants($input->infants)
            ->setPets($input->pets)
            ->setGuestPhone($input->guestPhone)
            ->setMessage($input->message)
            ->setEstimatedPrice($quote->total)
            ->markOutsideRules($quote->outsideRules);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        // Delivered 24 then 48 hours later: a reminder to the owner, then the expiry,
        // each doing nothing if he has answered in the meantime.
        $id = $request->getId()->toRfc4122();
        $this->bus->dispatch(
            new RemindOwnerOfBookingRequest($id),
            [DelayStamp::delayUntil($request->remindAt())],
        );
        $this->bus->dispatch(new ExpireBookingRequest($id), [DelayStamp::delayUntil($request->getExpiresAt())]);
        $this->mailer->received($request);

        return $request;
    }
}
