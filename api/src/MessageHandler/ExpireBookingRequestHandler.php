<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ExpireBookingRequest;
use App\Repository\BookingRequestRepository;
use App\Service\Booking\BookingDesk;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Answered, cancelled or already expired in the meantime: nothing to do. BookingDesk::expire()
 * checks the state and the deadline itself, so a message delivered twice is harmless.
 */
#[AsMessageHandler]
final readonly class ExpireBookingRequestHandler
{
    public function __construct(
        private BookingRequestRepository $requests,
        private BookingDesk $desk,
    ) {
    }

    public function __invoke(ExpireBookingRequest $message): void
    {
        if (!Uuid::isValid($message->bookingRequestId)) {
            return;
        }

        $request = $this->requests->find(Uuid::fromString($message->bookingRequestId));

        if (null !== $request) {
            $this->desk->expire($request);
        }
    }
}
