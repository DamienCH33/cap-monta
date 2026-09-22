<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChangePasswordRequest;
use App\Dto\OwnerProfile;
use App\Dto\UpdateOwnerProfileRequest;
use App\Entity\User;
use App\Service\Http\FloodGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The connected owner: who he is (read on every page load, to know whether the session is
 * still open), his public name and phone, his password.
 */
final class OwnerProfileController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly FloodGuard $floodGuard,
        #[Target('password_resets')]
        private readonly RateLimiterFactoryInterface $passwordResetsLimiter,
    ) {
    }

    #[Route('/api/owner/me', name: 'api_owner_me', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(OwnerProfile::of($user));
    }

    #[Route('/api/owner/me', name: 'api_owner_me_update', methods: ['PATCH'])]
    public function update(#[CurrentUser] User $user, #[MapRequestPayload] UpdateOwnerProfileRequest $payload): JsonResponse
    {
        $user->setDisplayName(trim($payload->displayName));
        $phone = null === $payload->phone ? null : trim($payload->phone);
        $user->setPhone('' === $phone ? null : $phone);
        $this->em->flush();

        return new JsonResponse(OwnerProfile::of($user));
    }

    #[Route('/api/owner/me/password', name: 'api_owner_me_password', methods: ['POST'])]
    public function changePassword(#[CurrentUser] User $user, #[MapRequestPayload] ChangePasswordRequest $payload): JsonResponse
    {
        // Same ceiling as the forgotten password: a stolen session must not test passwords at will.
        $this->floodGuard->check($this->passwordResetsLimiter);

        if (!$this->hasher->isPasswordValid($user, $payload->currentPassword)) {
            $message = 'Mot de passe actuel incorrect.';

            return new JsonResponse([
                'title' => 'An error occurred',
                'detail' => $message,
                'status' => 422,
                'violations' => [['propertyPath' => 'currentPassword', 'message' => $message, 'title' => $message]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($this->hasher->hashPassword($user, $payload->newPassword));
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
