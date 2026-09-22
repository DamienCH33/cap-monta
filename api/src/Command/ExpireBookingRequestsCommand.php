<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BookingRequestRepository;
use App\Service\Booking\BookingDesk;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Catch-up for the delayed messages: expires every pending request past its deadline or whose
 * arrival day has come. Harmless to run often; meant for a cron job in production (hourly),
 * in case a message was lost while the worker was stopped.
 */
#[AsCommand(name: 'app:booking-requests:expire', description: 'Expire les demandes restées sans réponse')]
final readonly class ExpireBookingRequestsCommand
{
    public function __construct(
        private BookingRequestRepository $requests,
        private BookingDesk $desk,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $expired = 0;

        foreach ($this->requests->findDueForExpiry($this->clock->now()) as $request) {
            $expired += $this->desk->expire($request) ? 1 : 0;
        }

        $io->success(sprintf('%d demande(s) expirée(s).', $expired));

        return 0;
    }
}
