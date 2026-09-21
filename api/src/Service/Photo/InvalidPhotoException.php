<?php

declare(strict_types=1);

namespace App\Service\Photo;

/**
 * The file is not a picture PHP can read. Its message is shown to the owner as is.
 */
final class InvalidPhotoException extends \DomainException
{
}
