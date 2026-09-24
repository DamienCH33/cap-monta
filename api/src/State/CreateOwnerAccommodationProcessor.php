<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OwnerAccommodationResource;
use App\Dto\CreateAccommodationInput;
use App\Entity\User;
use App\Service\Accommodation\OwnerAccommodationCreator;
use App\Service\Photo\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * POST /api/owner/accommodations: a new draft owned by the logged-in user.
 *
 * The input has already been validated by API Platform when we get here.
 *
 * @implements ProcessorInterface<CreateAccommodationInput, OwnerAccommodationResource>
 */
final readonly class CreateOwnerAccommodationProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private OwnerAccommodationCreator $creator,
        private PhotoStorage $photos,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OwnerAccommodationResource
    {
        $owner = $this->security->getUser();

        // access_control already refuses anonymous visitors: this is a safety net.
        if (!$owner instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $accommodation = $this->creator->create($owner, $data);
        $this->em->flush();

        return OwnerAccommodationResource::fromEntity($accommodation, $this->photos);
    }
}
