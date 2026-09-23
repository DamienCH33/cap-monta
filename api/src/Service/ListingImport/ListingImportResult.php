<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\ListingImportFailure;

/**
 * The outcome of one import. A failure is a normal result, not an exception: the owner's screen
 * falls back to the empty form (ADR 008, the AI assists, the site works without it).
 */
final readonly class ListingImportResult
{
    /**
     * @param list<string> $contacts phone numbers and emails found in the text
     */
    public function __construct(
        public string $model,
        public ?ListingExtraction $extraction,
        public array $contacts,
        public int $durationMs,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?string $error = null,
        public mixed $rawOutput = null,
        public ?ListingImportFailure $failure = null,
        /** Seconds the provider asked to wait (rate limit), if it said so. */
        public ?int $retryAfter = null,
    ) {
    }

    public function succeeded(): bool
    {
        return null !== $this->extraction;
    }
}
