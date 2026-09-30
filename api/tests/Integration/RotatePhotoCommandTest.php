<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Factory\AccommodationFactory;
use App\Service\Photo\PhotoResizer;
use App\Service\Photo\PhotoStorage;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class RotatePhotoCommandTest extends DatabaseTestCase
{
    private string $media;

    protected function setUp(): void
    {
        self::bootKernel();
        // Same folder as PHOTOS_DIR in .env.test.
        $this->media = self::$kernel?->getProjectDir().'/var/test-media';
        new Filesystem()->remove($this->media);
    }

    public function testTheChosenPhotosAreTurnedInPlaceUnderANewAddress(): void
    {
        $accommodation = AccommodationFactory::createOne(['slug' => 'polynesie']);
        [$cover, $sideways, $third] = [$this->photo($accommodation), $this->photo($accommodation), $this->photo($accommodation)];
        $oldFile = $this->media.'/'.$sideways->getId()->toRfc4122().'.webp';
        self::assertFileExists($oldFile);

        $tester = $this->command();
        $tester->execute(['slug' => 'polynesie', 'numbers' => ['2'], '--sens' => 'droite']);
        $tester->assertCommandIsSuccessful();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $photos = $em->find(Accommodation::class, $accommodation->getId())?->getPhotos() ?? [];

        self::assertCount(3, $photos);
        // Same order; only the second one changed, and it is now portrait.
        self::assertTrue($cover->getId()->equals($photos[0]->getId()));
        self::assertFalse($sideways->getId()->equals($photos[1]->getId()));
        self::assertTrue($third->getId()->equals($photos[2]->getId()));
        self::assertSame([20, 40], [$photos[1]->getWidth(), $photos[1]->getHeight()]);

        // Old file gone (it was cached as immutable), new files in place.
        self::assertFileDoesNotExist($oldFile);
        self::assertFileExists($this->media.'/'.$photos[1]->getId()->toRfc4122().'.webp');
        self::assertFileExists($this->media.'/'.$photos[1]->getId()->toRfc4122().'-thumb.webp');
    }

    public function testWithoutNumbersItOnlyListsThePhotos(): void
    {
        $accommodation = AccommodationFactory::createOne(['slug' => 'polynesie']);
        $this->photo($accommodation);

        $tester = $this->command();
        $tester->execute(['slug' => 'polynesie']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('40 × 20 paysage', $tester->getDisplay());
    }

    public function testAnUnknownNumberChangesNothing(): void
    {
        $accommodation = AccommodationFactory::createOne(['slug' => 'polynesie']);
        $photo = $this->photo($accommodation);

        $tester = $this->command();
        $tester->execute(['slug' => 'polynesie', 'numbers' => ['1', '4']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertFileExists($this->media.'/'.$photo->getId()->toRfc4122().'.webp');
    }

    private function command(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:photo:rotate'));
    }

    /** A 40 × 20 landscape photo, stored like an upload. */
    private function photo(Accommodation $accommodation): Photo
    {
        $image = imagecreatetruecolor(40, 20);
        self::assertNotFalse($image);
        $path = (string) tempnam(sys_get_temp_dir(), 'cm');
        imagejpeg($image, $path);

        $resized = self::getContainer()->get(PhotoResizer::class)->resize($path);
        $photo = new Photo($accommodation, $resized->width, $resized->height);
        $accommodation->addPhoto($photo);
        self::getContainer()->get(PhotoStorage::class)->save($photo, $resized);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        return $photo;
    }
}
