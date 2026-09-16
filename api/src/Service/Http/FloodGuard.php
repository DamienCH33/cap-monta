<?php

declare(strict_types=1);

namespace App\Service\Http;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Refuse les rafales, par adresse IP.
 *
 * Le limiteur est passé en argument : c'est l'appelant qui sait quelle règle
 * s'applique à son opération, ce service sait seulement comment la faire respecter.
 */
final readonly class FloodGuard
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function check(RateLimiterFactoryInterface $limiter): void
    {
        $key = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $limit = $limiter->create($key)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Trop de requêtes depuis cette adresse. Réessayez dans quelques minutes.');
        }
    }
}
