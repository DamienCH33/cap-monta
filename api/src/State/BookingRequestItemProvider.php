<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BookingRequestResource;
use App\Repository\BookingRequestRepository;
use Symfony\Component\Uid\Uuid;

/**
 * GET /api/booking-requests/{id}.
 *
 * @implements ProviderInterface<BookingRequestResource>
 */
final readonly class BookingRequestItemProvider implements ProviderInterface
{
    public function __construct(private BookingRequestRepository $requests)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?BookingRequestResource
    {
        $id = $uriVariables['id'] ?? null;

        // A malformed identifier is a 404, not a 500.
        if (!is_string($id) || !Uuid::isValid($id)) {
            return null;
        }

        $request = $this->requests->find(Uuid::fromString($id));

        return null === $request ? null : BookingRequestResource::fromEntity($request);
    }
}
