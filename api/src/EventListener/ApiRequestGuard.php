<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Two cheap guards on the owner's writes, and safe headers on every API answer.
 *
 * The session cookie is SameSite=Lax, which already stops most cross-site requests. Two holes
 * remain: a form posted with enctype="text/plain" can carry valid JSON, and a page on a sibling
 * sub-domain counts as « same site ». So a write that changes something must say it sends JSON
 * (or a photo), and must not come from a foreign origin.
 */
final readonly class ApiRequestGuard
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        #[Autowire(env: 'CORS_ALLOW_ORIGIN')]
        private string $allowedOrigins,
    ) {
    }

    // After the firewall (priority 8): an anonymous visitor gets his 401 first.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!$event->isMainRequest() || \in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        if (!str_starts_with($path, '/api/owner') && !\in_array($path, ['/api/login', '/api/logout'], true)) {
            return;
        }

        $origin = $request->headers->get('Origin');

        if (null !== $origin && $origin !== $request->getSchemeAndHttpHost() && 1 !== preg_match('#'.$this->allowedOrigins.'#', $origin)) {
            $event->setResponse(self::problem(403, 'Requête refusée : elle ne vient pas du site.'));

            return;
        }

        // A photo arrives as multipart/form-data: PHP has already read it into the files.
        if ($request->files->count() > 0) {
            return;
        }

        $type = (string) $request->headers->get('Content-Type');

        if ('' !== (string) $request->getContent() && !str_contains($type, 'json')) {
            $event->setResponse(self::problem(415, 'Envoyez les données en JSON.'));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $headers = $event->getResponse()->headers;

        if (!$headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }

        if ($event->getRequest()->isSecure() && !$headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    private static function problem(int $status, string $detail): JsonResponse
    {
        return new JsonResponse(
            ['title' => 'Requête refusée', 'status' => $status, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
