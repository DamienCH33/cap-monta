<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Accommodation;
use App\Entity\BookingRequest;
use App\Entity\PricePeriod;
use App\Repository\PricePeriodRepository;
use App\Repository\UnavailabilityRepository;
use App\Service\Pricing\PriceCalculator;
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
        private readonly UnavailabilityRepository $unavailabilities,
        private readonly PricePeriodRepository $pricePeriods,
        private readonly PriceCalculator $priceCalculator,
    ) {
    }

    /**
     * @throws BookingRefusedException
     */
    public function create(Accommodation $accommodation, NewBookingRequest $input): BookingRequest
    {
        $nights = $input->nights();

        if ($nights < 1) {
            throw BookingRefusedException::stayTooShort($nights, 1);
        }

        if ($input->guests() > $accommodation->getMaxCapacity()) {
            throw BookingRefusedException::tooManyGuests($input->guests(), $accommodation->getMaxCapacity());
        }

        if ($this->unavailabilities->hasOverlap($accommodation, $input->arrival, $input->departure)) {
            throw BookingRefusedException::unavailable();
        }

        $periods = $this->pricePeriods->findCoveringStay($accommodation, $input->arrival, $input->departure);
        $minimumNights = $this->minimumNights($periods);

        if ($nights < $minimumNights) {
            throw BookingRefusedException::stayTooShort($nights, $minimumNights);
        }

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
            // Frozen on purpose: the guest committed to this amount, a later
            // change to the owner's rate grid must not rewrite it.
            ->setEstimatedPrice($this->priceCalculator->calculate($periods, $input->arrival, $input->departure));

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * The strictest minimum among the periods the stay goes through.
     *
     * @param list<PricePeriod> $periods
     */
    private function minimumNights(array $periods): int
    {
        $minimums = array_map(
            static fn (PricePeriod $period): int => $period->getMinimumNights(),
            $periods,
        );

        return [] === $minimums ? 1 : max($minimums);
    }
}
