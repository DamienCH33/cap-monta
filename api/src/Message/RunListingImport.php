<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Read one pasted listing with the assistant. Handled by the worker, never during the owner's
 * HTTP request: a slow or failing provider never blocks a page (ADR 027).
 */
final readonly class RunListingImport
{
    public function __construct(
        public string $listingImportId,
    ) {
    }
}
