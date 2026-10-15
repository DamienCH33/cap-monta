<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\User;
use App\I18n\Locales;
use App\I18n\Translator;
use App\Service\Mail\MailComposer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Les deux messages de l'inscription. Ils se ressemblent volontairement de
 * l'extérieur : seule la personne qui relève la boîte sait lequel elle a reçu.
 *
 * Chacun part dans la langue du compte (User::getLocale()) ; les textes français sont les clés
 * des catalogues config/i18n/emails.<langue>.php.
 */
final readonly class AccountMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private MailComposer $composer,
        private Translator $translator,
        private UrlGeneratorInterface $urls,
        private UriSigner $signer,
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
        $locale = $user->getLocale();
        $validity = $this->t("Ce lien est valable 24 heures.\nSi vous n'êtes pas à l'origine de cette inscription, ignorez ce message.", $locale);

        $this->mailer->send(
            $this->composer->compose($user->getEmail(), $this->t('Confirmez votre adresse — Cap Monta', $locale), <<<TXT
                        {$this->t('Bonjour %name%,', $locale, ['%name%' => $user->getDisplayName()])}

                        {$this->t('Confirmez votre adresse pour pouvoir publier votre annonce :', $locale)}

                        {$link}

                        {$validity}

                        Cap Monta
                        TXT, $locale),
        );
    }

    /** The account's language: the address already has one, it is the recipient. */
    public function sendAlreadyRegistered(string $email, string $locale = Locales::SOURCE): void
    {
        $login = $this->frontUrl.Locales::pathPrefix($locale).'/connexion';
        $attempt = $this->t("Une inscription vient d'être tentée avec cette adresse, mais vous avez\ndéjà un compte Cap Monta. Connectez-vous ici :", $locale);
        $ignore = $this->t("Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre compte n'a pas été modifié.", $locale);

        $this->mailer->send(
            $this->composer->compose($email, $this->t('Votre compte Cap Monta', $locale), <<<TXT
                        {$this->t('Bonjour,', $locale)}

                        {$attempt}

                        {$login}

                        {$ignore}

                        Cap Monta
                        TXT, $locale),
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

        $locale = $user->getLocale();
        $query = parse_url($signed, PHP_URL_QUERY);
        $link = $this->frontUrl.Locales::pathPrefix($locale).'/nouveau-mot-de-passe?jeton='.rawurlencode(is_string($query) ? $query : '');
        $validity = $this->t("Ce lien est valable une heure et ne fonctionne qu'une fois.\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre mot de passe actuel reste valable.", $locale);

        $this->mailer->send(
            $this->composer->compose($user->getEmail(), $this->t('Réinitialiser votre mot de passe — Cap Monta', $locale), <<<TXT
                        {$this->t('Bonjour %name%,', $locale, ['%name%' => $user->getDisplayName()])}

                        {$this->t('Vous avez demandé à changer votre mot de passe. Choisissez-en un nouveau ici :', $locale)}

                        {$link}

                        {$validity}

                        Cap Monta
                        TXT, $locale),
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
