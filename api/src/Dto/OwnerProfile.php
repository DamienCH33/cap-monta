<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;

/**
 * The connected owner, as the front sees them.
 * Shared by /api/login and /api/owner/me so the two can never drift apart.
 */
final class OwnerProfile
{
    /**
     * @return array{email: string, displayName: string, phone: string|null, verified: bool}
     */
    public static function of(User $user): array
    {
        return [
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'phone' => $user->getPhone(),
            'verified' => $user->isVerified(),
        ];
    }
}
