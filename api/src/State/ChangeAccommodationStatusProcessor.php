<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OwnerAccommodationResource;
use App\Entity\Accommodation;
use App\Exception\InvalidStatusTransitionException;
use App\Exception\PublicationRefusedException;
use App\Repository\AccommodationRepository;
use App\Security\Voter\AccommodationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * POST /api/owner/accommodations/{slug}/publish and /archive.
 *
 * The rules live in the entity (publish(), archive()); this class only loads the
 * accommodation, asks the voter, and turns domain refusals into HTTP answers the
 * owner can act on: 422 with what to fix, 409 for a transition that makes no sense.
 *
 * @implements ProcessorInterface<mixed, OwnerAccommodationResource>
 */
final readonly class ChangeAccommodationStatusProcessor implements ProcessorInterface
{
    public const string PUBLISH = 'publish';
    public const string ARCHIVE = 'archive';

    public function __construct(
        private AccommodationRepository $accommodations,
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

        $transition = $operation->getExtraProperties()['transition'] ?? null;

        try {
            if (self::PUBLISH === $transition) {
                $accommodation->publish();
            } elseif (self::ARCHIVE === $transition) {
                $accommodation->archive();
            } else {
                throw new \LogicException('Unknown status transition.');
            }
        } catch (PublicationRefusedException $e) {
            throw new UnprocessableEntityHttpException($this->explain($e), $e);
        } catch (InvalidStatusTransitionException $e) {
            throw new ConflictHttpException("Un brouillon n'a jamais été en ligne : il n'y a rien à retirer du site.", $e);
        }

        $this->em->flush();

        return OwnerAccommodationResource::fromEntity($accommodation);
    }

    private function explain(PublicationRefusedException $e): string
    {
        if ($e->ownerUnverified) {
            return "Confirmez d'abord votre adresse email : le lien vous a été envoyé à l'inscription.";
        }

        return implode("\n", array_map(
            static fn (string $field): string => match ($field) {
                'description' => sprintf("Ajoutez une description d'au moins %d caractères.", Accommodation::MIN_DESCRIPTION_LENGTH),
                'district' => 'Choisissez le quartier du logement.',
            },
            $e->missing,
        ));
    }
}
