<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Photo;
use App\Service\Photo\PhotoStorage;

/**
 * A photo as the public sees it: two addresses and the size of the large version,
 * so the page can reserve the space before the image arrives. No id: nothing to
 * act on from the public site.
 */
final class PhotoResource
{
    public function __construct(
        public string $url,
        public string $thumbUrl,
        public int $width,
        public int $height,
    ) {
    }

    public static function fromEntity(Photo $photo, PhotoStorage $storage): self
    {
        return new self(
            $storage->url($photo),
            $storage->thumbUrl($photo),
            $photo->getWidth(),
            $photo->getHeight(),
        );
    }
}
