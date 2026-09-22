<?php

declare(strict_types=1);

namespace App\Enum;

enum Resort: string
{
    case Chm = 'chm';
    case Euronat = 'euronat';

    public function label(): string
    {
        return match ($this) {
            self::Chm => 'CHM Montalivet',
            self::Euronat => 'Euronat',
        };
    }
}
