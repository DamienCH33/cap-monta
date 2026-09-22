<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whether the owner takes animals. "Not allowed" is a hard rule (a request with an animal is
 * refused before it reaches him); "on request" lets the request through for him to decide.
 */
enum PetsPolicy: string
{
    case Allowed = 'allowed';
    case OnRequest = 'on_request';
    case NotAllowed = 'not_allowed';

    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Animaux acceptés',
            self::OnRequest => 'Animaux sur demande',
            self::NotAllowed => 'Animaux non acceptés',
        };
    }
}
