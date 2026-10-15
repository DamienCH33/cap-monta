<?php

declare(strict_types=1);

namespace App\Service\Moderation;

use App\Entity\Accommodation;
use App\Entity\ListingReport;
use App\Enum\ReportReason;
use App\I18n\Locales;
use App\I18n\Translator;
use App\Service\Mail\MailComposer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final readonly class ModerationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private MailComposer $composer,
        private Translator $translator,
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

    /**
     * To the editor, each time an accommodation goes online (first time or back from archive):
     * who published it, and enough of the listing to check it without opening the site.
     */
    public function published(Accommodation $accommodation): void
    {
        $owner = $accommodation->getOwner();
        $name = trim((string) preg_replace('/\s+/u', ' ', $owner->getDisplayName()));
        $description = trim((string) preg_replace('/\s+/u', ' ', $accommodation->getDescription()));
        $excerpt = mb_strlen($description) > 300 ? mb_substr($description, 0, 300).'…' : $description;
        $photos = count($accommodation->getPhotos());

        $this->mailer->send($this->composer->compose(
            $this->moderationEmail,
            'Nouvelle annonce en ligne : '.$accommodation->title().' — Cap Monta',
            <<<TXT
                Une annonce vient d'être mise en ligne.

                Logement : {$accommodation->title()}
                Propriétaire : {$name} ({$owner->getEmail()})
                Photos : {$photos}

                « {$excerpt} »

                {$this->frontUrl}/logement/{$accommodation->getSlug()}

                Si elle pose problème :
                php bin/console app:accommodation:suspend {$accommodation->getSlug()} --reason="…"
                TXT,
        ));
    }

    /**
     * To the owner, in their language (the motive stays as the editor typed it). The other two
     * emails go to the editor and stay in French.
     */
    public function suspended(Accommodation $accommodation, string $reason): void
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        $locale = $accommodation->getOwner()->getLocale();
        $site = $this->frontUrl.Locales::pathPrefix($locale);
        $removed = $this->t("Votre annonce %title% a été retirée du site à la suite d'un\nsignalement.", $locale, ['%title%' => $accommodation->title($locale)]);
        $next = $this->t("Elle n'est pas supprimée : corrigez-la puis republiez-la depuis votre espace, ou\nrépondez à ce message si vous pensez qu'il s'agit d'une erreur.", $locale);

        $this->mailer->send($this->composer->compose(
            $accommodation->getOwner()->getEmail(),
            $this->t('Votre annonce a été retirée du site — Cap Monta', $locale),
            <<<TXT
                {$this->t('Bonjour,', $locale)}

                {$removed}

                {$this->t('Motif : %reason%', $locale, ['%reason%' => $reason])}

                {$next}

                {$site}/mon-espace/logements

                Cap Monta
                TXT,
            $locale,
        ));
    }

    /**
     * Un texte du catalogue (config/i18n/emails.<langue>.php) dans la langue du destinataire.
     *
     * @param array<string, string> $parameters
     */
    private function t(string $text, string $locale, array $parameters = []): string
    {
        return $this->translator->trans($text, $parameters, $locale);
    }
}
