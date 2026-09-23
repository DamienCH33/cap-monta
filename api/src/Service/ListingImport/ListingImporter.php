<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\ListingImportFailure;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;

/**
 * Turns a pasted listing into a pre-filled form. One call to the model, then the PHP applies
 * every rule it can check for sure (ADR 025):
 *
 * - the answer goes through ListingExtraction::fromArray(): shape, dates, closed lists;
 * - ExtractionReview proofreads it: dates of month labels, units and equipment not written in
 *   the text, duplicates;
 * - the questions come from the PHP only (ExtractionRules): overlapping periods, prices without
 *   dates or unit, no rate at all. The model's own questions were mostly noise (23/09);
 * - contact details in the text are found by ContactDetector, not by the model, and masked
 *   before the text leaves the server.
 *
 * Nothing is saved here: the owner reviews the result first.
 */
final readonly class ListingImporter
{
    /**
     * A model the free plan actually serves (admin.mistral.ai/plateforme/limits): on 23/09,
     * "mistral-small-*" were refused (429). Of the two served, the 14b read the 20 real listings
     * best (3/20 against 0/20 before the PHP proofreading). Declared in config/packages/ai.yaml,
     * symfony/ai 0.13 does not know it yet.
     */
    public const string DEFAULT_MODEL = 'ministral-14b-2512';
    private const int MAX_TEXT_LENGTH = 12000;

    public function __construct(
        #[Autowire(service: 'ai.platform.mistral')]
        private PlatformInterface $platform,
        private ContactDetector $contacts,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/config/prompts/listing-import.md')]
        private string $promptFile,
    ) {
    }

    /**
     * @param \DateTimeImmutable $publishedAt a month written without a year belongs to this date's year
     * @param list<string>       $districts   known district names, the only ones the model may pick
     */
    public function import(string $text, \DateTimeImmutable $publishedAt, array $districts, ?string $model = null): ListingImportResult
    {
        if (null === $model || '' === $model) {
            $model = self::DEFAULT_MODEL;
        }
        $text = mb_substr(trim($text), 0, self::MAX_TEXT_LENGTH);
        $contacts = $this->contacts->find($text);
        $started = hrtime(true);
        $raw = null;
        $usage = null;

        try {
            $result = $this->platform->invoke($model, $this->messages($this->contacts->mask($text), $publishedAt, $districts), [
                'response_format' => ListingImportSchema::responseFormat($districts),
            ])->getResult();

            $raw = $result->getContent();
            $usage = $result->getMetadata()->get('token_usage');
            $extraction = ListingExtraction::fromArray(ExtractionReview::repairDates($this->decode($raw), $publishedAt));
            $extraction = $extraction->withListing($extraction->listing?->keepingOnlyDistricts($districts));
            $extraction = ExtractionReview::apply($extraction, $text, $publishedAt);
        } catch (PlatformException|HttpException|InvalidExtractionException|\JsonException $e) {
            $failure = self::classify($e);
            $this->logger->log(
                ListingImportFailure::Configuration === $failure ? 'error' : 'warning',
                'Import d\'annonce en échec ({failure}) : {message}',
                ['failure' => $failure->value, 'message' => $e->getMessage(), 'model' => $model],
            );

            return new ListingImportResult(
                $model, null, $contacts, $this->elapsed($started),
                $usage instanceof TokenUsageInterface ? $usage->getPromptTokens() : null,
                $usage instanceof TokenUsageInterface ? $usage->getCompletionTokens() : null,
                $e->getMessage(), $raw, $failure,
                $e instanceof RateLimitExceededException ? $e->getRetryAfter() : null,
            );
        }

        return new ListingImportResult(
            $model,
            $extraction->withQuestions(ExtractionRules::questions($extraction)),
            $contacts,
            $this->elapsed($started),
            $usage instanceof TokenUsageInterface ? $usage->getPromptTokens() : null,
            $usage instanceof TokenUsageInterface ? $usage->getCompletionTokens() : null,
            rawOutput: $raw,
        );
    }

    /**
     * What a failure means for the next attempt (see ListingImportFailure).
     */
    public static function classify(\Throwable $e): ListingImportFailure
    {
        return match (true) {
            $e instanceof RateLimitExceededException => ListingImportFailure::ProviderLimit,
            $e instanceof AuthenticationException,
            $e instanceof ModelNotFoundException,
            $e instanceof MissingModelSupportException,
            $e instanceof BadRequestException => ListingImportFailure::Configuration,
            $e instanceof InvalidExtractionException,
            $e instanceof \JsonException,
            $e instanceof ExceedContextSizeException => ListingImportFailure::Unreadable,
            default => ListingImportFailure::Unavailable,
        };
    }

    /**
     * @param list<string> $districts
     */
    private function messages(string $text, \DateTimeImmutable $publishedAt, array $districts): MessageBag
    {
        $prompt = file_get_contents($this->promptFile);

        if (false === $prompt) {
            throw new \RuntimeException('Consigne introuvable : '.$this->promptFile);
        }

        return new MessageBag(
            Message::forSystem($prompt),
            Message::ofUser(\sprintf(
                "Date de publication de l'annonce : %s\nQuartiers connus : %s\n\nAnnonce :\n<<<\n%s\n>>>",
                $publishedAt->format('Y-m-d'),
                [] === $districts ? 'aucun' : implode(', ', $districts),
                $text,
            )),
        );
    }

    /**
     * Structured output comes back decoded, or as JSON text depending on the platform.
     *
     * @return array<mixed>
     *
     * @throws \JsonException|InvalidExtractionException
     */
    private function decode(mixed $raw): array
    {
        if (\is_string($raw)) {
            $raw = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($raw)) {
            throw new InvalidExtractionException('Réponse du modèle illisible.');
        }

        return $raw;
    }

    private function elapsed(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }
}
