<?php

declare(strict_types=1);

namespace App\Service\ListingImport\Evaluation;

/**
 * The comparison of one extraction with the expected answer. A case passes only when nothing
 * is missing, nothing is extra, no amount is invented and the agent asked exactly when it had to.
 */
final readonly class CaseScore
{
    /**
     * @param list<string> $missingPeriods          expected, not found
     * @param list<string> $extraPeriods            found, not expected
     * @param list<string> $missingUnavailabilities
     * @param list<string> $extraUnavailabilities
     * @param list<int>    $inventedAmounts         amounts absent from the listing text
     * @param list<string> $wrongFields             accommodation fields that differ ("chambres : attendu 3, trouvé 2")
     * @param list<string> $inventedAmenities       ticked but not written in the listing
     * @param list<string> $missedAmenities         written but not ticked
     * @param list<string> $lostFeatures            out-of-list items that vanished instead of being reported
     * @param list<string> $rejected                values the PHP refused (unknown key, impossible number)
     */
    public function __construct(
        public string $caseId,
        public int $expectedPeriods,
        public array $missingPeriods,
        public array $extraPeriods,
        public array $missingUnavailabilities,
        public array $extraUnavailabilities,
        public array $inventedAmounts,
        public bool $needsClarification,
        public bool $askedQuestions,
        public array $wrongFields = [],
        public array $inventedAmenities = [],
        public array $missedAmenities = [],
        public array $lostFeatures = [],
        public array $rejected = [],
    ) {
    }

    public function passed(): bool
    {
        return [] === $this->problems();
    }

    public function foundPeriods(): int
    {
        return $this->expectedPeriods - \count($this->missingPeriods);
    }

    public function missedQuestion(): bool
    {
        return $this->needsClarification && !$this->askedQuestions;
    }

    public function needlessQuestion(): bool
    {
        return !$this->needsClarification && $this->askedQuestions;
    }

    /**
     * @return list<string> one line per problem, in French, for the report, most serious first
     */
    public function problems(): array
    {
        $problems = [];

        foreach ($this->inventedAmounts as $amount) {
            $problems[] = \sprintf('PRIX INVENTÉ : %d € n\'est pas dans le texte', $amount);
        }
        foreach ($this->inventedAmenities as $amenity) {
            $problems[] = 'ÉQUIPEMENT INVENTÉ : '.$amenity.' coché sans être écrit';
        }
        foreach ($this->rejected as $value) {
            $problems[] = 'refusé par le PHP : '.$value;
        }
        foreach ($this->missingPeriods as $period) {
            $problems[] = 'période manquante : '.$period;
        }
        foreach ($this->extraPeriods as $period) {
            $problems[] = 'période en trop : '.$period;
        }
        foreach ($this->missingUnavailabilities as $range) {
            $problems[] = 'indisponibilité manquante : '.$range;
        }
        foreach ($this->extraUnavailabilities as $range) {
            $problems[] = 'indisponibilité en trop : '.$range;
        }
        foreach ($this->wrongFields as $field) {
            $problems[] = 'champ faux : '.$field;
        }
        foreach ($this->missedAmenities as $amenity) {
            $problems[] = 'équipement oublié : '.$amenity;
        }
        foreach ($this->lostFeatures as $feature) {
            $problems[] = 'hors liste perdu : '.$feature;
        }
        if ($this->missedQuestion()) {
            $problems[] = 'aurait dû poser une question';
        }
        if ($this->needlessQuestion()) {
            $problems[] = 'question inutile';
        }

        return $problems;
    }
}
