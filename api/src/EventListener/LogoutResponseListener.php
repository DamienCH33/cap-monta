<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new JsonResponse(null, Response::HTTP_NO_CONTENT));
    }
}
