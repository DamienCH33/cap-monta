<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
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
 * - overlapping periods, prices without dates or unit, no rate at all → a question is added,
 *   whether or not the model thought of it;
 * - contact details in the text are found by ContactDetector, not by the model.
 *
 * Nothing is saved here: the owner reviews the result first.
 */
final readonly class ListingImporter
{
    public const string DEFAULT_MODEL = 'gpt-5.6-luna';
    private const int MAX_TEXT_LENGTH = 12000;

    public function __construct(
        #[Autowire(service: 'ai.platform.openai')]
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
            $result = $this->platform->invoke($model, $this->messages($text, $publishedAt, $districts), [
                'response_format' => ListingImportSchema::responseFormat($districts),
            ])->getResult();

            $raw = $result->getContent();
            $usage = $result->getMetadata()->get('token_usage');
            $extraction = ListingExtraction::fromArray($this->decode($raw));
            $extraction = $extraction->withListing($extraction->listing?->keepingOnlyDistricts($districts));
        } catch (PlatformException|HttpException|InvalidExtractionException|\JsonException $e) {
            $this->logger->warning('Import d\'annonce en échec : {message}', ['message' => $e->getMessage(), 'model' => $model]);

            return new ListingImportResult(
                $model, null, $contacts, $this->elapsed($started),
                $usage instanceof TokenUsageInterface ? $usage->getPromptTokens() : null,
                $usage instanceof TokenUsageInterface ? $usage->getCompletionTokens() : null,
                $e->getMessage(), $raw,
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
