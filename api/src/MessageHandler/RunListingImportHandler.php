<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\ListingImportFailure;
use App\Message\RunListingImport;
use App\Repository\DistrictRepository;
use App\Repository\ListingImportRepository;
use App\Service\ListingImport\AssistantAvailability;
use App\Service\ListingImport\ListingImporter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Reads one pasted listing, retries on its own schedule, and always ends in a clear state
 * (ADR 027):
 *
 * - success → done, the owner reviews the pre-filled form;
 * - provider down or rate-limited → up to 3 attempts (after 15 s, then 1 min, or what the
 *   provider asked), then failed and the assistant paused for everyone for a few minutes;
 * - unreadable answer → one more attempt, then failed;
 * - wrong configuration → failed at once, assistant paused, error logged.
 *
 * The handler never throws for a provider problem: Messenger's own retries (1 min to 4 h,
 * made for emails) would make the owner wait for nothing.
 */
#[AsMessageHandler]
final readonly class RunListingImportHandler
{
    public const int MAX_ATTEMPTS = 3;
    /** Delay before attempt 2, then 3, in milliseconds. */
    private const array RETRY_DELAYS_MS = [15_000, 60_000];
    private const int MAX_RETRY_AFTER_S = 120;

    public function __construct(
        private ListingImportRepository $imports,
        private DistrictRepository $districts,
        private ListingImporter $importer,
        private AssistantAvailability $availability,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RunListingImport $message): void
    {
        if (!Uuid::isValid($message->listingImportId)) {
            return;
        }

        $import = $this->imports->find(Uuid::fromString($message->listingImportId));
        if (null === $import || !$import->isPending()) {
            return;
        }

        $result = $this->importer->import($import->getSourceText(), $this->clock->now(), $this->districts->names());
        $import->recordAttempt($result->model, $result->inputTokens, $result->outputTokens);

        if (null !== $result->extraction) {
            $import->succeed([...$result->extraction->toArray(), 'contacts' => $result->contacts], $this->clock->now());
            $this->em->flush();

            return;
        }

        $failure = $result->failure ?? ListingImportFailure::Unavailable;
        $attempts = $import->getAttempts();
        $retry = $failure->isWorthRetrying()
            && $attempts < self::MAX_ATTEMPTS
            && (ListingImportFailure::Unreadable !== $failure || $attempts < 2);

        if (ListingImportFailure::ProviderLimit === $failure || ListingImportFailure::Configuration === $failure) {
            $this->availability->pauseAfter($failure);
        }

        if ($retry) {
            $import->noteFailure($failure, $result->error);
            $this->em->flush();

            $delay = null !== $result->retryAfter
                ? min($result->retryAfter, self::MAX_RETRY_AFTER_S) * 1000
                : self::RETRY_DELAYS_MS[$attempts - 1] ?? 60_000;
            $this->bus->dispatch(new RunListingImport($message->listingImportId), [new DelayStamp($delay)]);

            return;
        }

        $import->fail($failure, $result->error, $this->clock->now());
        $this->em->flush();

        if (ListingImportFailure::Unavailable === $failure) {
            $this->availability->pauseAfter($failure);
        }
    }
}
