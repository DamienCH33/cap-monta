<?php

declare(strict_types=1);

namespace App\Enum;

enum ListingImportStatus: string
{
    /** Waiting for the worker, or between two attempts. */
    case Pending = 'pending';
    case Done = 'done';
    /** Given up: the owner fills the form himself, his text is still there. */
    case Failed = 'failed';
}
