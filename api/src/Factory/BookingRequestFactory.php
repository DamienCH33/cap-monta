<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\BookingRequest;
use App\Enum\BookingRequestStatus;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BookingRequest>
 */
final class BookingRequestFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return BookingRequest::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return static function (): array {
            $start = new \DateTimeImmutable('monday next week');

            return [
                'accommodation' => AccommodationFactory::new(),
                'startDate' => $start,
                'endDate' => $start->modify('+7 days'),
                'adults' => 2,
                'children' => 0,
                'guestName' => self::faker()->name(),
                'guestEmail' => self::faker()->email(),
                'status' => BookingRequestStatus::Pending,
            ];
        };
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::withConstructor());
    }
}
