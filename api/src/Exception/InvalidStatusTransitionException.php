<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A status change that makes no sense from the current status (archiving a draft).
 * The API answers it with a 409 Conflict.
 */
final class InvalidStatusTransitionException extends \DomainException
{
}
