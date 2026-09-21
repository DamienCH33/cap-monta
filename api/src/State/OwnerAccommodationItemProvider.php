<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OwnerAccommodationResource;
use App\Repository\AccommodationRepository;
use App\Security\Voter\AccommodationVoter;
use App\Service\Photo\PhotoStorage;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /api/owner/accommodations/{slug}.
 *
 * Someone else's accommodation gives a 404, not a 403: a 403 would confirm
 * that the slug exists, and a draft's slug has never been public.
 *
 * @implements ProviderInterface<OwnerAccommodationResource>
 */
final readonly class OwnerAccommodationItemProvider implements ProviderInterface
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private Security $security,
        private PhotoStorage $photos,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OwnerAccommodationResource
    {
        $slug = $uriVariables['slug'] ?? null;

        if (!is_string($slug)) {
            return null;
        }

        $accommodation = $this->accommodations->findOneBy(['slug' => $slug]);

        if (null === $accommodation || !$this->security->isGranted(AccommodationVoter::VIEW, $accommodation)) {
            return null;
        }

        return OwnerAccommodationResource::fromEntity($accommodation, $this->photos);
    }
}
