<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

/**
 * Dates of an extraction: "Y-m-d" strings or null. The end is exclusive, like everywhere else
 * in the application ("du 4 au 11 juillet" = arrival on the 4th, departure on the 11th).
 */
final class ExtractionDates
{
    public static function parse(mixed $value, string $field, bool $nullable): ?\DateTimeImmutable
    {
        if (null === $value && $nullable) {
            return null;
        }

        if (!\is_string($value)) {
            throw new InvalidExtractionException(\sprintf('« %s » doit être une date AAAA-MM-JJ%s.', $field, $nullable ? ' ou null' : ''));
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidExtractionException(\sprintf('« %s » n\'est pas une date valide : %s.', $field, $value));
        }

        return $date;
    }

    public static function checkOrder(?\DateTimeImmutable $start, ?\DateTimeImmutable $end): void
    {
        if (null !== $start && null !== $end && $end <= $start) {
            throw new InvalidExtractionException(\sprintf('La fin (%s) doit suivre le début (%s).', $end->format('Y-m-d'), $start->format('Y-m-d')));
        }
    }

    public static function format(?\DateTimeImmutable $date): string
    {
        return $date?->format('Y-m-d') ?? '?';
    }
}
