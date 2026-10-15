<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\BookingRequest;
use App\I18n\Locales;
use App\I18n\Translator;
use App\Service\Mail\MailComposer;
use App\Service\Mail\MailFormat;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The emails of a booking request. Written as plain text; MailComposer adds the HTML version.
 *
 * Privacy rule, stated on the booking form: the guest's email and phone reach the owner only
 * once the request is accepted, and the owner's only then too. Before that, each side talks
 * through the site.
 *
 * Each email goes out in its recipient's language: the owner's (User::getLocale()), the
 * guest's (BookingRequest::getGuestLocale()). The French texts are the catalogue keys
 * (config/i18n/emails.<langue>.php): whole paragraphs, so a translator sees the sentence.
 */
final readonly class BookingMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private MailComposer $composer,
        private Translator $translator,
        #[Autowire('%app.front_url%')] private string $frontUrl,
        #[Autowire(env: 'default::SUPPORT_URL')] private ?string $supportUrl = null,
    ) {
    }

    /**
     * Le moment où le site vient de rendre service au propriétaire : une réservation sans
     * commission. C'est là qu'un lien de dons a du sens. Rien si SUPPORT_URL est vide.
     */
    private function supportNote(string $locale): string
    {
        if (null === $this->supportUrl || '' === $this->supportUrl) {
            return '';
        }

        return $this->t('Cette réservation ne vous a coûté aucune commission. Cap Monta reste gratuit grâce aux dons : si le site vous rend service, vous pouvez le soutenir.', $locale)
            ."\n\n{$this->supportUrl}\n";
    }

    public function received(BookingRequest $request): void
    {
        $owner = $request->getAccommodation()->getOwner()->getLocale();
        $guest = $request->getGuestLocale();
        $title = $request->getAccommodation()->title($owner);
        $deadline = MailFormat::dateTime($request->getExpiresAt(), $owner);
        $answer = $this->t("Répondez avant le %until% : passé ce délai, la demande expire\net le voyageur en est prévenu.", $owner, ['%until%' => $deadline]);

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            $this->t('Nouvelle demande pour votre %title%', $owner, ['%title%' => $title]),
            <<<TXT
                {$this->t('Bonjour,', $owner)}

                {$this->t('Vous avez reçu une demande de réservation pour votre %title%.', $owner, ['%title%' => $title])}

                {$this->summary($request, $owner)}
                {$this->guestMessage($request, $owner)}
                {$answer}

                {$this->frontUrl($owner)}/mon-espace/demandes

                {$this->t('Les coordonnées du voyageur vous seront transmises si vous acceptez.', $owner)}

                Cap Monta
                TXT,
            $owner,
        );

        $deadline = MailFormat::dateTime($request->getExpiresAt(), $guest);
        $delay = $this->t("Il a jusqu'au %until% pour vous répondre. Vous recevrez sa réponse\npar email. Aucun paiement n'a été demandé.", $guest, ['%until%' => $deadline]);

        $this->send(
            $request->getGuestEmail(),
            $this->t('Votre demande pour un %title% est envoyée', $guest, ['%title%' => $request->getAccommodation()->title($guest)]),
            <<<TXT
                {$this->t('Bonjour %name%,', $guest, ['%name%' => $this->guestName($request)])}

                {$this->t('Votre demande a bien été transmise au propriétaire.', $guest)}

                {$this->summary($request, $guest)}

                {$delay}

                {$this->t('Suivre ou annuler votre demande :', $guest)}
                {$this->trackingUrl($request, $guest)}

                Cap Monta
                TXT,
            $guest,
        );
    }

    public function reminder(BookingRequest $request): void
    {
        $owner = $request->getAccommodation()->getOwner()->getLocale();
        $title = $request->getAccommodation()->title($owner);
        $warning = $this->t("Sans réponse avant le %until%, elle expirera et le voyageur ira voir\nailleurs. Accepter ou refuser prend un clic :", $owner, [
            '%until%' => MailFormat::dateTime($request->getExpiresAt(), $owner),
        ]);

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            $this->t('Rappel : une demande attend votre réponse', $owner),
            <<<TXT
                {$this->t('Bonjour,', $owner)}

                {$this->t('Une demande pour votre %title% attend toujours votre réponse.', $owner, ['%title%' => $title])}

                {$this->summary($request, $owner)}

                {$warning}

                {$this->frontUrl($owner)}/mon-espace/demandes

                Cap Monta
                TXT,
            $owner,
        );
    }

    public function accepted(BookingRequest $request): void
    {
        $accommodation = $request->getAccommodation();
        $owner = $accommodation->getOwner();
        $guest = $request->getGuestLocale();
        $ownerContact = trim(self::oneLine($owner->getDisplayName())."\n".$owner->getEmail()."\n".self::oneLine($owner->getPhone() ?? ''));
        $guestContact = trim($this->guestName($request)."\n".$request->getGuestEmail()."\n".self::oneLine($request->getGuestPhone() ?? ''));

        $this->send(
            $request->getGuestEmail(),
            $this->t('Votre séjour est accepté — %title%', $guest, ['%title%' => $accommodation->title($guest)]),
            <<<TXT
                {$this->t('Bonjour %name%,', $guest, ['%name%' => $this->guestName($request)])}

                {$this->t('Bonne nouvelle : le propriétaire accepte votre demande.', $guest)}

                {$this->summary($request, $guest)}
                {$this->ownerMessage($request, $guest)}
                {$this->t("Pour convenir de l'arrivée et du règlement, contactez-le directement :", $guest)}

                {$ownerContact}

                {$this->t('Suivre ou annuler votre réservation :', $guest)}
                {$this->trackingUrl($request, $guest)}

                Cap Monta
                TXT,
            $guest,
        );

        $locale = $owner->getLocale();

        $this->send(
            $owner->getEmail(),
            $this->t('Réservation confirmée — %title%', $locale, ['%title%' => $accommodation->title($locale)]),
            <<<TXT
                {$this->t('Bonjour,', $locale)}

                {$this->t('Vous avez accepté cette demande. Les dates sont bloquées dans votre calendrier.', $locale)}

                {$this->summary($request, $locale)}

                {$this->t('Coordonnées du voyageur :', $locale)}

                {$guestContact}

                {$this->frontUrl($locale)}/mon-espace/demandes

                {$this->supportNote($locale)}
                Cap Monta
                TXT,
            $locale,
        );
    }

    public function declined(BookingRequest $request): void
    {
        $guest = $request->getGuestLocale();

        $this->send(
            $request->getGuestEmail(),
            $this->t('Votre demande pour un %title%', $guest, ['%title%' => $request->getAccommodation()->title($guest)]),
            <<<TXT
                {$this->t('Bonjour %name%,', $guest, ['%name%' => $this->guestName($request)])}

                {$this->t('Le propriétaire ne peut pas accueillir votre séjour du %from% au %to%.', $guest, $this->stay($request, $guest))}
                {$this->ownerMessage($request, $guest)}
                {$this->t("D'autres logements sont peut-être libres à ces dates :", $guest)}
                {$this->searchUrl($request, $guest)}

                Cap Monta
                TXT,
            $guest,
        );
    }

    public function expired(BookingRequest $request): void
    {
        $guest = $request->getGuestLocale();
        $owner = $request->getAccommodation()->getOwner()->getLocale();
        $title = $request->getAccommodation()->title($owner);
        $toGuest = $this->t("Le propriétaire n'a pas répondu à temps à votre demande du %from% au %to%.\nElle est annulée : vous ne devez rien.", $guest, $this->stay($request, $guest));
        $toOwner = $this->t("La demande du %from% au %to% pour votre %title% a expiré sans réponse.\nLe voyageur en a été prévenu.", $owner, [
            ...$this->stay($request, $owner),
            '%title%' => $title,
        ]);
        $advice = $this->t("Pour ne pas recevoir de demandes que vous ne pouvez pas honorer,\ngardez votre calendrier à jour :", $owner);

        $this->send(
            $request->getGuestEmail(),
            $this->t('Pas de réponse à votre demande — %title%', $guest, ['%title%' => $request->getAccommodation()->title($guest)]),
            <<<TXT
                {$this->t('Bonjour %name%,', $guest, ['%name%' => $this->guestName($request)])}

                {$toGuest}

                {$this->t("D'autres logements sont peut-être libres à ces dates :", $guest)}
                {$this->searchUrl($request, $guest)}

                Cap Monta
                TXT,
            $guest,
        );

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            $this->t('Demande expirée — %title%', $owner, ['%title%' => $title]),
            <<<TXT
                {$this->t('Bonjour,', $owner)}

                {$toOwner}

                {$advice}
                {$this->frontUrl($owner)}/mon-espace/logements/{$request->getAccommodation()->getSlug()}/calendrier

                Cap Monta
                TXT,
            $owner,
        );
    }

    public function cancelledByOwner(BookingRequest $request): void
    {
        $guest = $request->getGuestLocale();

        $this->send(
            $request->getGuestEmail(),
            $this->t('Réservation annulée — %title%', $guest, ['%title%' => $request->getAccommodation()->title($guest)]),
            <<<TXT
                {$this->t('Bonjour %name%,', $guest, ['%name%' => $this->guestName($request)])}

                {$this->t('Le propriétaire a annulé votre séjour du %from% au %to%.', $guest, $this->stay($request, $guest))}
                {$this->ownerMessage($request, $guest)}
                {$this->t("D'autres logements sont peut-être libres à ces dates :", $guest)}
                {$this->searchUrl($request, $guest)}

                Cap Monta
                TXT,
            $guest,
        );
    }

    public function cancelledByGuest(BookingRequest $request, bool $wasAccepted): void
    {
        $owner = $request->getAccommodation()->getOwner()->getLocale();
        $title = ['%title%' => $request->getAccommodation()->title($owner)];
        $stay = $this->stay($request, $owner);

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            $wasAccepted
                ? $this->t('Réservation annulée — %title%', $owner, $title)
                : $this->t('Demande retirée — %title%', $owner, $title),
            <<<TXT
                {$this->t('Bonjour,', $owner)}

                {$this->cancelledByGuestLine($wasAccepted, $owner, $stay)}

                {$this->frontUrl($owner)}/mon-espace/demandes

                Cap Monta
                TXT,
            $owner,
        );
    }

    /**
     * @param array<string, string> $stay
     */
    private function cancelledByGuestLine(bool $wasAccepted, string $locale, array $stay): string
    {
        return $wasAccepted
            ? $this->t('Le voyageur du %from% au %to% a annulé sa réservation. Les dates sont de nouveau libres dans votre calendrier.', $locale, $stay)
            : $this->t('Le voyageur du %from% au %to% a retiré sa demande.', $locale, $stay);
    }

    private function summary(BookingRequest $request, string $locale): string
    {
        $travellers = $this->count($request->getAdults(), '%count% adulte', '%count% adultes', $locale);

        if ($request->getChildren() > 0) {
            $travellers .= ', '.$this->count($request->getChildren(), '%count% enfant', '%count% enfants', $locale);
        }

        if ($request->getInfants() > 0) {
            $travellers .= ', '.$this->count($request->getInfants(), '%count% bébé', '%count% bébés', $locale);
        }

        if ($request->getPets() > 0) {
            $travellers .= ', '.$this->count($request->getPets(), '%count% animal', '%count% animaux', $locale);
        }

        $price = null === $request->price() ? $this->t('à convenir', $locale) : MailFormat::euros($request->price(), $locale);
        $nights = $this->count($request->nights(), '%count% nuit', '%count% nuits', $locale);

        return implode("\n", [
            $this->t('Séjour : du %from% au %to% (%nights%)', $locale, [...$this->stay($request, $locale), '%nights%' => $nights]),
            $this->t('Voyageurs : %travellers%', $locale, ['%travellers%' => $travellers]),
            $this->t('Prix : %price%', $locale, ['%price%' => $price]),
        ]);
    }

    /**
     * The dates of the stay, as parameters of a sentence: "du %from% au %to%".
     *
     * @return array{'%from%': string, '%to%': string}
     */
    private function stay(BookingRequest $request, string $locale): array
    {
        return [
            '%from%' => MailFormat::date($request->getStartDate(), $locale),
            '%to%' => MailFormat::date($request->getEndDate(), $locale),
        ];
    }

    private function guestMessage(BookingRequest $request, string $locale): string
    {
        return null === $request->getMessage() ? '' : "\n".$this->t('Son message :', $locale)."\n« ".self::quoted($request->getMessage())." »\n";
    }

    private function ownerMessage(BookingRequest $request, string $locale): string
    {
        return null === $request->getOwnerMessage() ? '' : "\n".$this->t('Son message :', $locale)."\n« ".self::quoted($request->getOwnerMessage())." »\n";
    }

    private function guestName(BookingRequest $request): string
    {
        return self::oneLine($request->getGuestName());
    }

    /**
     * What a visitor typed stays on its line: a name with line breaks could otherwise add
     * paragraphs, links or buttons of its own to the email (MailComposer reads the layout
     * from the text).
     */
    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * A message keeps its line breaks but loses its blank lines: the quote then runs to its
     * end, and nothing inside it can become a button or a table (MailComposer).
     */
    private static function quoted(string $text): string
    {
        return trim((string) preg_replace('/\R(?:\h*\R)+/u', "\n", trim($text)));
    }

    /** The site in the recipient's language: "https://…" in French, "https://…/de" in German. */
    private function frontUrl(string $locale): string
    {
        return $this->frontUrl.Locales::pathPrefix($locale);
    }

    private function trackingUrl(BookingRequest $request, string $locale): string
    {
        return $this->frontUrl($locale).'/demande/'.$request->getTrackingToken();
    }

    /** The same search again: dates and travellers, so the results fit at once. */
    private function searchUrl(BookingRequest $request, string $locale): string
    {
        return $this->frontUrl($locale).'/recherche?'.http_build_query(array_filter([
            'arrivee' => $request->getStartDate()->format('Y-m-d'),
            'depart' => $request->getEndDate()->format('Y-m-d'),
            'adultes' => $request->getAdults(),
            'enfants' => $request->getChildren(),
            'bebes' => $request->getInfants(),
            'animaux' => $request->getPets(),
        ]));
    }

    /**
     * "Retrouver mes demandes": the tracking links of every request still in play. Sent to the
     * address typed, whether or not it has requests: only its mailbox learns the answer. In
     * the language of the page the visitor asked from.
     *
     * @param list<BookingRequest> $requests
     */
    public function recovery(string $email, array $requests): void
    {
        // Aucune demande en cours : aucun email. Écrire à une adresse inconnue laisserait
        // n'importe qui faire envoyer des messages par le site (et le mettre sur liste noire).
        if ([] === $requests) {
            return;
        }

        $locale = $this->translator->locale();

        $lines = implode("\n\n", array_map(
            fn (BookingRequest $request): string => ($request->isAccepted()
                ? $this->t('%title% — du %from% au %to% (acceptée)', $locale, [...$this->stay($request, $locale), '%title%' => $request->getAccommodation()->title($locale)])
                : $this->t('%title% — du %from% au %to% (en attente de réponse)', $locale, [...$this->stay($request, $locale), '%title%' => $request->getAccommodation()->title($locale)])
            )."\n".$this->trackingUrl($request, $locale),
            $requests,
        ));
        $intro = $this->t("Voici vos demandes de réservation en cours. Chaque lien permet de suivre la\ndemande et, si besoin, de l'annuler.", $locale);

        $this->send($email, $this->t('Vos demandes de réservation', $locale), <<<TXT
            {$this->t('Bonjour,', $locale)}

            {$intro}

            {$lines}

            {$this->t('Ces liens sont personnels : ne les transférez pas.', $locale)}

            Cap Monta
            TXT, $locale);
    }

    private function send(string $to, string $subject, string $text, string $locale): void
    {
        $this->mailer->send($this->composer->compose($to, $subject.' — Cap Monta', $text, $locale));
    }

    /**
     * A text of the catalogue (config/i18n/emails.<langue>.php) in the recipient's language.
     *
     * @param array<string, string|int> $parameters
     */
    private function t(string $text, string $locale, array $parameters = []): string
    {
        return $this->translator->trans($text, $parameters, $locale);
    }

    /** "1 nuit", "7 nuits": singular up to one, in every language of the site. */
    private function count(int $count, string $one, string $many, string $locale): string
    {
        return $count > 1
            ? $this->t($many, $locale, ['%count%' => $count])
            : $this->t($one, $locale, ['%count%' => $count]);
    }
}
