<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Service\ListingImport\Evaluation\EvalCase;
use App\Service\ListingImport\Evaluation\ExtractionScorer;
use App\Service\ListingImport\Evaluation\SourceAmounts;
use App\Service\ListingImport\ListingExtraction;
use PHPUnit\Framework\TestCase;

final class ExtractionScorerTest extends TestCase
{
    private const string TEXT = "Juillet : 650 € la semaine.\nDéjà loué du 08/07/2026 au 15/07/2026.";

    private ExtractionScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new ExtractionScorer();
    }

    public function testTheExpectedAnswerPasses(): void
    {
        $score = $this->scorer->score($this->case(), $this->extraction());

        self::assertTrue($score->passed());
        self::assertSame(1, $score->foundPeriods());
        self::assertSame([], $score->problems());
    }

    public function testAnAmountAbsentFromTheTextIsInvented(): void
    {
        $score = $this->scorer->score($this->case(), $this->extraction(amount: 600));

        self::assertFalse($score->passed());
        self::assertSame([600], $score->inventedAmounts);
        self::assertSame(0, $score->foundPeriods());
        self::assertCount(1, $score->extraPeriods);
    }

    public function testAPeriodThatIsAlmostRightIsWrong(): void
    {
        $score = $this->scorer->score($this->case(), $this->extraction(saturday: true));

        self::assertFalse($score->passed());
        self::assertSame(['2026-07-01→2026-08-01 [650/week] min=-'], $score->missingPeriods);
        self::assertSame(['2026-07-01→2026-08-01 [650/week] min=- samedi'], $score->extraPeriods);
        self::assertSame([], $score->inventedAmounts, 'the amount itself is in the text');
    }

    public function testMissingTakenDatesAreReported(): void
    {
        $score = $this->scorer->score($this->case(), $this->extraction(unavailable: []));

        self::assertSame(['2026-07-08→2026-07-15'], $score->missingUnavailabilities);
        self::assertFalse($score->passed());
    }

    public function testAQuestionIsExpectedExactlyWhenTheCaseNeedsOne(): void
    {
        $needless = $this->scorer->score($this->case(), $this->extraction(questions: ['Quel tarif en août ?']));
        $missed = $this->scorer->score($this->case(needsClarification: true), $this->extraction());

        self::assertTrue($needless->needlessQuestion());
        self::assertFalse($needless->passed());
        self::assertTrue($missed->missedQuestion());
        self::assertFalse($missed->passed());
    }

    public function testAPeriodReturnedTwiceCountsTwice(): void
    {
        $period = $this->periodData(650, false);
        $actual = ListingExtraction::fromArray([
            'periods' => [$period, $period],
            'unavailable' => [['start' => '2026-07-08', 'end' => '2026-07-15']],
        ]);

        self::assertCount(1, $this->scorer->score($this->case(), $actual)->extraPeriods);
    }

    public function testTakenWeeksThatFollowEachOtherAreTheSameDays(): void
    {
        $weekByWeek = $this->extraction(unavailable: [
            ['start' => '2026-07-11', 'end' => '2026-07-15'],
            ['start' => '2026-07-08', 'end' => '2026-07-11'],
        ]);

        self::assertTrue($this->scorer->score($this->case(), $weekByWeek)->passed());
    }

    public function testADateJustBeforeAnAmountIsNotAThousandsGroup(): void
    {
        self::assertContains(700, SourceAmounts::in('11/07 au 18/07 700€/semaine'));
        self::assertNotContains(7700, SourceAmounts::in('11/07 au 18/07 700€/semaine'));
        self::assertContains(2950, SourceAmounts::in('Août : 2 950 € la quinzaine'));
    }

    private function case(bool $needsClarification = false): EvalCase
    {
        return EvalCase::fromArray([
            'id' => 'test',
            'text' => self::TEXT,
            'publishedAt' => '2026-03-01',
            'expected' => [
                'periods' => [$this->periodData(650, false)],
                'unavailable' => [['start' => '2026-07-08', 'end' => '2026-07-15']],
                'needsClarification' => $needsClarification,
            ],
        ]);
    }

    /**
     * @param list<array{start: string, end: string}>|null $unavailable
     * @param list<string>                                 $questions
     */
    private function extraction(int $amount = 650, bool $saturday = false, ?array $unavailable = null, array $questions = []): ListingExtraction
    {
        return ListingExtraction::fromArray([
            'periods' => [$this->periodData($amount, $saturday)],
            'unavailable' => $unavailable ?? [['start' => '2026-07-08', 'end' => '2026-07-15']],
            'questions' => $questions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function periodData(int $amount, bool $saturday): array
    {
        return [
            'label' => 'juillet',
            'start' => '2026-07-01',
            'end' => '2026-08-01',
            'prices' => [['amount' => $amount, 'unit' => 'week']],
            'minimumNights' => null,
            'saturdayArrival' => $saturday,
        ];
    }
}
