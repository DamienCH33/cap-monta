<?php

declare(strict_types=1);

namespace App\Service\Photo;

use App\Entity\Photo;

/**
 * Where the photo files live. Local disk in development; an object storage (R2, S3) in
 * production, where the container disk is wiped on every deploy. Only the implementation
 * changes, never the code that uses it.
 */
interface PhotoStorage
{
    public function save(Photo $photo, ResizedPhoto $resized): void;

    public function delete(Photo $photo): void;

    /** The stored files, to transform them (a rotation) into a new photo. */
    public function read(Photo $photo): ResizedPhoto;

    /** Public address of the large version, for the accommodation page. */
    public function url(Photo $photo): string;

    /** Public address of the thumbnail, for the search cards and the owner's form. */
    public function thumbUrl(Photo $photo): string;
}
