<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * The login throttling of the "main" firewall, kept from taking the login down with Redis
 * (ADR 014): when the counters cannot be read, the attempt goes through and is logged.
 */
#[AsDecorator('security.login_throttling.main.limiter')]
final readonly class ResilientLoginRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        #[AutowireDecorated] private RequestRateLimiterInterface $inner,
        private LoggerInterface $logger,
    ) {
    }

    public function consume(Request $request): RateLimit
    {
        try {
            return $this->inner->consume($request);
        } catch (\Throwable $e) {
            $this->logger->warning('Login throttling unavailable, attempt let through: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new RateLimit(1, new \DateTimeImmutable(), true, 1);
        }
    }

    public function reset(Request $request): void
    {
        try {
            $this->inner->reset($request);
        } catch (\Throwable $e) {
            $this->logger->warning('Login throttling reset failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }
}
