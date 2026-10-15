<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests only. Symfony's test client sends « Accept-Language: en-us,en;q=0.5 » on every request,
 * so the API would answer the whole suite in English. That synthetic header is dropped: a
 * request is French by default, like the site, and a test of another language sets the header
 * itself (« de », « en »…).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 200)]
final class SyntheticAcceptLanguageListener
{
    public function __invoke(RequestEvent $event): void
    {
        $headers = $event->getRequest()->headers;

        if ('en-us,en;q=0.5' === $headers->get('Accept-Language')) {
            $headers->remove('Accept-Language');
        }
    }
}
