<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\ListingImportFailure;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Whether the reading assistant can be offered right now. Two cases where it cannot:
 *
 * - it is switched off: no provider key configured;
 * - it is paused: the provider just failed in a way that will not fix itself in seconds (limit
 *   of the free plan reached, wrong key, repeated outage). For a few minutes nobody is offered
 *   a button that would only make them wait for an error; the form works as usual.
 *
 * The pause lives in PostgreSQL (pool assistant.cache), not in Redis nor in memory: the web
 * server and the worker must see the same one.
 */
final readonly class AssistantAvailability
{
    private const string KEY = 'listing_import.paused_until';

    public function __construct(
        #[Autowire(service: 'assistant.cache')]
        private CacheItemPoolInterface $cache,
        private ClockInterface $clock,
        #[Autowire(env: 'MISTRAL_API_KEY')]
        private string $apiKey,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== trim($this->apiKey);
    }

    public function pausedUntil(): ?\DateTimeImmutable
    {
        $until = $this->cache->getItem(self::KEY)->get();

        return $until instanceof \DateTimeImmutable && $until > $this->clock->now() ? $until : null;
    }

    public function isAvailable(): bool
    {
        return $this->isEnabled() && null === $this->pausedUntil();
    }

    /** Only ever lengthens a pause, never shortens one already running. */
    public function pauseAfter(ListingImportFailure $failure): void
    {
        if ($failure->pauseSeconds() <= 0) {
            return;
        }

        $until = $this->clock->now()->modify(\sprintf('+%d seconds', $failure->pauseSeconds()));
        $current = $this->pausedUntil();
        if (null !== $current && $current >= $until) {
            return;
        }

        $item = $this->cache->getItem(self::KEY);
        $item->set($until);
        $item->expiresAt($until);
        $this->cache->save($item);
    }

    public function resume(): void
    {
        $this->cache->deleteItem(self::KEY);
    }
}
