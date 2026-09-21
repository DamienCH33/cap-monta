<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OwnerAccommodationResource;
use App\Entity\User;
use App\Repository\AccommodationRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /api/owner/accommodations: the logged-in owner's accommodations, every status.
 *
 * @implements ProviderInterface<OwnerAccommodationResource>
 */
final readonly class OwnerAccommodationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<OwnerAccommodationResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $owner = $this->security->getUser();

        // access_control already refuses anonymous visitors: this is a safety net.
        if (!$owner instanceof User) {
            return [];
        }

        return array_map(
            OwnerAccommodationResource::fromEntity(...),
            $this->accommodations->findByOwner($owner),
        );
    }
}
