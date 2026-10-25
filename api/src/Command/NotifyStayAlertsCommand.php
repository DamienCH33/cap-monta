<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\StayAlert\StayAlertDesk;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The round of « Prévenez-moi »: writes to every guest whose dates have freed up. Each home is
 * announced once per alert, so running it often costs nothing; meant for the hourly cron job.
 */
#[AsCommand(name: 'app:stay-alerts:notify', description: 'Prévient les voyageurs dont les dates se sont libérées')]
final readonly class NotifyStayAlertsCommand
{
    public function __construct(
        private StayAlertDesk $desk,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(sprintf('%d alerte(s) envoyée(s).', $this->desk->notify()));

        return 0;
    }
}
