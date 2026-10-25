<?php

declare(strict_types=1);

namespace App\Service\StayAlert;

use App\Entity\Accommodation;
use App\Entity\StayAlert;
use App\I18n\Locales;
use App\I18n\Translator;
use App\Service\Mail\MailComposer;
use App\Service\Mail\MailFormat;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The emails of « Prévenez-moi », in the language of the page where the alert was left.
 * Plain text; MailComposer adds the HTML version.
 */
final readonly class StayAlertMailer
{
    /** Homes listed in one email; the search link shows the rest. */
    public const int MAX_LISTED = 5;

    public function __construct(
        private MailerInterface $mailer,
        private MailComposer $composer,
        private Translator $translator,
        private StayAlertLinks $links,
        #[Autowire('%app.front_url%')] private string $frontUrl,
    ) {
    }

    public function confirmation(StayAlert $alert): void
    {
        $locale = $alert->getLocale();
        $ignore = $this->t("Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nsans activation, l'alerte et votre adresse sont effacées sous deux jours.", $locale);

        $this->send(
            $alert,
            $this->t('Activez votre alerte', $locale),
            <<<TXT
                {$this->t('Bonjour,', $locale)}

                {$this->t('Vous avez demandé à être prévenu si un logement se libère :', $locale)}

                {$this->summary($alert, $locale)}

                {$this->t("Pour activer l'alerte, ouvrez ce lien :", $locale)}

                {$this->alertUrl($alert, $locale)}/activer

                {$ignore}

                Cap Monta
                TXT,
        );
    }

    /**
     * @param list<Accommodation> $homes the homes newly free, at least one
     */
    public function matches(StayAlert $alert, array $homes): void
    {
        $locale = $alert->getLocale();
        $lines = implode("\n", array_map(
            fn (Accommodation $home): string => '- '.$home->title($locale).' : '.$this->frontUrl($locale).'/logement/'.$home->getSlug(),
            \array_slice($homes, 0, self::MAX_LISTED),
        ));
        $news = \count($homes) > 1
            ? $this->t('%count% logements viennent de se libérer à vos dates.', $locale, ['%count%' => \count($homes)])
            : $this->t('Un logement vient de se libérer à vos dates.', $locale);
        $hurry = $this->t("Les dates libres partent vite en saison : la demande de réservation\nest gratuite et le propriétaire répond sous 48 heures.", $locale);

        $this->send(
            $alert,
            $this->t('Un logement est libre à vos dates', $locale),
            <<<TXT
                {$this->t('Bonjour,', $locale)}

                {$news}

                {$this->summary($alert, $locale)}

                {$lines}

                {$hurry}

                {$this->searchUrl($alert, $locale)}

                {$this->t("Vous ne voulez plus de ces messages ? Arrêtez l'alerte ici :", $locale)}
                {$this->alertUrl($alert, $locale)}

                Cap Monta
                TXT,
        );
    }

    private function summary(StayAlert $alert, string $locale): string
    {
        $nights = $this->count($alert->nights(), '%count% nuit', '%count% nuits', $locale);
        $guests = $this->count($alert->getGuests(), '%count% personne', '%count% personnes', $locale);
        $lines = [
            $this->t('Séjour : du %from% au %to% (%nights%)', $locale, [
                '%from%' => MailFormat::date($alert->getArrival(), $locale),
                '%to%' => MailFormat::date($alert->getDeparture(), $locale),
                '%nights%' => $nights,
            ]),
            $this->t('Voyageurs : %travellers%', $locale, ['%travellers%' => $guests]),
        ];

        if (null !== $alert->getResort()) {
            $lines[] = $this->t('Domaine : %resort%', $locale, ['%resort%' => $alert->getResort()->label()]);
        }

        return implode("\n", $lines);
    }

    private function frontUrl(string $locale): string
    {
        return $this->frontUrl.Locales::pathPrefix($locale);
    }

    private function alertUrl(StayAlert $alert, string $locale): string
    {
        return $this->frontUrl($locale).'/alerte/'.$this->links->keyFor($alert);
    }

    private function searchUrl(StayAlert $alert, string $locale): string
    {
        return $this->frontUrl($locale).'/recherche?'.http_build_query(array_filter([
            'arrivee' => $alert->getArrival()->format('Y-m-d'),
            'depart' => $alert->getDeparture()->format('Y-m-d'),
            'adultes' => $alert->getGuests(),
            'domaine' => $alert->getResort()?->value,
        ]));
    }

    private function send(StayAlert $alert, string $subject, string $text): void
    {
        $this->mailer->send($this->composer->compose($alert->getEmail(), $subject.' — Cap Monta', $text, $alert->getLocale()));
    }

    /**
     * @param array<string, string|int> $parameters
     */
    private function t(string $text, string $locale, array $parameters = []): string
    {
        return $this->translator->trans($text, $parameters, $locale);
    }

    private function count(int $count, string $one, string $many, string $locale): string
    {
        return $count > 1
            ? $this->t($many, $locale, ['%count%' => $count])
            : $this->t($one, $locale, ['%count%' => $count]);
    }
}
