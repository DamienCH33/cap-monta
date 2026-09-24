<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ListingImport;
use App\Entity\User;
use App\Enum\ListingImportStatus;
use App\Message\RunListingImport;
use App\Repository\AccommodationRepository;
use App\Repository\DistrictRepository;
use App\Repository\ListingImportRepository;
use App\Security\Voter\AccommodationVoter;
use App\Service\ListingImport\AssistantAvailability;
use App\Service\ListingImport\ImportApplier;
use App\Service\ListingImport\ImportProposal;
use App\Service\ListingImport\InvalidExtractionException;
use App\Service\ListingImport\ListingExtraction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * "Import from my listing" in the owner's space (ADR 027).
 *
 * POST stores the text and answers at once (202): the reading happens in the worker. The front
 * polls GET until the import is done or failed. Nothing here ever waits for the AI provider.
 *
 * What protects the free plan of the provider: a confirmed address, 10 readings per owner and
 * per day, 300 for the whole site, and the same text read only once.
 */
#[Route('/api/owner/listing-imports')]
final readonly class OwnerListingImportController
{
    /** The same text pasted again within this delay gets the previous reading back. */
    private const string REUSE_WITHIN = '-30 days';

    public function __construct(
        private ListingImportRepository $imports,
        private AssistantAvailability $availability,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private Security $security,
        private ClockInterface $clock,
        #[Autowire(service: 'limiter.listing_imports_owner')]
        private RateLimiterFactoryInterface $ownerLimiter,
        #[Autowire(service: 'limiter.listing_imports_all')]
        private RateLimiterFactoryInterface $siteLimiter,
        private DistrictRepository $districts,
        private AccommodationRepository $accommodations,
        private ImportApplier $applier,
    ) {
    }

    /** Whether to show the "import" button at all. */
    #[Route('/assistant', name: 'api_owner_listing_import_assistant', methods: ['GET'])]
    public function assistant(): JsonResponse
    {
        return new JsonResponse($this->availabilityView());
    }

    #[Route('', name: 'api_owner_listing_import_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $owner = $this->owner();

        if (!$owner->isVerified()) {
            return $this->refuse(Response::HTTP_FORBIDDEN, 'Confirmez d\'abord votre adresse email (lien reçu à l\'inscription) pour utiliser l\'assistant.');
        }

        $payload = json_decode($request->getContent(), true);
        $text = \is_array($payload) && \is_string($payload['text'] ?? null) ? trim($payload['text']) : '';

        if (mb_strlen($text) < ListingImport::MIN_TEXT_LENGTH) {
            return $this->invalid(\sprintf('Collez le texte de votre annonce (au moins %d caractères).', ListingImport::MIN_TEXT_LENGTH));
        }
        if (mb_strlen($text) > ListingImport::MAX_TEXT_LENGTH) {
            return $this->invalid(\sprintf('Texte trop long : %d caractères au plus. Gardez les tarifs, les disponibilités et la description.', ListingImport::MAX_TEXT_LENGTH));
        }

        if (!$this->availability->isAvailable()) {
            return new JsonResponse(['code' => 'assistant_unavailable', ...$this->availabilityView()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // The same listing again: no second reading, the first one is given back.
        $existing = $this->imports->findReusable($owner, $text, $this->clock->now()->modify(self::REUSE_WITHIN));
        if (null !== $existing) {
            return new JsonResponse($this->view($existing), Response::HTTP_OK);
        }

        $ownerQuota = $this->ownerLimiter->create($owner->getId()->toRfc4122())->consume();
        if (!$ownerQuota->isAccepted()) {
            return $this->refuse(Response::HTTP_TOO_MANY_REQUESTS, 'Vous avez utilisé toutes vos lectures du jour. Réessayez demain, ou remplissez le formulaire vous-même.');
        }
        if (!$this->siteLimiter->create('site')->consume()->isAccepted()) {
            return $this->refuse(Response::HTTP_TOO_MANY_REQUESTS, 'L\'assistant a beaucoup travaillé aujourd\'hui. Réessayez demain, ou remplissez le formulaire vous-même.');
        }

        $import = new ListingImport($owner, $text, $this->clock->now());
        $this->em->persist($import);
        $this->em->flush();
        $this->bus->dispatch(new RunListingImport($import->getId()->toRfc4122()));

        return new JsonResponse($this->view($import), Response::HTTP_ACCEPTED);
    }

    #[Route('/{id}', name: 'api_owner_listing_import_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        return new JsonResponse($this->view($this->ownedImport($id)));
    }

    /**
     * The owner validated the check screen (lot 4c): a new draft, or one of his accommodations
     * ({"slug": …}), gets the rates and taken dates he kept. All or nothing.
     */
    #[Route('/{id}/apply', name: 'api_owner_listing_import_apply', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function apply(string $id, Request $request): JsonResponse
    {
        $import = $this->ownedImport($id);

        if (ListingImportStatus::Done !== $import->getStatus()) {
            return $this->refuse(Response::HTTP_CONFLICT, 'La lecture de votre annonce n\'est pas terminée.');
        }
        if ($import->isApplied()) {
            return new JsonResponse([
                'message' => 'Cette lecture a déjà été enregistrée.',
                'slug' => $import->getAccommodation()?->getSlug(),
            ], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->refuse(Response::HTTP_BAD_REQUEST, 'Requête illisible.');
        }

        $target = null;
        if (\is_string($payload['slug'] ?? null)) {
            $target = $this->accommodations->findOneBy(['slug' => $payload['slug']]);
            // Someone else's accommodation: 404, its slug must not leak (same as everywhere).
            if (null === $target || !$this->security->isGranted(AccommodationVoter::EDIT, $target)) {
                throw new NotFoundHttpException();
            }
        }

        $result = $this->applier->apply($import, $this->owner(), $target, $payload);

        if (\is_array($result)) {
            return new JsonResponse([
                'message' => 'Certaines lignes sont à corriger : rien n\'a été enregistré.',
                'violations' => $result,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['slug' => $result->getSlug(), 'created' => null === $target], Response::HTTP_CREATED);
    }

    private function ownedImport(string $id): ListingImport
    {
        $import = $this->imports->find(Uuid::fromString($id));

        // Someone else's import is a 404, not a 403: its existence is not confirmed.
        if (null === $import || !$import->getOwner()->getId()->equals($this->owner()->getId())) {
            throw new NotFoundHttpException();
        }

        return $import;
    }

    /**
     * The reading, made ready for the check screen. Null while pending or failed, or if a stored
     * result can no longer be read (older format): the screen then offers the plain form.
     *
     * @return array<string, mixed>|null
     */
    private function proposal(ListingImport $import): ?array
    {
        $result = $import->getResult();
        if (ListingImportStatus::Done !== $import->getStatus() || null === $result) {
            return null;
        }

        try {
            $extraction = ListingExtraction::fromArray($result);
        } catch (InvalidExtractionException) {
            return null;
        }

        $districts = [];
        foreach ($this->districts->findAll() as $district) {
            $districts[$district->getName()] = ['resort' => $district->getResort()->value, 'slug' => $district->getSlug()];
        }
        $contacts = array_values(array_filter(\is_array($result['contacts'] ?? null) ? $result['contacts'] : [], \is_string(...)));

        return ImportProposal::build($extraction, $contacts, $import->getSourceText(), $districts, $this->clock->now()->setTime(0, 0));
    }

    /**
     * @return array<string, mixed>
     */
    private function view(ListingImport $import): array
    {
        $failure = $import->getFailure();
        $message = match (true) {
            ListingImportStatus::Failed === $import->getStatus() && null !== $failure => $failure->messageForOwner(),
            $import->isPending() && null !== $failure => 'L\'assistant met plus de temps que prévu, nouvel essai en cours. Vous pouvez quitter cette page : votre texte est gardé.',
            $import->isPending() => 'Lecture de votre annonce en cours…',
            default => null,
        };

        return [
            'id' => $import->getId()->toRfc4122(),
            'status' => $import->getStatus()->value,
            'failure' => ListingImportStatus::Failed === $import->getStatus() ? $failure?->value : null,
            'message' => $message,
            'attempts' => $import->getAttempts(),
            'text' => $import->getSourceText(),
            'result' => $import->getResult(),
            'proposal' => $this->proposal($import),
            'appliedTo' => $import->getAccommodation()?->getSlug(),
            'createdAt' => $import->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array{available: bool, reason: string|null, until: string|null}
     */
    private function availabilityView(): array
    {
        $until = $this->availability->pausedUntil();

        return [
            'available' => $this->availability->isAvailable(),
            'reason' => match (true) {
                !$this->availability->isEnabled() => 'disabled',
                null !== $until => 'paused',
                default => null,
            },
            'until' => $until?->format(\DATE_ATOM),
        ];
    }

    private function owner(): User
    {
        $user = $this->security->getUser();
        \assert($user instanceof User, 'access_control requires a logged-in owner on /api/owner');

        return $user;
    }

    private function invalid(string $message): JsonResponse
    {
        return new JsonResponse(
            ['violations' => [['propertyPath' => 'text', 'message' => $message]]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    private function refuse(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
