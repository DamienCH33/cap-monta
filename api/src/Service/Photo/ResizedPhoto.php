<?php

declare(strict_types=1);

namespace App\Service\Photo;

/**
 * The two WebP versions of an uploaded photo, ready to be stored.
 */
final readonly class ResizedPhoto
{
    public function __construct(
        public string $large,
        public string $thumb,
        /** Size of the large version, in pixels. */
        public int $width,
        public int $height,
    ) {
    }
}
