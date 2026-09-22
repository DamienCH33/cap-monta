<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RemindOwnerOfBookingRequest;
use App\Repository\BookingRequestRepository;
use App\Service\Booking\BookingMailer;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Already answered, cancelled or expired: no reminder. Nothing is written, so a message
 * delivered twice sends at most a second reminder, never a wrong state.
 */
#[AsMessageHandler]
final readonly class RemindOwnerOfBookingRequestHandler
{
    public function __construct(
        private BookingRequestRepository $requests,
        private BookingMailer $mailer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemindOwnerOfBookingRequest $message): void
    {
        if (!Uuid::isValid($message->bookingRequestId)) {
            return;
        }

        $request = $this->requests->find(Uuid::fromString($message->bookingRequestId));

        if (null !== $request && $request->isPending() && $request->getExpiresAt() > $this->clock->now()) {
            $this->mailer->reminder($request);
        }
    }
}
