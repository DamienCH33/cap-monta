<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Enum\PriceUnit;
use App\Service\ListingImport\Evaluation\SourceAmounts;
use App\Service\ListingImport\InvalidExtractionException;
use App\Service\ListingImport\ListingExtraction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListingExtractionTest extends TestCase
{
    public function testAPeriodWithoutDatesKeepsItsPrice(): void
    {
        $extraction = ListingExtraction::fromArray([
            'periods' => [[
                'label' => 'hors saison',
                'start' => null,
                'end' => null,
                'prices' => [['amount' => 400, 'unit' => 'week'], ['amount' => 70, 'unit' => 'night']],
            ]],
            'unavailable' => [],
            'questions' => ['À quelles dates correspond « hors saison » ?', '  '],
        ]);

        $period = $extraction->periods[0];
        self::assertNull($period->start);
        self::assertSame(PriceUnit::Night, $period->prices[1]->unit);
        self::assertSame([400, 70], $extraction->amounts());
        self::assertCount(1, $extraction->questions, 'blank questions are dropped');
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('invalidExtractions')]
    public function testAMalformedExtractionIsRefused(array $data): void
    {
        $this->expectException(InvalidExtractionException::class);

        ListingExtraction::fromArray($data);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidExtractions(): iterable
    {
        $period = static fn (array $override): array => [
            'periods' => [array_replace(['label' => 'x', 'start' => '2026-07-01', 'end' => '2026-08-01', 'prices' => []], $override)],
            'unavailable' => [],
        ];

        yield 'no periods key' => [['unavailable' => []]];
        yield 'end before start' => [$period(['end' => '2026-06-01'])];
        yield 'impossible date' => [$period(['start' => '2026-02-30'])];
        yield 'french date' => [$period(['start' => '01/07/2026'])];
        yield 'unknown unit' => [$period(['prices' => [['amount' => 500, 'unit' => 'month']]])];
        yield 'price as text' => [$period(['prices' => [['amount' => '500', 'unit' => 'week']]])];
        yield 'zero price' => [$period(['prices' => [['amount' => 0, 'unit' => 'week']]])];
        yield 'open unavailability' => [['periods' => [], 'unavailable' => [['start' => '2026-08-22', 'end' => null]]]];
    }

    public function testAmountsAreReadWithTheirThousandsSeparators(): void
    {
        $amounts = SourceAmounts::in('1.000€ la semaine, 2 950 € les 3 semaines, 1200€, 18€du 27/6');

        foreach ([1000, 2950, 1200, 18, 27, 6] as $amount) {
            self::assertContains($amount, $amounts);
        }
        self::assertSame([600], SourceAmounts::invented([1200, 600], '1200€ pour 2 semaines'));
    }
}
