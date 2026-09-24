<?php

declare(strict_types=1);

namespace App\Service\Pricing;

/**
 * What a rate period must respect before it is saved, whoever writes it: the rates screen or
 * the listing import. One definition, the same messages everywhere.
 */
final class RateRules
{
    public const string HORIZON = '+24 months';
    public const int MAX_MINIMUM_NIGHTS = 28;
    public const int MIN_PRICE = 100;
    public const int MAX_PRICE = 10_000_000;

    /**
     * @return array{0: string, 1: string}|null the field and the message, null when acceptable
     */
    public static function refusal(
        ?\DateTimeImmutable $start,
        ?\DateTimeImmutable $end,
        mixed $weekly,
        mixed $nightly,
        mixed $minimum,
        mixed $saturday,
        \DateTimeImmutable $today,
    ): ?array {
        return match (true) {
            null === $start => ['start', 'Choisissez le premier jour de la période.'],
            null === $end => ['end', 'Choisissez le jour où la période se termine.'],
            $end <= $start => ['end', 'La fin doit venir après le début.'],
            $end <= $today => ['end', 'Cette période est entièrement passée.'],
            $end > $today->modify(self::HORIZON) => ['end', 'Les tarifs se saisissent sur deux ans au plus.'],
            !self::validPrice($weekly) => ['weeklyPrice', 'Le prix à la semaine doit être compris entre 1 € et 100 000 €.'],
            !self::validPrice($nightly) => ['nightlyPrice', 'Le prix à la nuit doit être compris entre 1 € et 100 000 €.'],
            null === $weekly && null === $nightly => ['weeklyPrice', 'Indiquez au moins un prix : à la semaine ou à la nuit.'],
            !\is_int($minimum) || $minimum < 1 || $minimum > self::MAX_MINIMUM_NIGHTS => ['minimumNights', \sprintf('Le minimum de nuits va de 1 à %d.', self::MAX_MINIMUM_NIGHTS)],
            !\is_bool($saturday) => ['saturdayArrival', 'Réponse attendue : oui ou non.'],
            default => null,
        };
    }

    /** A YYYY-MM-DD date, or null if absent or malformed (2026-02-30 included). */
    public static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function validPrice(mixed $price): bool
    {
        return null === $price || (\is_int($price) && $price >= self::MIN_PRICE && $price <= self::MAX_PRICE);
    }
}
