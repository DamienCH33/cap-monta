<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Enum\AccommodationType;
use App\Service\Photo\InvalidPhotoException;
use App\Service\Photo\PhotoResizer;
use App\Service\Photo\PhotoStorage;
use App\Service\Photo\ResizedPhoto;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * Real photos for the development data set, read from a local folder (FIXTURE_PHOTOS_DIR).
 *
 * The photos are not in the repository: licences, credits and weight. Without the folder, the
 * fixtures load as before, without photos.
 *
 * A photo is sorted by its name or the name of its sub-folder:
 * "mobil" → mobile home, "bungalow" or "chalet" → bungalow, "carav" → caravan;
 * "plage", "dune", "ocean", "pin", "foret"… → landscape, added after the accommodation's own
 * photos. Anything else is a spare, used when a type has no photo of its own.
 * Each source is resized once, then its bytes are reused for every accommodation that shows it.
 */
final class FixturePhotoLibrary
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const LANDSCAPE = '/plage|beach|dune|oc[eé]an|mer\b|sea|pin|for[eê]t|forest|paysage|landscape|coucher|sunset/u';

    /** @var array<string, list<string>>|null paths by pool: mobile_home, bungalow, caravan, landscape, spare */
    private ?array $pools = null;

    /** @var array<string, ResizedPhoto|null> */
    private array $resized = [];

    public function __construct(
        private readonly PhotoResizer $resizer,
        private readonly PhotoStorage $storage,
        #[Autowire(env: 'default::FIXTURE_PHOTOS_DIR')]
        private readonly ?string $directory = null,
    ) {
    }

    /**
     * Three or four photos: the accommodation's type first (the cover), then a landscape.
     * The seed picks different photos from one accommodation to the next, the same on every load.
     */
    public function attachTo(Accommodation $accommodation, int $seed): void
    {
        $pools = $this->pools();
        $own = $pools[$accommodation->getType()->value] ?? [];

        if ([] === $own) {
            $own = [] !== $pools['spare'] ? $pools['spare'] : array_merge(...array_values($pools));
        }

        $chosen = array_merge(
            self::pick($own, $seed, min(3, count($own))),
            self::pick($pools['landscape'], $seed, 1),
        );

        foreach (array_unique($chosen) as $path) {
            $resized = $this->resize($path);

            if (null === $resized) {
                continue;
            }

            $photo = new Photo($accommodation, $resized->width, $resized->height);
            $accommodation->addPhoto($photo);
            $this->storage->save($photo, $resized);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function pools(): array
    {
        if (null !== $this->pools) {
            return $this->pools;
        }

        $pools = [
            AccommodationType::MobileHome->value => [],
            AccommodationType::Bungalow->value => [],
            AccommodationType::Caravan->value => [],
            'landscape' => [],
            'spare' => [],
        ];

        $directory = $this->expand($this->directory);

        if (null === $directory || !is_dir($directory)) {
            return $this->pools = $pools;
        }

        $files = (new Finder())->files()->in($directory)->name(array_map(static fn (string $ext): string => '/\.'.$ext.'$/i', self::EXTENSIONS))->sortByName();

        foreach ($files as $file) {
            $pools[self::poolOf(mb_strtolower($file->getRelativePathname()))][] = $file->getPathname();
        }

        return $this->pools = $pools;
    }

    private static function poolOf(string $name): string
    {
        return match (true) {
            str_contains($name, 'mobil') => AccommodationType::MobileHome->value,
            str_contains($name, 'bungalow'), str_contains($name, 'chalet') => AccommodationType::Bungalow->value,
            str_contains($name, 'carav') => AccommodationType::Caravan->value,
            1 === preg_match(self::LANDSCAPE, $name) => 'landscape',
            default => 'spare',
        };
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private static function pick(array $paths, int $seed, int $count): array
    {
        $picked = [];

        for ($i = 0; $i < $count && [] !== $paths; ++$i) {
            $picked[] = $paths[($seed + $i) % count($paths)];
        }

        return $picked;
    }

    private function resize(string $path): ?ResizedPhoto
    {
        if (!array_key_exists($path, $this->resized)) {
            try {
                $this->resized[$path] = $this->resizer->resize($path);
            } catch (InvalidPhotoException) {
                // An unreadable file in the folder: skipped, the others still load.
                $this->resized[$path] = null;
            }
        }

        return $this->resized[$path];
    }

    /** "~/Images/…" as written in .env.local: PHP does not expand the tilde. */
    private function expand(?string $directory): ?string
    {
        if (null === $directory || '' === trim($directory)) {
            return null;
        }

        $home = getenv('HOME');

        return str_starts_with($directory, '~/') && is_string($home) ? $home.substr($directory, 1) : $directory;
    }
}
