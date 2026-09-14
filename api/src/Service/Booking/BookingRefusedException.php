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
        return new self(BookingRefusalReason::Unavailable, 'The accommodation is already booked for these dates.');
    }

    public static function tooManyGuests(int $guests, int $maxCapacity): self
    {
        return new self(
            BookingRefusalReason::TooManyGuests,
            sprintf('The accommodation sleeps %d guests, %d were requested.', $maxCapacity, $guests),
        );
    }

    public static function stayTooShort(int $nights, int $minimumNights): self
    {
        return new self(
            BookingRefusalReason::StayTooShort,
            sprintf('The stay lasts %d nights, the owner requires at least %d.', $nights, $minimumNights),
        );
    }

    public function getReason(): BookingRefusalReason
    {
        return $this->reason;
    }
}
