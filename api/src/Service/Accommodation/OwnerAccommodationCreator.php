<?php

declare(strict_types=1);

namespace App\Service\Accommodation;

use App\Dto\CreateAccommodationInput;
use App\Entity\Accommodation;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\PetsPolicy;
use App\Enum\Resort;
use App\Repository\DistrictRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A new draft for an owner, from an input already validated. Used by the form
 * (POST /api/owner/accommodations) and by the listing import: the same slug, the same rules.
 */
final readonly class OwnerAccommodationCreator
{
    public function __construct(
        private EntityManagerInterface $em,
        private DistrictRepository $districts,
        private AccommodationSlugger $slugger,
    ) {
    }

    /**
     * Persisted, not flushed: the caller decides when the whole operation is written.
     */
    public function create(User $owner, CreateAccommodationInput $data): Accommodation
    {
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
        $accommodation->setPetsPolicy(PetsPolicy::from($data->petsPolicy));

        $this->em->persist($accommodation);

        return $accommodation;
    }
}
