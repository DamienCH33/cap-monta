<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Enum\BookingRefusalReason;
use App\Enum\PetsPolicy;
use App\Repository\PricePeriodRepository;
use App\Repository\UnavailabilityRepository;
use App\Service\Pricing\PriceCalculator;
use App\ValueObject\DateRange;

/**
 * La seule définition des règles d'un séjour : durée, capacité, disponibilité,
 * minimum de nuits, prix. Le devis les décrit, la création les impose.
 */
final class QuoteCalculator
{
    public function __construct(
        private readonly UnavailabilityRepository $unavailabilities,
        private readonly PricePeriodRepository $pricePeriods,
        private readonly PriceCalculator $priceCalculator,
    ) {
    }

    public function quote(
        Accommodation $accommodation,
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        int $guests,
        int $pets = 0,
        ?int $adults = null,
    ): Quote {
        $nights = (new DateRange($arrival, $departure))->nights();
        $maxCapacity = $accommodation->getMaxCapacity();

        $periods = $this->pricePeriods->findCoveringStay($accommodation, $arrival, $departure);
        $minimumNights = $this->minimumNights($periods);

        $refusal = match (true) {
            $nights < 1 => BookingRefusalReason::StayTooShort,
            $guests > $maxCapacity => BookingRefusalReason::TooManyGuests,
            $pets > 0 && PetsPolicy::NotAllowed === $accommodation->getPetsPolicy() => BookingRefusalReason::PetsNotAllowed,
            $this->unavailabilities->hasOverlap($accommodation, $arrival, $departure) => BookingRefusalReason::Unavailable,
            $nights < $minimumNights => BookingRefusalReason::StayTooShort,
            default => null,
        };

        $total = $nights >= 1
            ? $this->priceCalculator->calculate($periods, $arrival, $departure)
            : null;

        [$extras, $unknown] = $this->extras($accommodation, max(0, $nights), $guests, $adults ?? $guests);

        return new Quote($nights, $guests, $maxCapacity, $minimumNights, $total, $refusal, $this->ignoresArrivalDay($periods, $arrival), $extras, $unknown);
    }

    /**
     * The fees declared by the owner, for this stay. The tourist tax is due by adults only
     * (children are exempt); the resort fee and the linen by every guest (babies are never
     * counted as guests).
     *
     * @return array{0: list<QuoteExtra>, 1: list<string>} the fees, and the mandatory ones nobody stated
     */
    private function extras(Accommodation $accommodation, int $nights, int $guests, int $adults): array
    {
        $terms = $accommodation->getTerms();
        $extras = [];
        $unknown = [];

        $mandatory = [
            QuoteExtra::TOURIST_TAX => [$terms->getTouristTax(), min($adults, $guests)],
            QuoteExtra::RESORT_FEE => [$terms->getResortFee(), $guests],
        ];

        foreach ($mandatory as $code => [$rate, $people]) {
            if (null === $rate) {
                $unknown[] = $code;
            } elseif ($rate > 0) {
                $extras[] = new QuoteExtra($code, $rate * $people * $nights, false);
            }
        }

        if (null !== $terms->getCleaningFee() && $terms->getCleaningFee() > 0) {
            $extras[] = new QuoteExtra(QuoteExtra::CLEANING, $terms->getCleaningFee(), true);
        }
        if (null !== $terms->getLinenFee() && $terms->getLinenFee() > 0) {
            $extras[] = new QuoteExtra(QuoteExtra::LINEN, $terms->getLinenFee() * $guests, true);
        }

        return [$extras, $unknown];
    }

    /**
     * Le même devis, mais qui refuse au lieu de décrire.
     *
     * @throws BookingRefusedException
     */
    public function assert(
        Accommodation $accommodation,
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        int $guests,
        int $pets = 0,
    ): Quote {
        $quote = $this->quote($accommodation, $arrival, $departure, $guests, $pets);

        if (null !== $quote->refusal) {
            throw match ($quote->refusal) {
                BookingRefusalReason::Unavailable => BookingRefusedException::unavailable(),
                BookingRefusalReason::TooManyGuests => BookingRefusedException::tooManyGuests($quote->guests, $quote->maxCapacity),
                BookingRefusalReason::StayTooShort => BookingRefusedException::stayTooShort($quote->nights, $quote->minimumNights),
                BookingRefusalReason::PetsNotAllowed => BookingRefusedException::petsNotAllowed(),
            };
        }

        return $quote;
    }

    /**
     * The period of the arrival night prefers Saturday arrivals, and this one is not a Saturday.
     *
     * @param list<PricePeriod> $periods
     */
    private function ignoresArrivalDay(array $periods, \DateTimeImmutable $arrival): bool
    {
        foreach ($periods as $period) {
            if ($period->getStartDate() <= $arrival && $arrival < $period->getEndDate()) {
                return $period->prefersSaturdayArrival() && '6' !== $arrival->format('N');
            }
        }

        return false;
    }

    /**
     * Le minimum le plus strict parmi les périodes traversées.
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
