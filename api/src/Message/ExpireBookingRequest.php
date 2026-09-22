<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Sent when a request is made, delivered 48 hours later: if the owner has not answered,
 * the request expires. Carries the id only; the handler re-reads the current state.
 */
final readonly class ExpireBookingRequest
{
    public function __construct(
        public string $bookingRequestId,
    ) {
    }
}
