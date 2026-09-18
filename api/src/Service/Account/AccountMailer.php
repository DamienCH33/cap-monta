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
}
