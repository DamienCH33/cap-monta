<?php

declare(strict_types=1);

namespace App\Service\Photo;

use App\Entity\Photo;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Photos on the local disk, served as static files by the web server.
 *
 * PHOTOS_DIR is relative to the project (public/media/photos in development,
 * var/test-media in tests); PHOTOS_BASE_URL is the matching public address.
 */
#[AsAlias(PhotoStorage::class)]
final readonly class LocalPhotoStorage implements PhotoStorage
{
    private const LARGE = '';
    private const THUMB = '-thumb';

    private string $directory;
    private string $baseUrl;
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        #[Autowire(env: 'PHOTOS_DIR')]
        string $directory,
        #[Autowire(env: 'PHOTOS_BASE_URL')]
        string $baseUrl,
    ) {
        $this->directory = $projectDir.'/'.trim($directory, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->filesystem = new Filesystem();
    }

    public function save(Photo $photo, ResizedPhoto $resized): void
    {
        $this->filesystem->dumpFile($this->path($photo, self::LARGE), $resized->large);
        $this->filesystem->dumpFile($this->path($photo, self::THUMB), $resized->thumb);
    }

    public function delete(Photo $photo): void
    {
        $this->filesystem->remove([$this->path($photo, self::LARGE), $this->path($photo, self::THUMB)]);
    }

    public function url(Photo $photo): string
    {
        return $this->baseUrl.'/'.self::name($photo, self::LARGE);
    }

    public function thumbUrl(Photo $photo): string
    {
        return $this->baseUrl.'/'.self::name($photo, self::THUMB);
    }

    private function path(Photo $photo, string $suffix): string
    {
        return $this->directory.'/'.self::name($photo, $suffix);
    }

    private static function name(Photo $photo, string $suffix): string
    {
        return $photo->getId()->toRfc4122().$suffix.'.webp';
    }
}
