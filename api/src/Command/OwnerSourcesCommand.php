<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\HeardFrom;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Which channel brings owners: the answers to « Comment avez-vous connu Cap Monta ? », counted.
 * Confirmed accounts only (an address never confirmed is nobody), with how many published.
 */
#[AsCommand(name: 'app:owners:sources', description: 'Propriétaires inscrits par canal (« Comment avez-vous connu Cap Monta ? »)')]
final readonly class OwnerSourcesCommand
{
    public function __construct(
        private Connection $db,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        /** @var list<array{heard_from: string|null, owners: int|string, publishing: int|string}> $rows */
        $rows = $this->db->fetchAllAssociative(
            <<<'SQL'
                SELECT u.heard_from,
                       count(*) AS owners,
                       count(*) FILTER (WHERE EXISTS (
                           SELECT 1 FROM accommodation a WHERE a.owner_id = u.id AND a.status = 'published'
                       )) AS publishing
                FROM "user" u
                WHERE u.email_verified_at IS NOT NULL
                GROUP BY u.heard_from
                ORDER BY count(*) DESC, u.heard_from NULLS LAST
                SQL,
        );

        $io->table(['Canal', 'Propriétaires', 'Avec une annonce en ligne'], array_map(static fn (array $row): array => [
            null === $row['heard_from'] ? 'sans réponse' : (HeardFrom::tryFrom($row['heard_from'])?->label() ?? $row['heard_from']),
            (int) $row['owners'],
            (int) $row['publishing'],
        ], $rows));

        return 0;
    }
}
