<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\OwnerProfile;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Who am I? The front calls this right after logging in, and on every page
 * refresh, to know whether the session is still open.
 */
final class OwnerProfileController
{
    #[Route('/api/owner/me', name: 'api_owner_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(OwnerProfile::of($user));
    }
}
