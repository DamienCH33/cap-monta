<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Enum\BookingRefusalReason;

/**
 * Ce que coûte un séjour, et pourquoi il serait refusé le cas échéant.
 *
 * Aucune écriture, aucun effet de bord : le même objet répond à l'API de devis
 * et sert de feu vert à la création d'une demande.
 */
final readonly class Quote
{
    public function __construct(
        public int $nights,
        public int $guests,
        public int $maxCapacity,
        public int $minimumNights,
        public ?int $total,
        public ?BookingRefusalReason $refusal,
        /**
         * The stay ignores a preference of the owner (arrival day): the request is sent all
         * the same, flagged for the owner. Unlike $refusal, it never blocks anything.
         */
        public bool $outsideRules = false,
        /** @var list<QuoteExtra> fees on top of the rent declared by the owner */
        public array $extras = [],
        /** @var list<string> mandatory fees the owner has not stated (QuoteExtra codes) */
        public array $unknownFees = [QuoteExtra::TOURIST_TAX, QuoteExtra::RESORT_FEE],
    ) {
    }

    public function isAvailable(): bool
    {
        return null === $this->refusal;
    }

    /** Rent plus the mandatory fees; null while the rent is "à convenir". */
    public function estimatedTotal(): ?int
    {
        if (null === $this->total) {
            return null;
        }

        $mandatory = array_filter($this->extras, static fn (QuoteExtra $extra): bool => !$extra->optional);

        return $this->total + array_sum(array_map(static fn (QuoteExtra $extra): int => $extra->amount, $mandatory));
    }
}
