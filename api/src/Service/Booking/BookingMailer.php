<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\BookingRequest;
use App\Service\Mail\MailComposer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The emails of a booking request. Written as plain text; MailComposer adds the HTML version.
 *
 * Privacy rule, stated on the booking form: the guest's email and phone reach the owner only
 * once the request is accepted, and the owner's only then too. Before that, each side talks
 * through the site.
 */
final readonly class BookingMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private MailComposer $composer,
        #[Autowire('%app.front_url%')] private string $frontUrl,
    ) {
    }

    public function received(BookingRequest $request): void
    {
        $title = $request->getAccommodation()->title();
        $summary = $this->summary($request);
        $deadline = self::dateTime($request->getExpiresAt());
        $tracking = $this->trackingUrl($request);

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            'Nouvelle demande pour votre '.$title,
            <<<TXT
                Bonjour,

                Vous avez reçu une demande de réservation pour votre {$title}.

                {$summary}
                {$this->guestMessage($request)}
                Répondez avant le {$deadline} : passé ce délai, la demande expire
                et le voyageur en est prévenu.

                {$this->frontUrl}/mon-espace/demandes

                Les coordonnées du voyageur vous seront transmises si vous acceptez.

                Cap Monta
                TXT,
        );

        $this->send(
            $request->getGuestEmail(),
            'Votre demande pour un '.$title.' est envoyée',
            <<<TXT
                Bonjour {$this->guestName($request)},

                Votre demande a bien été transmise au propriétaire.

                {$summary}

                Il a jusqu'au {$deadline} pour vous répondre. Vous recevrez sa réponse
                par email. Aucun paiement n'a été demandé.

                Suivre ou annuler votre demande :
                {$tracking}

                Cap Monta
                TXT,
        );
    }

    public function reminder(BookingRequest $request): void
    {
        $title = $request->getAccommodation()->title();
        $deadline = self::dateTime($request->getExpiresAt());

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            'Rappel : une demande attend votre réponse',
            <<<TXT
                Bonjour,

                Une demande pour votre {$title} attend toujours votre réponse.

                {$this->summary($request)}

                Sans réponse avant le {$deadline}, elle expirera et le voyageur ira voir
                ailleurs. Accepter ou refuser prend un clic :

                {$this->frontUrl}/mon-espace/demandes

                Cap Monta
                TXT,
        );
    }

    public function accepted(BookingRequest $request): void
    {
        $accommodation = $request->getAccommodation();
        $owner = $accommodation->getOwner();
        $title = $accommodation->title();
        $summary = $this->summary($request);
        $ownerContact = trim(self::oneLine($owner->getDisplayName())."\n".$owner->getEmail()."\n".self::oneLine($owner->getPhone() ?? ''));
        $guestContact = trim($this->guestName($request)."\n".$request->getGuestEmail()."\n".self::oneLine($request->getGuestPhone() ?? ''));

        $this->send(
            $request->getGuestEmail(),
            'Votre séjour est accepté — '.$title,
            <<<TXT
                Bonjour {$this->guestName($request)},

                Bonne nouvelle : le propriétaire accepte votre demande.

                {$summary}
                {$this->ownerMessage($request)}
                Pour convenir de l'arrivée et du règlement, contactez-le directement :

                {$ownerContact}

                Suivre ou annuler votre réservation :
                {$this->trackingUrl($request)}

                Cap Monta
                TXT,
        );

        $this->send(
            $owner->getEmail(),
            'Réservation confirmée — '.$title,
            <<<TXT
                Bonjour,

                Vous avez accepté cette demande. Les dates sont bloquées dans votre calendrier.

                {$summary}

                Coordonnées du voyageur :

                {$guestContact}

                {$this->frontUrl}/mon-espace/demandes

                Cap Monta
                TXT,
        );
    }

    public function declined(BookingRequest $request): void
    {
        $title = $request->getAccommodation()->title();

        $this->send(
            $request->getGuestEmail(),
            'Votre demande pour un '.$title,
            <<<TXT
                Bonjour {$this->guestName($request)},

                Le propriétaire ne peut pas accueillir votre séjour du {$this->stay($request)}.
                {$this->ownerMessage($request)}
                D'autres logements sont peut-être libres à ces dates :
                {$this->searchUrl($request)}

                Cap Monta
                TXT,
        );
    }

    public function expired(BookingRequest $request): void
    {
        $title = $request->getAccommodation()->title();
        $stay = $this->stay($request);

        $this->send(
            $request->getGuestEmail(),
            'Pas de réponse à votre demande — '.$title,
            <<<TXT
                Bonjour {$this->guestName($request)},

                Le propriétaire n'a pas répondu à temps à votre demande du {$stay}.
                Elle est annulée : vous ne devez rien.

                D'autres logements sont peut-être libres à ces dates :
                {$this->searchUrl($request)}

                Cap Monta
                TXT,
        );

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            'Demande expirée — '.$title,
            <<<TXT
                Bonjour,

                La demande du {$stay} pour votre {$title} a expiré sans réponse.
                Le voyageur en a été prévenu.

                Pour ne pas recevoir de demandes que vous ne pouvez pas honorer,
                gardez votre calendrier à jour :
                {$this->frontUrl}/mon-espace/logements/{$request->getAccommodation()->getSlug()}/calendrier

                Cap Monta
                TXT,
        );
    }

    public function cancelledByOwner(BookingRequest $request): void
    {
        $title = $request->getAccommodation()->title();

        $this->send(
            $request->getGuestEmail(),
            'Réservation annulée — '.$title,
            <<<TXT
                Bonjour {$this->guestName($request)},

                Le propriétaire a annulé votre séjour du {$this->stay($request)}.
                {$this->ownerMessage($request)}
                D'autres logements sont peut-être libres à ces dates :
                {$this->searchUrl($request)}

                Cap Monta
                TXT,
        );
    }

    public function cancelledByGuest(BookingRequest $request, bool $wasAccepted): void
    {
        $title = $request->getAccommodation()->title();
        $what = $wasAccepted
            ? 'a annulé sa réservation. Les dates sont de nouveau libres dans votre calendrier.'
            : 'a retiré sa demande.';

        $this->send(
            $request->getAccommodation()->getOwner()->getEmail(),
            ($wasAccepted ? 'Réservation annulée' : 'Demande retirée').' — '.$title,
            <<<TXT
                Bonjour,

                Le voyageur du {$this->stay($request)} {$what}

                {$this->frontUrl}/mon-espace/demandes

                Cap Monta
                TXT,
        );
    }

    private function summary(BookingRequest $request): string
    {
        $travellers = self::plural($request->getAdults(), 'adulte', 'adultes');

        if ($request->getChildren() > 0) {
            $travellers .= ', '.self::plural($request->getChildren(), 'enfant', 'enfants');
        }

        if ($request->getInfants() > 0) {
            $travellers .= ', '.self::plural($request->getInfants(), 'bébé', 'bébés');
        }

        if ($request->getPets() > 0) {
            $travellers .= ', '.self::plural($request->getPets(), 'animal', 'animaux');
        }

        $price = null === $request->price() ? 'à convenir' : self::euros($request->price());
        $nights = self::plural($request->nights(), 'nuit', 'nuits');

        return <<<TXT
            Séjour : du {$this->stay($request)} ({$nights})
            Voyageurs : {$travellers}
            Prix : {$price}
            TXT;
    }

    private function stay(BookingRequest $request): string
    {
        return self::date($request->getStartDate()).' au '.self::date($request->getEndDate());
    }

    private function guestMessage(BookingRequest $request): string
    {
        return null === $request->getMessage() ? '' : "\nSon message :\n« ".self::quoted($request->getMessage())." »\n";
    }

    private function ownerMessage(BookingRequest $request): string
    {
        return null === $request->getOwnerMessage() ? '' : "\nSon message :\n« ".self::quoted($request->getOwnerMessage())." »\n";
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

    private function trackingUrl(BookingRequest $request): string
    {
        return $this->frontUrl.'/demande/'.$request->getTrackingToken();
    }

    /** The same search again: dates and travellers, so the results fit at once. */
    private function searchUrl(BookingRequest $request): string
    {
        return $this->frontUrl.'/recherche?'.http_build_query(array_filter([
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
     * address typed, whether or not it has requests: only its mailbox learns the answer.
     *
     * @param list<BookingRequest> $requests
     */
    public function recovery(string $email, array $requests): void
    {
        if ([] === $requests) {
            $this->send($email, 'Vos demandes de réservation', <<<TXT
                Bonjour,

                Quelqu'un (vous, sans doute) a demandé à retrouver les demandes de réservation
                envoyées depuis cette adresse. Aucune n'est en cours.

                Si vous pensez en avoir envoyé une, elle a peut-être été faite avec une autre
                adresse email. Pour chercher un logement :
                {$this->frontUrl}/recherche

                Cap Monta
                TXT);

            return;
        }

        $lines = implode("\n\n", array_map(
            fn (BookingRequest $request): string => sprintf(
                "%s — du %s (%s)\n%s",
                $request->getAccommodation()->title(),
                $this->stay($request),
                $request->isAccepted() ? 'acceptée' : 'en attente de réponse',
                $this->trackingUrl($request),
            ),
            $requests,
        ));

        $this->send($email, 'Vos demandes de réservation', <<<TXT
            Bonjour,

            Voici vos demandes de réservation en cours. Chaque lien permet de suivre la
            demande et, si besoin, de l'annuler.

            {$lines}

            Ces liens sont personnels : ne les transférez pas.

            Cap Monta
            TXT);
    }

    private function send(string $to, string $subject, string $text): void
    {
        $this->mailer->send($this->composer->compose($to, $subject.' — Cap Monta', $text));
    }

    private static function date(\DateTimeImmutable $date): string
    {
        return (string) (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'EEEE d MMMM y'))->format($date);
    }

    private static function dateTime(\DateTimeImmutable $date): string
    {
        return (string) (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Paris', null, "EEEE d MMMM 'à' HH'h'mm"))->format($date);
    }

    private static function euros(int $cents): string
    {
        return number_format($cents / 100, 0, ',', ' ').' €';
    }

    private static function plural(int $count, string $one, string $many): string
    {
        return $count.' '.($count > 1 ? $many : $one);
    }
}
