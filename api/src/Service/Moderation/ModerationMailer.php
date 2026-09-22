<?php

declare(strict_types=1);

namespace App\Service\Moderation;

use App\Entity\Accommodation;
use App\Entity\ListingReport;
use App\Enum\ReportReason;
use App\Service\Mail\MailComposer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final readonly class ModerationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private MailComposer $composer,
        #[Autowire('%app.moderation_email%')] private string $moderationEmail,
        #[Autowire('%app.front_url%')] private string $frontUrl,
    ) {
    }

    public function reportReceived(ListingReport $report): void
    {
        $accommodation = $report->getAccommodation();
        $urgent = ReportReason::PeopleVisible === $report->getReason() ? 'URGENT — ' : '';
        $message = null === $report->getMessage()
            ? ''
            : "\nMessage :\n« ".trim((string) preg_replace('/\R(?:\h*\R)+/u', "\n", $report->getMessage()))." »\n";
        $reporter = $report->getReporterEmail() ?? 'non communiquée';

        $this->mailer->send($this->composer->compose(
            $this->moderationEmail,
            $urgent.'Signalement : '.$accommodation->title().' — Cap Monta',
            <<<TXT
                Un visiteur signale une annonce.

                Raison : {$report->getReason()->label()}
                Annonce : {$accommodation->getSlug()}
                Adresse : {$reporter}
                {$message}
                {$this->frontUrl}/logement/{$accommodation->getSlug()}

                Pour retirer l'annonce du site et prévenir le propriétaire :
                php bin/console app:accommodation:suspend {$accommodation->getSlug()} --reason="…"
                TXT,
        ));
    }

    public function suspended(Accommodation $accommodation, string $reason): void
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        $this->mailer->send($this->composer->compose(
            $accommodation->getOwner()->getEmail(),
            'Votre annonce a été retirée du site — Cap Monta',
            <<<TXT
                Bonjour,

                Votre annonce {$accommodation->title()} a été retirée du site à la suite d'un
                signalement.

                Motif : {$reason}

                Elle n'est pas supprimée : corrigez-la puis republiez-la depuis votre espace, ou
                répondez à ce message si vous pensez qu'il s'agit d'une erreur.

                {$this->frontUrl}/mon-espace/logements

                Cap Monta
                TXT,
        ));
    }
}
