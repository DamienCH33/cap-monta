<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Service\ListingImport\ImportProposal;
use App\Service\ListingImport\ListingExtraction;
use PHPUnit\Framework\TestCase;

/**
 * The check screen of lot 4c gets rows it can save as they are: cents, both bounds, a flag
 * wherever the owner has a decision to make. Nothing ticked that would be refused.
 */
final class ImportProposalTest extends TestCase
{
    private const array DISTRICTS = ['La Lande' => ['resort' => 'chm', 'slug' => 'la-lande']];

    public function testPricesBecomeCentsAndAMissingUnitIsProposedAsTheWeek(): void
    {
        $proposal = $this->build([
            $this->period('juillet', '2027-07-01', '2027-08-01', [[650, 'week'], [100, 'night']], 3, true),
            $this->period('septembre', '2027-09-01', '2027-10-01', [[420, 'unknown']]),
        ]);

        self::assertSame([65000, 10000, 3, true, false, true], $this->pick($proposal['periods'][0], 'weeklyPrice', 'nightlyPrice', 'minimumNights', 'saturdayArrival', 'unitToConfirm', 'selected'));
        self::assertSame([42000, null, 1, true, true], $this->pick($proposal['periods'][1], 'weeklyPrice', 'nightlyPrice', 'minimumNights', 'unitToConfirm', 'selected'));
    }

    public function testAStayPriceIsSpreadOverItsNightsOnlyWhenTheDatesAreKnown(): void
    {
        $proposal = $this->build([
            $this->period('du 25 juillet au 8 août', '2027-07-25', '2027-08-08', [[1200, 'stay']]),
            $this->period('un week-end', '2027-05-14', '2027-05-16', [[150, 'stay']]),
            $this->period('au printemps', null, null, [[900, 'stay']]),
        ]);

        self::assertSame([60000, null, 120000], $this->pick($proposal['periods'][0], 'weeklyPrice', 'nightlyPrice', 'stayPrice'));
        self::assertSame([null, 7500], $this->pick($proposal['periods'][1], 'weeklyPrice', 'nightlyPrice'));
        self::assertSame([null, null, true, false], $this->pick($proposal['periods'][2], 'weeklyPrice', 'nightlyPrice', 'datesMissing', 'selected'));
    }

    public function testPastAndTooFarRowsAreProposedUnticked(): void
    {
        $proposal = $this->build([
            $this->period('avril 2024', '2024-04-01', '2024-05-01', [[375, 'week']]),
            $this->period('été 2030', '2030-07-01', '2030-08-01', [[900, 'week']]),
        ]);

        self::assertSame([true, false], $this->pick($proposal['periods'][0], 'past', 'selected'));
        self::assertSame([true, false], $this->pick($proposal['periods'][1], 'tooFar', 'selected'));
    }

    public function testTakenDatesAreMergedAndCutAtToday(): void
    {
        $proposal = $this->build([], [
            ['start' => '2026-09-20', 'end' => '2026-10-03'],
            ['start' => '2026-10-03', 'end' => '2026-10-10'],
            ['start' => '2026-08-01', 'end' => '2026-08-15'],
            ['start' => '2028-07-01', 'end' => '2028-08-01'],
        ]);

        self::assertSame([
            ['start' => '2026-09-24', 'end' => '2026-10-10', 'tooFar' => false, 'selected' => true],
            ['start' => '2028-07-01', 'end' => '2028-08-01', 'tooFar' => true, 'selected' => false],
        ], $proposal['unavailable']);
    }

    public function testTheFormIsFilledAndTheContactsLeaveTheDescription(): void
    {
        $proposal = ImportProposal::build(
            ListingExtraction::fromArray(['periods' => [], 'unavailable' => [], 'listing' => [
                'type' => 'mobile_home', 'capacity' => 4, 'bedrooms' => 2, 'district' => 'La Lande',
                'amenities' => ['television'], 'petsPolicy' => 'not_allowed', 'otherFeatures' => ['transats'],
            ]]),
            ['06 11 22 33 44'],
            "Mobil home La Lande, TV, transats.\nAppelez le 06 11 22 33 44  !",
            self::DISTRICTS,
            new \DateTimeImmutable('2026-09-24'),
        );

        self::assertSame('chm', $proposal['listing']['resort']);
        self::assertSame('la-lande', $proposal['listing']['district']);
        self::assertSame(['television'], $proposal['listing']['amenities']);
        self::assertSame('not_allowed', $proposal['listing']['petsPolicy']);
        self::assertSame("Mobil home La Lande, TV, transats.\nAppelez le !", $proposal['listing']['description']);
    }

    public function testTheDomainComesFromTheTextAndTickedEquipmentIsNotRepeated(): void
    {
        $proposal = ImportProposal::build(
            ListingExtraction::fromArray(['periods' => [], 'unavailable' => [], 'listing' => [
                'type' => 'bungalow', 'district' => 'Tahiti', 'amenities' => ['lave-linge'],
                'otherFeatures' => ['machine à laver', 'kayak de mer'],
            ]]),
            [],
            'Bungalow au CHM Montalivet, machine à laver, kayak de mer.',
            self::DISTRICTS,
            new \DateTimeImmutable('2026-09-24'),
        );

        self::assertSame('chm', $proposal['listing']['resort']);
        self::assertSame(['kayak de mer'], $proposal['listing']['otherFeatures']);
    }

    /**
     * @param list<array<string, mixed>>              $periods
     * @param list<array{start: string, end: string}> $unavailable
     *
     * @return array<string, mixed>
     */
    private function build(array $periods, array $unavailable = []): array
    {
        return ImportProposal::build(
            ListingExtraction::fromArray(['periods' => $periods, 'unavailable' => $unavailable]),
            [],
            'texte',
            self::DISTRICTS,
            new \DateTimeImmutable('2026-09-24'),
        );
    }

    /**
     * @param list<array{0: int, 1: string}> $prices
     *
     * @return array<string, mixed>
     */
    private function period(string $label, ?string $start, ?string $end, array $prices, ?int $minimum = null, bool $saturday = false): array
    {
        return [
            'label' => $label, 'start' => $start, 'end' => $end,
            'prices' => array_map(static fn (array $p): array => ['amount' => $p[0], 'unit' => $p[1]], $prices),
            'minimumNights' => $minimum, 'saturdayArrival' => $saturday,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<mixed>
     */
    private function pick(array $row, string ...$keys): array
    {
        return array_map(static fn (string $key): mixed => $row[$key], array_values($keys));
    }
}
