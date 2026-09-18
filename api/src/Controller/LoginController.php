<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\OwnerProfile;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The firewall authenticates before this runs; reaching it means the
 * credentials were valid. Wrong credentials never get here: they are
 * turned into a 401 by the authenticator.
 */
final class LoginController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(OwnerProfile::of($user));
    }
}
