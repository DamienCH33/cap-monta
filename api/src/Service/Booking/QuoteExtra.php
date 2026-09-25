<?php

declare(strict_types=1);

namespace App\Service\Booking;

/**
 * A fee on top of the rent, as the owner declared it, computed for this stay.
 */
final readonly class QuoteExtra
{
    public const TOURIST_TAX = 'tourist_tax';
    public const RESORT_FEE = 'resort_fee';
    public const CLEANING = 'cleaning';
    public const LINEN = 'linen';

    public function __construct(
        public string $code,
        /** Cents, for the whole stay. */
        public int $amount,
        /** Taken only if the traveller asks for it (cleaning, linen): not in the estimated total. */
        public bool $optional,
    ) {
    }

    /** @return array{code: string, amount: int, optional: bool} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'amount' => $this->amount, 'optional' => $this->optional];
    }
}
