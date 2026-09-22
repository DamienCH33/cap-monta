<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ListingReport;
use App\Enum\AccommodationStatus;
use App\Repository\AccommodationRepository;
use App\Service\Moderation\ModerationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Takes a reported listing off the site at once, without deleting it, and tells the owner
 * why. The owner can fix it and publish it again. Every open report on it is marked handled.
 */
#[AsCommand(name: 'app:accommodation:suspend', description: 'Retire une annonce signalée et prévient son propriétaire')]
final readonly class SuspendAccommodationCommand
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private EntityManagerInterface $em,
        private ModerationMailer $mailer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse de l’annonce (slug)')] string $slug,
        #[Option(description: 'Motif envoyé au propriétaire')] string $reason = 'contenu non conforme aux conditions du site',
    ): int {
        $accommodation = $this->accommodations->findOneBy(['slug' => $slug]);

        if (null === $accommodation) {
            $io->error(sprintf('Aucune annonce « %s ».', $slug));

            return 1;
        }

        if (AccommodationStatus::Published !== $accommodation->getStatus()) {
            $io->warning('Cette annonce n’est pas en ligne : rien à retirer.');

            return 0;
        }

        $accommodation->archive();

        foreach ($this->em->getRepository(ListingReport::class)->findBy(['accommodation' => $accommodation, 'handledAt' => null]) as $report) {
            $report->markHandled($this->clock->now());
        }

        $this->em->flush();
        $this->mailer->suspended($accommodation, $reason);

        $io->success(sprintf('Annonce « %s » retirée, propriétaire prévenu.', $slug));

        return 0;
    }
}
