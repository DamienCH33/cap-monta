<?php

declare(strict_types=1);

namespace App\Service\Booking;

/**
 * What a guest submits from the accommodation page.
 */
final readonly class NewBookingRequest
{
    public function __construct(
        public \DateTimeImmutable $arrival,
        public \DateTimeImmutable $departure,
        public int $adults,
        public string $guestName,
        public string $guestEmail,
        public int $children = 0,
        public ?string $guestPhone = null,
        public ?string $message = null,
        public int $infants = 0,
        public int $pets = 0,
    ) {
    }

    /**
     * People who take a bed. Infants and pets do not count against capacity.
     */
    public function guests(): int
    {
        return $this->adults + $this->children;
    }

    public function nights(): int
    {
        return (int) $this->arrival->diff($this->departure)->days;
    }
}
