<?php

declare(strict_types=1);

namespace App\Service\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Refuse les rafales, par adresse IP.
 *
 * Le limiteur est passé en argument : c'est l'appelant qui sait quelle règle
 * s'applique à son opération, ce service sait seulement comment la faire respecter.
 *
 * Les compteurs vivent dans Redis. Si Redis ne répond pas, on laisse passer et on le note
 * dans les journaux : mieux vaut quelques minutes sans limiteur qu'un site entier en panne
 * (inscription, demandes de réservation, mot de passe oublié).
 */
final readonly class FloodGuard
{
    public function __construct(
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function check(RateLimiterFactoryInterface $limiter): void
    {
        $key = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';

        try {
            $limit = $limiter->create($key)->consume();
        } catch (\Throwable $e) {
            $this->logger->warning('Rate limiter unavailable, request let through: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return;
        }

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Trop de requêtes depuis cette adresse. Réessayez dans quelques minutes.');
        }
    }
}
