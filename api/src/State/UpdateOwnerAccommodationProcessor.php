<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OwnerAccommodationResource;
use App\Dto\UpdateAccommodationInput;
use App\Entity\District;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Repository\AccommodationRepository;
use App\Repository\DistrictRepository;
use App\Security\Voter\AccommodationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * PATCH /api/owner/accommodations/{slug}: only the fields that were sent change.
 *
 * A published accommodation is edited in place, changes are public at once.
 * One condition: an edit that would make it incomplete is refused while it is published.
 * On a 422 nothing is flushed, so the database is untouched.
 *
 * @implements ProcessorInterface<UpdateAccommodationInput, OwnerAccommodationResource>
 */
final readonly class UpdateOwnerAccommodationProcessor implements ProcessorInterface
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private DistrictRepository $districts,
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OwnerAccommodationResource
    {
        $slug = $uriVariables['slug'] ?? null;
        $accommodation = is_string($slug) ? $this->accommodations->findOneBy(['slug' => $slug]) : null;

        // 404 and not 403 for someone else's accommodation: the slug must not leak.
        if (null === $accommodation || !$this->security->isGranted(AccommodationVoter::EDIT, $accommodation)) {
            throw new NotFoundHttpException();
        }

        $sent = $data->provided();

        if (array_key_exists('type', $sent)) {
            $accommodation->setType(AccommodationType::from($data->type));
        }
        if (array_key_exists('capacity', $sent)) {
            $accommodation->setCapacity($data->capacity);
            $accommodation->setMaxCapacity($data->capacity);
        }
        if (array_key_exists('bedrooms', $sent)) {
            $accommodation->setBedrooms($data->bedrooms);
        }
        if (array_key_exists('surface', $sent)) {
            $accommodation->setSurface($data->surface);
        }
        if (array_key_exists('amenities', $sent)) {
            $accommodation->setAmenities(array_values(array_unique($data->amenities)));
        }
        if (array_key_exists('description', $sent)) {
            $accommodation->setDescription(trim($data->description));
        }
        if (array_key_exists('district', $sent)) {
            $accommodation->setDistrict($this->resolveDistrict($data->district, $accommodation->getResort()));
        }

        if ($accommodation->isPublished()) {
            $missing = $accommodation->missingForPublication();

            if ([] !== $missing) {
                throw new UnprocessableEntityHttpException(implode("\n", array_map(static fn (string $field): string => match ($field) {
                    'description' => sprintf("Un logement publié doit garder une description d'au moins %d caractères.", \App\Entity\Accommodation::MIN_DESCRIPTION_LENGTH), 'district' => 'Un logement publié au CHM doit garder son quartier.',
                }, $missing)));
            }
        }

        $accommodation->touch();
        $this->em->flush();

        return OwnerAccommodationResource::fromEntity($accommodation);
    }

    private function resolveDistrict(?string $slug, Resort $resort): ?District
    {
        if (null === $slug) {
            return null;
        }

        // The front offers a list: an unknown slug is a bug or a forged request.
        return $this->districts->findOneBy(['slug' => $slug, 'resort' => $resort])
            ?? throw new UnprocessableEntityHttpException('Quartier inconnu pour ce domaine.');
    }
}
