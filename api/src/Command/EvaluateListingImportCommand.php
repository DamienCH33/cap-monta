<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\ListingImportFailure;
use App\Repository\DistrictRepository;
use App\Service\ListingImport\Evaluation\CaseScore;
use App\Service\ListingImport\Evaluation\EvalCase;
use App\Service\ListingImport\Evaluation\EvalCaseLoader;
use App\Service\ListingImport\Evaluation\EvalSetOverlap;
use App\Service\ListingImport\Evaluation\ExtractionScorer;
use App\Service\ListingImport\InvalidExtractionException;
use App\Service\ListingImport\ListingExtraction;
use App\Service\ListingImport\ListingImporter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Scores the listing import against the evaluation set.
 *
 * - no option: the set against its own answers, must give 100 % (checks the hand-written answers);
 * - --predictions=DIR: answers already on disk, one <case-id>.json per listing;
 * - --run: calls the model on every case, saves the answers in evals/listing-import/runs/, then
 *   scores them. Costs a few cents; --case limits it to the cases whose id contains a string.
 */
#[AsCommand(name: 'app:listing-import:eval', description: 'Évalue l\'import d\'annonce sur le jeu de cas réels')]
final readonly class EvaluateListingImportCommand
{
    /** Seconds to wait before each new attempt after a rate limit (free plan, per-minute quotas). */
    private const array RATE_LIMIT_WAITS_S = [10, 30, 60];

    public function __construct(
        private EvalCaseLoader $loader,
        private ExtractionScorer $scorer,
        private ListingImporter $importer,
        private DistrictRepository $districts,
        private ClockInterface $clock,
        #[Autowire('%kernel.project_dir%/evals/listing-import')]
        private string $evalDir,
        #[Autowire('%env(MISTRAL_API_KEY)%')]
        private string $apiKey,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Dossier des réponses de l\'agent (<id>.json), ou « expected » pour vérifier le jeu')]
        string $predictions = 'expected',
        #[Option('Dossier des cas')]
        ?string $cases = null,
        #[Option('Appelle le modèle sur chaque cas (formule gratuite de Mistral : compte dans le quota du jour)')]
        bool $run = false,
        #[Option('Modèle à utiliser avec --run')]
        string $model = ListingImporter::DEFAULT_MODEL,
        #[Option('Ne garde que les cas dont l\'identifiant contient ce texte')]
        ?string $case = null,
        #[Option('Supprime du jeu de contrôle les annonces déjà présentes dans cases/, au lieu de refuser')]
        bool $removeShared = false,
    ): int {
        $cases ??= $this->evalDir.'/cases';

        if (!is_dir($cases)) {
            $io->error(\sprintf('Dossier des cas introuvable : %s. Voir evals/listing-import/README.md.', $cases));

            return 1;
        }

        $duplicates = $this->sharedWithTheMainSet($cases);
        if ([] !== $duplicates && $removeShared) {
            foreach (array_keys($duplicates) as $file) {
                unlink($cases.'/'.$file);
            }
            $io->warning(['Retirées du jeu de contrôle (déjà dans cases/) :', ...array_values($duplicates)]);
            $duplicates = [];
        }
        if ([] !== $duplicates) {
            $io->error(['Ces annonces sont déjà dans evals/listing-import/cases : un jeu de contrôle ne doit contenir que des annonces jamais vues (--remove-shared pour les retirer).', ...array_values($duplicates)]);

            return 1;
        }

        $selected = array_values(array_filter(
            $this->loader->load($cases),
            static fn (EvalCase $c): bool => null === $case || str_contains($c->id, $case),
        ));

        if ($run) {
            if ('' === trim($this->apiKey)) {
                $io->error('MISTRAL_API_KEY est vide : ajoute ta clé dans api/.env.local (jamais dans .env). Voir evals/listing-import/README.md.');

                return 1;
            }
            $predictions = $this->runModel($io, $selected, $model, basename(rtrim($cases, '/')));
        }

        $scores = [];
        $rows = [];
        $expectedPeriods = 0;

        foreach ($selected as $evalCase) {
            $expectedPeriods += \count($evalCase->expected->periods);

            try {
                $actual = 'expected' === $predictions
                    ? new ListingExtraction($evalCase->expected->periods, $evalCase->expected->unavailable, $evalCase->needsClarification ? ['?'] : [], $evalCase->expected->listing)
                    : $this->prediction($predictions, $evalCase->id);
            } catch (InvalidExtractionException $e) {
                $rows[] = [$evalCase->id, '<error>ÉCHEC</error>', 'réponse invalide : '.$e->getMessage()];
                $scores[] = null;
                continue;
            }

            $score = $this->scorer->score($evalCase, $actual);
            $scores[] = $score;
            $rows[] = [
                $evalCase->id,
                $score->passed() ? '<info>OK</info>' : '<error>ÉCHEC</error>',
                implode("\n", $score->problems()),
            ];
        }

        $io->table(['Cas', 'Résultat', 'Détail'], $rows);
        $this->summary($io, $scores, $expectedPeriods);

        return 0;
    }

    /**
     * @return array<string, string> file of the control set => explanation
     */
    private function sharedWithTheMainSet(string $cases): array
    {
        $main = $this->evalDir.'/cases';

        return !is_dir($main) || realpath($main) === realpath($cases) ? [] : EvalSetOverlap::shared($cases, $main);
    }

    /**
     * @param list<EvalCase> $cases
     *
     * @return string the folder the answers were written to
     */
    private function runModel(SymfonyStyle $io, array $cases, string $model, string $set): string
    {
        $districts = $this->districts->names();
        // "20260924-101500-holdout-ministral-14b-2512": the set is in the name, the scores of the
        // two sets are never mixed up.
        $directory = \sprintf('%s/runs/%s-%s-%s', $this->evalDir, $this->clock->now()->format('Ymd-His'), $set, preg_replace('/[^a-z0-9.-]+/i', '-', $model));
        if (!is_dir($directory) && !mkdir($directory, 0o775, true)) {
            throw new \RuntimeException('Impossible de créer '.$directory);
        }

        $tokensIn = $tokensOut = $durationMs = 0;
        $io->progressStart(\count($cases));

        foreach ($cases as $evalCase) {
            // The free plan limits the pace: on a rate limit, wait and try again (3 times at most).
            $result = $this->importer->import($evalCase->text, $evalCase->publishedAt, $districts, $model);
            foreach (self::RATE_LIMIT_WAITS_S as $wait) {
                if (ListingImportFailure::ProviderLimit !== $result->failure) {
                    break;
                }
                sleep(min($result->retryAfter ?? $wait, 60));
                $result = $this->importer->import($evalCase->text, $evalCase->publishedAt, $districts, $model);
            }
            $tokensIn += $result->inputTokens ?? 0;
            $tokensOut += $result->outputTokens ?? 0;
            $durationMs += $result->durationMs;

            // A failed call leaves the error in the file: the scoring reports it as unreadable.
            $answer = $result->extraction?->toArray() ?? ['error' => $result->error, 'raw' => $result->rawOutput];
            file_put_contents(
                $directory.'/'.$evalCase->id.'.json',
                json_encode($answer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n",
            );
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->text(\sprintf(
            'Modèle %s — %d cas, %s tokens envoyés, %s reçus, %.1f s en tout. Réponses : %s',
            $model, \count($cases), number_format($tokensIn, 0, ',', ' '), number_format($tokensOut, 0, ',', ' '),
            $durationMs / 1000, $directory,
        ));

        return $directory;
    }

    private function prediction(string $directory, string $caseId): ListingExtraction
    {
        $file = rtrim($directory, '/').'/'.$caseId.'.json';

        if (!is_file($file)) {
            throw new InvalidExtractionException('pas de fichier '.$caseId.'.json');
        }

        $data = $this->loader->decode($file);
        if (\is_string($data['error'] ?? null)) {
            throw new InvalidExtractionException('appel en échec : '.$data['error']);
        }

        return ListingExtraction::fromArray($data);
    }

    /**
     * @param list<CaseScore|null> $scores null = unreadable answer
     */
    private function summary(SymfonyStyle $io, array $scores, int $expected): void
    {
        $valid = array_values(array_filter($scores));
        $count = static fn (callable $test): int => \count(array_filter($valid, $test));
        $sum = static fn (callable $size): int => array_sum(array_map($size, $valid));

        $io->definitionList(
            ['Annonces réussies' => \sprintf('%d / %d', $count(static fn (CaseScore $s): bool => $s->passed()), \count($scores))],
            ['Périodes retrouvées' => \sprintf('%d / %d (%d en trop)', $sum(static fn (CaseScore $s): int => $s->foundPeriods()), $expected, $sum(static fn (CaseScore $s): int => \count($s->extraPeriods)))],
            ['Prix inventés' => (string) $sum(static fn (CaseScore $s): int => \count($s->inventedAmounts))],
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
