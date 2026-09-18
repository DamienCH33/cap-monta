<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ForgottenPasswordRequest;
use App\Repository\UserRepository;
use App\Service\Account\AccountMailer;
use App\Service\Http\FloodGuard;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ForgottenPasswordController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AccountMailer $mailer,
        private readonly FloodGuard $floodGuard,
        #[Target('password_resets')]
        private readonly RateLimiterFactoryInterface $passwordResetsLimiter,
    ) {
    }

    #[Route('/api/password/forgotten', name: 'api_password_forgotten', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ForgottenPasswordRequest $payload): JsonResponse
    {
        $this->floodGuard->check($this->passwordResetsLimiter);

        $email = mb_strtolower(trim($payload->email));
        $user = $this->users->findOneBy(['email' => $email]);

        // Même réponse dans les deux cas : le formulaire ne doit pas révéler
        // quelles adresses ont un compte. C'est la boîte mail qui informe.
        if (null === $user) {
            $this->mailer->sendNoAccountToReset($email);
        } else {
            $this->mailer->sendPasswordReset($user);
        }

        return new JsonResponse(['status' => 'check_your_inbox'], Response::HTTP_ACCEPTED);
    }
}
