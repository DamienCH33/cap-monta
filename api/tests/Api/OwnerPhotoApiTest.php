<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Controller\OwnerPhotoController;
use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class OwnerPhotoApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $storage;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        (new ORMPurger($this->em))->purge();

        // Same folder as PHOTOS_DIR in .env.test: emptied before every test.
        $this->storage = self::$kernel?->getProjectDir().'/var/test-media';
        (new Filesystem())->remove($this->storage);
    }

    public function testAnAnonymousVisitorCannotUpload(): void
    {
        $this->upload('anything', $this->jpeg(800, 600));

        self::assertResponseStatusCodeSame(401);
    }

    public function testAPhotoIsResizedStoredAndListed(): void
    {
        $alice = $this->loggedInOwner();
        $this->accommodation('alice-home', $alice);

        $this->upload('alice-home', $this->jpeg(3000, 2000));

        self::assertResponseStatusCodeSame(201);
        $photo = $this->json();
        self::assertSame(1600, $photo['width'], 'The large version is 1600 px wide.');
        self::assertSame(1066, $photo['height'], 'The proportions are kept (GD rounds down).');
        self::assertSame(0, $photo['position'], 'The first photo is the cover.');
        self::assertStringEndsWith('.webp', (string) $photo['url']);
        self::assertStringEndsWith('-thumb.webp', (string) $photo['thumbUrl']);
        self::assertFileExists($this->storage.'/'.$photo['id'].'.webp');
        self::assertFileExists($this->storage.'/'.$photo['id'].'-thumb.webp');

        $this->client->request('GET', '/api/owner/accommodations/alice-home');
        self::assertSame([$photo['id']], array_column($this->json()['photos'], 'id'));
    }

    public function testASmallPictureIsNeverEnlarged(): void
    {
        $this->accommodation('alice-home', $this->loggedInOwner());

        $this->upload('alice-home', $this->jpeg(800, 600));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(800, $this->json()['width']);
    }

    public function testTheNoPeopleConfirmationIsRequired(): void
    {
        $this->accommodation('alice-home', $this->loggedInOwner());

        $this->upload('alice-home', $this->jpeg(800, 600), noPeople: false);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('noPeople', $this->json()['violations'][0]['propertyPath']);
    }

    public function testAFileThatIsNotAPictureIsRefused(): void
    {
        $this->accommodation('alice-home', $this->loggedInOwner());
        $path = tempnam(sys_get_temp_dir(), 'cm');
        file_put_contents((string) $path, 'Not a picture at all.');

        $this->upload('alice-home', new UploadedFile((string) $path, 'notes.jpg', 'image/jpeg', null, true));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('photo', $this->json()['violations'][0]['propertyPath']);
    }

    public function testSomeoneElsesAccommodationIsNotFound(): void
    {
        $bob = $this->owner('bob@example.com');
        $this->accommodation('bob-home', $bob);
        $this->loggedInOwner();

        $this->upload('bob-home', $this->jpeg(800, 600));

        self::assertResponseStatusCodeSame(404);
    }

    public function testThereIsAMaximumNumberOfPhotos(): void
    {
        $home = $this->accommodation('alice-home', $this->loggedInOwner());

        for ($i = 0; $i < OwnerPhotoController::MAX_PHOTOS; ++$i) {
            $home->addPhoto(new Photo($home, 800, 600));
        }
        $this->em->flush();

        $this->upload('alice-home', $this->jpeg(800, 600));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('12 photos maximum', (string) $this->json()['detail']);
    }

    public function testDeletingAPhotoRemovesItsFilesAndRenumbersTheOthers(): void
    {
        $this->accommodation('alice-home', $this->loggedInOwner());
        $ids = $this->uploadMany('alice-home', 3);

        $this->client->request('DELETE', '/api/owner/accommodations/alice-home/photos/'.$ids[0]);

        self::assertResponseStatusCodeSame(204);
        self::assertFileDoesNotExist($this->storage.'/'.$ids[0].'.webp');
        self::assertFileDoesNotExist($this->storage.'/'.$ids[0].'-thumb.webp');

        $this->client->request('GET', '/api/owner/accommodations/alice-home');
        self::assertSame([$ids[1], $ids[2]], array_column($this->json()['photos'], 'id'), 'The second photo becomes the cover.');
    }

    public function testThePhotosCanBeReordered(): void
    {
        $this->accommodation('alice-home', $this->loggedInOwner());
        $ids = $this->uploadMany('alice-home', 3);

        $this->reorder('alice-home', [$ids[2], $ids[0], $ids[1]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$ids[2], $ids[0], $ids[1]], array_column($this->json(), 'id'));

        $this->client->request('GET', '/api/owner/accommodations/alice-home');
        self::assertSame([$ids[2], $ids[0], $ids[1]], array_column($this->json()['photos'], 'id'));
    }

    public function testAnIncompleteOrderIsRefused(): void
    {
        $this->accommodation('alice-home', $this->loggedInOwner());
        $ids = $this->uploadMany('alice-home', 2);

        $this->reorder('alice-home', [$ids[0]]);

        self::assertResponseStatusCodeSame(422);
    }

    private function loggedInOwner(): User
    {
        $alice = $this->owner('alice@example.com');
        $this->em->flush();
        $this->client->loginUser($alice, 'main');

        return $alice;
    }

    private function owner(string $email): User
    {
        $owner = new User($email, 'Owner test');
        $owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($owner);

        return $owner;
    }

    private function accommodation(string $slug, User $owner): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation', $owner);
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    /**
     * A real JPEG, drawn with GD: the upload goes through the whole resizing path.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    private function jpeg(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 11, 95, 138));

        $path = (string) tempnam(sys_get_temp_dir(), 'cm');
        imagejpeg($image, $path, 70);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }

    private function upload(string $slug, UploadedFile $file, bool $noPeople = true): void
    {
        $this->client->request(
            'POST',
            '/api/owner/accommodations/'.$slug.'/photos',
            $noPeople ? ['noPeople' => '1'] : [],
            ['photo' => $file],
        );
    }

    /**
     * @return list<string> the ids, in upload order
     */
    private function uploadMany(string $slug, int $count): array
    {
        $ids = [];

        for ($i = 0; $i < $count; ++$i) {
            $this->upload($slug, $this->jpeg(800, 600));
            self::assertResponseStatusCodeSame(201);
            $ids[] = (string) $this->json()['id'];
        }

        return $ids;
    }

    /**
     * @param list<string> $ids
     */
    private function reorder(string $slug, array $ids): void
    {
        $this->client->request(
            'PUT',
            '/api/owner/accommodations/'.$slug.'/photos/order',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['ids' => $ids], \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
