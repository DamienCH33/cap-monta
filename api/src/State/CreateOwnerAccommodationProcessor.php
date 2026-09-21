<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OwnerAccommodationResource;
use App\Dto\CreateAccommodationInput;
use App\Entity\Accommodation;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Repository\DistrictRepository;
use App\Service\Accommodation\AccommodationSlugger;
use App\Service\Photo\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
        private DistrictRepository $districts,
        private AccommodationSlugger $slugger,
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

        $resort = Resort::from((string) $data->resort);
        $type = AccommodationType::from((string) $data->type);
        $capacity = (int) $data->capacity;

        $district = null;

        if (null !== $data->district) {
            $district = $this->districts->findOneBy(['slug' => $data->district, 'resort' => $resort]);

            // The front offers a list: an unknown slug is a bug or a forged request.
            if (null === $district) {
                throw new UnprocessableEntityHttpException('Quartier inconnu pour ce domaine.');
            }
        }

        $accommodation = new Accommodation(
            $this->slugger->generate($type, $district?->getSlug() ?? $resort->value, $capacity),
            $resort,
            $type,
            $capacity,
            (int) $data->bedrooms,
            $data->description,
            $owner,
        );
        $accommodation->setDistrict($district);
        $accommodation->setSurface($data->surface);
        $accommodation->setAmenities(array_values(array_unique($data->amenities)));

        $this->em->persist($accommodation);
        $this->em->flush();

        return OwnerAccommodationResource::fromEntity($accommodation, $this->photos);
    }
}
