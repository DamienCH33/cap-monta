<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Photo;
use App\Repository\AccommodationRepository;
use App\Service\Photo\PhotoResizer;
use App\Service\Photo\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Redresse des photos déjà en ligne, sans rien demander au propriétaire : celles envoyées
 * quand l'orientation du téléphone était ignorée (extension exif absente en production).
 *
 * Sans numéro, liste les photos de l'annonce dans l'ordre de la fiche (1 = couverture).
 * La photo tournée reçoit une nouvelle adresse : les fichiers étant mis en cache pour 30 jours
 * (« immutable »), la même adresse resterait couchée chez les visiteurs déjà passés.
 */
#[AsCommand(name: 'app:photo:rotate', description: 'Tourne des photos d’une annonce (1 = couverture)')]
final readonly class RotatePhotoCommand
{
    private const TURNS = ['droite' => 90, 'demi' => 180, 'gauche' => 270];

    public function __construct(
        private AccommodationRepository $accommodations,
        private PhotoResizer $resizer,
        private PhotoStorage $storage,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<string> $numbers
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse de l’annonce (slug)')] string $slug,
        #[Argument(description: 'Numéros des photos à tourner, dans l’ordre de la fiche')] array $numbers = [],
        #[Option(description: 'droite (quart de tour horaire), gauche ou demi')] string $sens = 'droite',
    ): int {
        $accommodation = $this->accommodations->findOneBy(['slug' => $slug]);

        if (null === $accommodation) {
            $io->error(\sprintf('Aucune annonce « %s ».', $slug));

            return 1;
        }

        $photos = $accommodation->getPhotos();

        if ([] === $numbers) {
            $io->table(['N°', 'Format', 'Adresse'], array_map(
                fn (Photo $photo, int $index): array => [
                    $index + 1,
                    \sprintf('%d × %d %s', $photo->getWidth(), $photo->getHeight(), $photo->getWidth() >= $photo->getHeight() ? 'paysage' : 'portrait'),
                    $this->storage->url($photo),
                ],
                $photos,
                array_keys($photos),
            ));
            $io->text(\sprintf('Pour tourner : app:photo:rotate %s 2 5 --sens=droite', $slug));

            return 0;
        }

        $clockwise = self::TURNS[$sens] ?? null;

        if (null === $clockwise) {
            $io->error('Sens inconnu : droite, gauche ou demi.');

            return 1;
        }

        $selected = [];
        foreach ($numbers as $number) {
            $photo = ctype_digit($number) ? ($photos[(int) $number - 1] ?? null) : null;

            if (null === $photo) {
                $io->error(\sprintf('Pas de photo n° %s : l’annonce en a %d.', $number, \count($photos)));

                return 1;
            }
            $selected[(int) $number] = $photo;
        }

        $written = [];
        foreach ($selected as $number => $old) {
            $turned = $this->resizer->rotate($this->storage->read($old), $clockwise);
            $new = new Photo($accommodation, $turned->width, $turned->height);
            $accommodation->replacePhoto($old, $new);
            $this->storage->save($new, $turned);
            $written[$number] = [$old, $new];
        }

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            foreach ($written as [, $new]) {
                $this->storage->delete($new);
            }

            throw $e;
        }

        // Anciens fichiers effacés seulement une fois la base à jour.
        foreach ($written as $number => [$old, $new]) {
            $this->storage->delete($old);
            $io->text(\sprintf('Photo %d tournée : %s', $number, $this->storage->url($new)));
        }

        $io->success(\sprintf('%d photo(s) redressée(s) sur « %s ».', \count($written), $slug));

        return 0;
    }
}
