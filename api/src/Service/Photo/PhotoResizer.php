<?php

declare(strict_types=1);

namespace App\Service\Photo;

/**
 * Turns an uploaded picture into two WebP files: a large one for the accommodation page
 * and a thumbnail for the search cards. A 5 MB phone photo becomes about 250 kB.
 *
 * Phone pictures are often stored sideways with an "orientation" flag: it is applied
 * here, so that nobody sees their mobile home lying on its side.
 */
final class PhotoResizer
{
    public const LARGE_WIDTH = 1600;
    public const THUMB_WIDTH = 600;
    private const QUALITY = 80;

    public function resize(string $path): ResizedPhoto
    {
        // A 50 Mpx photo takes about 200 MB once decoded, plus the two resized copies.
        if (self::bytes((string) ini_get('memory_limit')) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }

        // GD images are freed automatically since PHP 8: no imagedestroy() needed.
        $source = $this->open($path);

        [$large, $width, $height] = $this->encode($source, self::LARGE_WIDTH);
        [$thumb] = $this->encode($source, self::THUMB_WIDTH);

        return new ResizedPhoto($large, $thumb, $width, $height);
    }

    private function open(string $path): \GdImage
    {
        $info = @getimagesize($path);

        if (false === $info) {
            throw new InvalidPhotoException("Ce fichier n'est pas une photo lisible.");
        }

        $image = match ($info[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => @imagecreatefrompng($path),
            \IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (false === $image) {
            throw new InvalidPhotoException('Choisissez une photo au format JPEG, PNG ou WebP.');
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return \IMAGETYPE_JPEG === $info[2] ? $this->applyOrientation($image, $path) : $image;
    }

    /**
     * @return array{string, int, int} the WebP bytes, then the width and height
     */
    private function encode(\GdImage $source, int $maxWidth): array
    {
        // Never enlarge a small picture: it would only get blurrier and heavier.
        $image = imagesx($source) > $maxWidth ? imagescale($source, $maxWidth, -1, \IMG_BICUBIC) : $source;

        if (false === $image) {
            throw new InvalidPhotoException("Cette photo n'a pas pu être redimensionnée. Essayez-en une autre.");
        }

        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        return [$bytes, imagesx($image), imagesy($image)];
    }

    private function applyOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!\function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = \is_array($exif) && isset($exif['Orientation']) && \is_int($exif['Orientation'])
            ? $exif['Orientation']
            : 1;

        // Angles are counter-clockwise for imagerotate().
        $angle = match ($orientation) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        if (0 === $angle) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return false === $rotated ? $image : $rotated;
    }

    /** "256M" → 268435456; "-1" (no limit) → PHP_INT_MAX. */
    private static function bytes(string $value): int
    {
        if ('-1' === $value) {
            return \PHP_INT_MAX;
        }

        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
