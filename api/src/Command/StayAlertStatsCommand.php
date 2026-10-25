<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\Resort;
use App\Repository\StayAlertRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What guests are still waiting for, by month of arrival: the figure to show owners. Counts
 * only, never an address.
 */
#[AsCommand(name: 'app:stay-alerts:stats', description: 'Alertes actives par mois d’arrivée et par domaine')]
final readonly class StayAlertStatsCommand
{
    public function __construct(
        private StayAlertRepository $alerts,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $rows = $this->alerts->demandByMonth($this->clock->now()->setTime(0, 0));

        if ([] === $rows) {
            $io->success('Aucune alerte active.');

            return 0;
        }

        $io->table(['Mois d’arrivée', 'Domaine', 'Alertes'], array_map(static fn (array $row): array => [
            $row['month'],
            null === $row['resort'] ? 'les deux' : (Resort::tryFrom($row['resort'])?->label() ?? $row['resort']),
            $row['alerts'],
        ], $rows));

        return 0;
    }
}
