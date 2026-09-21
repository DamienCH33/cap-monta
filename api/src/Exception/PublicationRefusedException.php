<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * An accommodation cannot go (back) online. Carries what is missing so the API
 * can answer a 422 the front can map onto the form fields.
 */
final class PublicationRefusedException extends \DomainException
{
    /**
     * @param list<'description'|'district'|'photos'> $missing
     */
    private function __construct(
        string $message,
        public readonly bool $ownerUnverified,
        public readonly array $missing,
    ) {
        parent::__construct($message);
    }

    public static function unverifiedOwner(): self
    {
        return new self('The owner must confirm their email address first.', true, []);
    }

    /**
     * @param list<'description'|'district'|'photos'> $missing
     */
    public static function incomplete(array $missing): self
    {
        return new self('The listing is incomplete: '.implode(', ', $missing).'.', false, $missing);
    }
}
