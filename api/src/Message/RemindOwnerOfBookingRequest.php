<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Delivered 24 hours after a request is made: if the owner still has not answered, he gets a
 * reminder while half the deadline is left, instead of discovering an expired request.
 */
final readonly class RemindOwnerOfBookingRequest
{
    public function __construct(
        public string $bookingRequestId,
    ) {
    }
}
