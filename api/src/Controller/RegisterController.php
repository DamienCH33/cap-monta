<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistrationRequest;
use App\Service\Account\Registrar;
use App\Service\Http\FloodGuard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly FloodGuard $floodGuard,
        private readonly RateLimiterFactoryInterface $registrationsLimiter,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegistrationRequest $payload): JsonResponse
    {
        $this->floodGuard->check($this->registrationsLimiter);
        $this->registrar->register($payload);

        return new JsonResponse(['status' => 'check_your_inbox'], Response::HTTP_ACCEPTED);
    }
}
