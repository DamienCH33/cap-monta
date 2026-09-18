<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Les deux messages de l'inscription. Ils se ressemblent volontairement de
 * l'extérieur : seule la personne qui relève la boîte sait lequel elle a reçu.
 */
final readonly class AccountMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private UriSigner $signer,
        #[Autowire('%app.mail_from%')] private string $from,
        #[Autowire('%app.front_url%')] private string $frontUrl,
    ) {
    }

    public function sendVerificationLink(User $user): void
    {
        $url = $this->urls->generate(
            'api_verify_email',
            ['id' => (string) $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $link = $this->signer->sign($url, new \DateTimeImmutable('+24 hours'));
        $name = $user->getDisplayName();

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($user->getEmail())
                ->subject('Confirmez votre adresse — Cap Monta')
                ->text(
                    <<<TXT
                        Bonjour {$name},

                        Confirmez votre adresse pour pouvoir publier votre annonce :

                        {$link}

                        Ce lien est valable 24 heures.
                        Si vous n'êtes pas à l'origine de cette inscription, ignorez ce message.

                        Cap Monta
                        TXT
                ),
        );
    }

    public function sendAlreadyRegistered(string $email): void
    {
        $login = $this->frontUrl.'/connexion';

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($email)
                ->subject('Votre compte Cap Monta')
                ->text(
                    <<<TXT
                        Bonjour,

                        Une inscription vient d'être tentée avec cette adresse, mais vous avez
                        déjà un compte Cap Monta. Connectez-vous ici :

                        {$login}

                        Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :
                        votre compte n'a pas été modifié.

                        Cap Monta
                        TXT
                ),
        );
    }

    public function sendPasswordReset(User $user): void
    {
        $signed = $this->signer->sign(
            $this->urls->generate(
                'api_password_reset',
                ['id' => (string) $user->getId(), 'v' => self::fingerprint($user)],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            new \DateTimeImmutable('+1 hour'),
        );

        $query = parse_url($signed, PHP_URL_QUERY);
        $link = $this->frontUrl.'/nouveau-mot-de-passe?jeton='.rawurlencode(is_string($query) ? $query : '');
        $name = $user->getDisplayName();

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($user->getEmail())
                ->subject('Réinitialiser votre mot de passe — Cap Monta')
                ->text(
                    <<<TXT
                        Bonjour {$name},

                        Vous avez demandé à changer votre mot de passe. Choisissez-en un nouveau ici :

                        {$link}

                        Ce lien est valable une heure et ne fonctionne qu'une fois.
                        Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :
                        votre mot de passe actuel reste valable.

                        Cap Monta
                        TXT
                ),
        );
    }

    public function sendNoAccountToReset(string $email): void
    {
        $signup = $this->frontUrl.'/inscription';

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($email)
                ->subject('Votre compte Cap Monta')
                ->text(
                    <<<TXT
                        Bonjour,

                        Une réinitialisation de mot de passe a été demandée pour cette adresse,
                        mais aucun compte Cap Monta n'y est associé.

                        Vous pouvez en créer un ici : {$signup}

                        Cap Monta
                        TXT
                ),
        );
    }

    /**
     * Empreinte du mot de passe actuel. Elle entre dans la signature du lien :
     * dès que le mot de passe change, les liens émis avant deviennent caducs.
     */
    public static function fingerprint(User $user): string
    {
        return substr(hash('sha256', (string) $user->getPassword()), 0, 16);
    }
}
