<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ListingImport\Evaluation\CaseScore;
use App\Service\ListingImport\Evaluation\EvalCaseLoader;
use App\Service\ListingImport\Evaluation\ExtractionScorer;
use App\Service\ListingImport\InvalidExtractionException;
use App\Service\ListingImport\ListingExtraction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Scores the import agent against the evaluation set. For now it reads the agent's answers from
 * a folder (one <case-id>.json per listing); lot 4b will add an option that calls the agent.
 *
 * Checking the set itself: --predictions=expected scores every case against its own answer and
 * must give 100 %. A lower score means a hand-written answer is inconsistent.
 */
#[AsCommand(name: 'app:listing-import:eval', description: 'Évalue l\'import d\'annonce sur le jeu de cas réels')]
final readonly class EvaluateListingImportCommand
{
    public function __construct(
        private EvalCaseLoader $loader,
        private ExtractionScorer $scorer,
        #[Autowire('%kernel.project_dir%/evals/listing-import/cases')]
        private string $defaultCases,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Dossier des réponses de l\'agent (<id>.json), ou « expected » pour vérifier le jeu')]
        string $predictions = 'expected',
        #[Option('Dossier des cas')]
        ?string $cases = null,
    ): int {
        $cases ??= $this->defaultCases;

        if (!is_dir($cases)) {
            $io->error(\sprintf('Dossier des cas introuvable : %s. Voir evals/listing-import/README.md.', $cases));

            return 1;
        }

        $scores = [];
        $rows = [];
        $expectedPeriods = 0;

        foreach ($this->loader->load($cases) as $case) {
            $expectedPeriods += \count($case->expected->periods);

            try {
                $actual = 'expected' === $predictions
                    ? new ListingExtraction($case->expected->periods, $case->expected->unavailable, $case->needsClarification ? ['?'] : [], $case->expected->listing)
                    : $this->prediction($predictions, $case->id);
            } catch (InvalidExtractionException $e) {
                $rows[] = [$case->id, '<error>ÉCHEC</error>', 'réponse invalide : '.$e->getMessage()];
                $scores[] = null;
                continue;
            }

            $score = $this->scorer->score($case, $actual);
            $scores[] = $score;
            $rows[] = [
                $case->id,
                $score->passed() ? '<info>OK</info>' : '<error>ÉCHEC</error>',
                implode("\n", $score->problems()),
            ];
        }

        $io->table(['Cas', 'Résultat', 'Détail'], $rows);
        $this->summary($io, $scores, $expectedPeriods);

        return 0;
    }

    private function prediction(string $directory, string $caseId): ListingExtraction
    {
        $file = rtrim($directory, '/').'/'.$caseId.'.json';

        if (!is_file($file)) {
            throw new InvalidExtractionException('pas de fichier '.$caseId.'.json');
        }

        return ListingExtraction::fromArray($this->loader->decode($file));
    }

    /**
     * @param list<CaseScore|null> $scores null = unreadable answer
     */
    private function summary(SymfonyStyle $io, array $scores, int $expected): void
    {
        $valid = array_values(array_filter($scores));
        $count = static fn (callable $test): int => \count(array_filter($valid, $test));

        $found = array_sum(array_map(static fn (CaseScore $s): int => $s->foundPeriods(), $valid));
        $extra = array_sum(array_map(static fn (CaseScore $s): int => \count($s->extraPeriods), $valid));
        $invented = array_sum(array_map(static fn (CaseScore $s): int => \count($s->inventedAmounts), $valid));
        $sum = static fn (callable $size): int => array_sum(array_map($size, $valid));

        $io->definitionList(
            ['Annonces réussies' => \sprintf('%d / %d', $count(static fn (CaseScore $s): bool => $s->passed()), \count($scores))],
            ['Périodes retrouvées' => \sprintf('%d / %d (%d en trop)', $found, $expected, $extra)],
            ['Prix inventés' => (string) $invented],
            ['Équipements inventés' => (string) $sum(static fn (CaseScore $s): int => \count($s->inventedAmenities))],
            ['Équipements oubliés' => (string) $sum(static fn (CaseScore $s): int => \count($s->missedAmenities))],
            ['Hors liste perdus' => (string) $sum(static fn (CaseScore $s): int => \count($s->lostFeatures))],
            ['Champs de l\'annonce faux' => (string) $sum(static fn (CaseScore $s): int => \count($s->wrongFields))],
            ['Valeurs refusées par le PHP' => (string) $sum(static fn (CaseScore $s): int => \count($s->rejected))],
            ['Questions oubliées' => (string) $count(static fn (CaseScore $s): bool => $s->missedQuestion())],
            ['Questions inutiles' => (string) $count(static fn (CaseScore $s): bool => $s->needlessQuestion())],
            ['Réponses illisibles' => (string) (\count($scores) - \count($valid))],
        );
    }
}
