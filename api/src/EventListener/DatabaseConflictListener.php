<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The database is the last guard against double bookings (EXCLUDE USING gist, ADR 010) and
 * duplicate pending requests (unique index). When two writes race past the checks made in
 * PHP, it refuses the second one. That refusal is a conflict the user can act on, not a
 * server error: 409 with a sentence, instead of a 500.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class DatabaseConflictListener
{
    /** PostgreSQL: exclusion_violation. DBAL has no dedicated exception for it. */
    private const EXCLUSION_VIOLATION = '23P01';

    public function __invoke(ExceptionEvent $event): void
    {
        for ($error = $event->getThrowable(); null !== $error; $error = $error->getPrevious()) {
            if ($error instanceof DriverException && self::EXCLUSION_VIOLATION === $error->getSQLState()) {
                $event->setResponse(self::conflict('Ces dates viennent d’être prises. Actualisez la page et choisissez-en d’autres.'));

                return;
            }

            if ($error instanceof UniqueConstraintViolationException) {
                $event->setResponse(self::conflict('Cette action vient déjà d’être enregistrée. Actualisez la page.'));

                return;
            }
        }
    }

    private static function conflict(string $detail): JsonResponse
    {
        return new JsonResponse(
            ['title' => 'Conflit', 'status' => 409, 'detail' => $detail],
            409,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
