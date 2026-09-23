<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ListingImport\AssistantAvailability;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/health: what the host's health check and a monitoring service call every minute.
 *
 * 503 only when the database is down: without it nothing works, the host must restart us.
 * Redis down, a late worker or a paused reading assistant give 200 "degraded": the site works,
 * someone should look. An assistant switched off on purpose (no key) is "off", not degraded.
 * Anonymous callers get the overall state only; the detail per component (database, cache,
 * worker) needs the X-Health-Token header, equal to HEALTH_TOKEN (empty: never shown).
 */
final readonly class HealthController
{
    /** A delayed message waiting longer than this means the worker is not running. */
    private const WORKER_LATE_AFTER = '-15 minutes';

    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'app.redis')]
        private \Redis $redis,
        private ClockInterface $clock,
        private AssistantAvailability $assistant,
        #[Autowire(env: 'HEALTH_TOKEN')]
        private string $token,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $database = $this->database();
        $checks = [
            'database' => $database ? 'ok' : 'down',
            'cache' => $this->cache() ? 'ok' : 'down',
            'worker' => $database ? ($this->workerLate() ? 'late' : 'ok') : 'unknown',
            'assistant' => $database ? $this->assistant() : 'unknown',
        ];

        $healthy = array_diff(array_values($checks), ['ok', 'off']);
        $status = !$database ? 'down' : ([] === $healthy ? 'ok' : 'degraded');

        $given = (string) $request->headers->get('X-Health-Token', '');
        $body = '' !== $this->token && hash_equals($this->token, $given)
            ? ['status' => $status, 'checks' => $checks]
            : ['status' => $status];

        $response = new JsonResponse($body, $database ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function assistant(): string
    {
        return match (true) {
            !$this->assistant->isEnabled() => 'off',
            null !== $this->assistant->pausedUntil() => 'paused',
            default => 'ok',
        };
    }

    private function database(): bool
    {
        try {
            return 1 === (int) $this->connection->fetchOne('SELECT 1');
        } catch (\Throwable) {
            return false;
        }
    }

    private function cache(): bool
    {
        try {
            // The cache adapter hides a Redis failure (it logs and recomputes): ask Redis itself.
            return false !== $this->redis->ping();
        } catch (\Throwable) {
            return false;
        }
    }

    private function workerLate(): bool
    {
        try {
            $late = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue AND delivered_at IS NULL AND available_at < :limit',
                ['queue' => 'default', 'limit' => $this->clock->now()->modify(self::WORKER_LATE_AFTER)->format('Y-m-d H:i:s')],
            );

            return (int) $late > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
