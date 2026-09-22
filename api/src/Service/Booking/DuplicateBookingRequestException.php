<?php

declare(strict_types=1);

namespace App\Service\Booking;

/**
 * The same guest already has a request waiting for the same accommodation and dates: a
 * double click, or a second try because the first email has not arrived yet. Refused rather
 * than sending the owner two identical requests.
 */
final class DuplicateBookingRequestException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Vous avez déjà envoyé une demande pour ces dates : elle attend la réponse du propriétaire. Retrouvez-la dans l’email reçu, ou sur « Mes demandes ».');
    }
}
