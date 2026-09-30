<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Photo;

use App\Service\Photo\PhotoResizer;
use PHPUnit\Framework\TestCase;

final class PhotoResizerTest extends TestCase
{
    public function testTheExifExtensionIsLoaded(): void
    {
        // Sans elle, l'orientation est ignorée en silence : les photos de téléphone
        // arrivent couchées (vu en production, où l'image Docker ne l'avait pas).
        self::assertTrue(\function_exists('exif_read_data'), 'Extension PHP exif manquante.');
    }

    public function testAPhonePictureTakenUprightIsStoredUpright(): void
    {
        // 40 × 20 px, moitié gauche noire, avec « Orientation = 6 » : à tourner de 90° dans le sens horaire.
        $photo = (new PhotoResizer())->resize(__DIR__.'/fixtures/sideways.jpg');

        self::assertSame(20, $photo->width);
        self::assertSame(40, $photo->height);

        $image = imagecreatefromstring($photo->large);
        self::assertNotFalse($image);

        // Après rotation horaire, la moitié gauche noire se retrouve en haut.
        self::assertLessThan(60, $this->brightness($image, 10, 5));
        self::assertGreaterThan(200, $this->brightness($image, 10, 35));
    }

    private function brightness(\GdImage $image, int $x, int $y): int
    {
        $color = imagecolorat($image, $x, $y);
        self::assertNotFalse($color);
        $rgb = imagecolorsforindex($image, $color);

        return intdiv($rgb['red'] + $rgb['green'] + $rgb['blue'], 3);
    }
}
