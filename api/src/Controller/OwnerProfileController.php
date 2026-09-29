<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChangePasswordRequest;
use App\Dto\DeleteAccountRequest;
use App\Dto\OwnerProfile;
use App\Dto\UpdateOwnerProfileRequest;
use App\Entity\User;
use App\Service\Account\AccountDeleter;
use App\Service\Account\AccountDeletionRefused;
use App\Service\Account\AccountMailer;
use App\Service\Http\FloodGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
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
        private readonly AccountDeleter $deleter,
        private readonly TokenStorageInterface $tokens,
        private readonly AccountMailer $accountMailer,
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

    /**
     * A new confirmation link, for an owner whose first email never arrived (spam folder,
     * mail outage). Nothing is sent once the address is confirmed; same ceiling as the
     * forgotten password, so the button cannot be used to flood a mailbox.
     */
    #[Route('/api/owner/me/verification', name: 'api_owner_me_verification', methods: ['POST'])]
    public function resendVerification(#[CurrentUser] User $user): JsonResponse
    {
        $this->floodGuard->check($this->passwordResetsLimiter);

        if (!$user->isVerified()) {
            $this->accountMailer->sendVerificationLink($user);
        }

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
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

    /**
     * Closes the account for good (GDPR, right to erasure). Password asked again; refused while
     * accepted stays are still to come, so no guest arrives at a listing that no longer exists.
     */
    #[Route('/api/owner/me', name: 'api_owner_me_delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] User $user, #[MapRequestPayload] DeleteAccountRequest $payload, Request $request): JsonResponse
    {
        $this->floodGuard->check($this->passwordResetsLimiter);

        if (!$this->hasher->isPasswordValid($user, $payload->password)) {
            $message = 'Mot de passe incorrect.';

            return new JsonResponse([
                'title' => 'An error occurred',
                'detail' => $message,
                'status' => 422,
                'violations' => [['propertyPath' => 'password', 'message' => $message, 'title' => $message]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->deleter->delete($user);
        } catch (AccountDeletionRefused $refused) {
            return new JsonResponse(
                ['title' => 'Conflit', 'status' => 409, 'detail' => $refused->getMessage()],
                Response::HTTP_CONFLICT,
                ['Content-Type' => 'application/problem+json'],
            );
        }

        $this->tokens->setToken(null);
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
