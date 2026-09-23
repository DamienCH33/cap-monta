<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

/**
 * The extraction does not follow the expected shape: a missing key, a malformed date, an end
 * before its start. Raised on the agent's output as well as on a hand-written test case.
 */
final class InvalidExtractionException extends \InvalidArgumentException
{
}
