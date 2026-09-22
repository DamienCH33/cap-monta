<?php

declare(strict_types=1);

namespace App\Service\Booking;

/**
 * An answer to a booking request that cannot be applied: already answered, dates taken
 * in the meantime, missing price... Carries the field and the HTTP status the front expects,
 * and a message written for the person who clicked.
 */
final class BookingAnswerRefused extends \DomainException
{
    private function __construct(
        string $message,
        public readonly string $field,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function notWaiting(): self
    {
        return new self('Cette demande n’attend plus de réponse : rechargez la page.', 'status', 409);
    }

    public static function expired(): self
    {
        return new self('Le délai de 48 heures est dépassé : la demande a expiré et le voyageur en est prévenu.', 'status', 409);
    }

    public static function datesTaken(): self
    {
        return new self('Ces dates ne sont plus libres dans votre calendrier : refusez la demande ou libérez les dates.', 'dates', 409);
    }

    public static function priceRequired(): self
    {
        return new self('Indiquez le prix du séjour : aucun tarif ne couvre ces dates.', 'price', 422);
    }

    public static function invalidPrice(): self
    {
        return new self('Le prix doit être compris entre 1 € et 100 000 €.', 'price', 422);
    }

    public static function messageTooLong(): self
    {
        return new self(sprintf('Le message fait %d caractères au plus.', BookingDesk::MESSAGE_MAX_LENGTH), 'message', 422);
    }

    public static function alreadyStarted(): self
    {
        return new self('Le séjour a commencé : il ne peut plus être modifié ici.', 'status', 409);
    }

    public static function notCancellable(): self
    {
        return new self('Seule une réservation acceptée peut être annulée : une demande en attente se refuse.', 'status', 409);
    }
}
