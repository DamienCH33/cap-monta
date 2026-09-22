<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Enum\BookingRefusalReason;

/**
 * A booking request the domain refuses to create. The reason is typed so the
 * controller can turn it into a precise message without parsing anything.
 */
final class BookingRefusedException extends \RuntimeException
{
    private function __construct(
        private readonly BookingRefusalReason $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unavailable(): self
    {
        return new self(BookingRefusalReason::Unavailable, 'Ces dates viennent d’être prises. Choisissez-en d’autres.');
    }

    public static function tooManyGuests(int $guests, int $maxCapacity): self
    {
        return new self(
            BookingRefusalReason::TooManyGuests,
            sprintf('Ce logement accueille %d personnes au maximum (%d demandées).', $maxCapacity, $guests),
        );
    }

    public static function stayTooShort(int $nights, int $minimumNights): self
    {
        return new self(
            BookingRefusalReason::StayTooShort,
            sprintf('Le séjour dure %d nuits : le propriétaire en demande au moins %d sur cette période.', $nights, $minimumNights),
        );
    }

    public static function petsNotAllowed(): self
    {
        return new self(BookingRefusalReason::PetsNotAllowed, 'Le propriétaire n’accepte pas les animaux dans ce logement.');
    }

    public function getReason(): BookingRefusalReason
    {
        return $this->reason;
    }
}
