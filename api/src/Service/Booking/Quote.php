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
    ) {
    }

    public function isAvailable(): bool
    {
        return null === $this->refusal;
    }
}
