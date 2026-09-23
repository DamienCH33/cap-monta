<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ListingImport;
use App\Entity\User;
use App\Enum\ListingImportStatus;
use App\Message\RunListingImport;
use App\Repository\ListingImportRepository;
use App\Service\ListingImport\AssistantAvailability;
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
        $import = $this->imports->find(Uuid::fromString($id));

        // Someone else's import is a 404, not a 403: its existence is not confirmed.
        if (null === $import || !$import->getOwner()->getId()->equals($this->owner()->getId())) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->view($import));
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
