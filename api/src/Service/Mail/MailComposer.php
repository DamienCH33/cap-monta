<?php

declare(strict_types=1);

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Every email of the site, in two versions built from one text.
 *
 * The plain text stays the source: it is what the mailers write and what the tests read. The
 * HTML version is derived from it, blank line by blank line: a link alone on its line becomes a
 * button, "Séjour : …" lines a table, a message between « » a quote. One wording to maintain,
 * and a mail client that shows only text loses nothing.
 */
final readonly class MailComposer
{
    /** Button label by link, first match wins. */
    private const BUTTONS = [
        '#/api/verify-email#' => 'Confirmer mon adresse',
        '#/nouveau-mot-de-passe#' => 'Choisir un nouveau mot de passe',
        '#/connexion#' => 'Me connecter',
        '#/inscription#' => 'Créer un compte',
        '#/calendrier$#' => 'Mettre à jour mon calendrier',
        '#/mon-espace/demandes#' => 'Voir mes demandes',
        '#/demande/#' => 'Suivre ma demande',
        '#/recherche#' => 'Voir les logements',
    ];

    private const URL = '#^https?://\S+$#';
    private const FACT = '#^([\p{L} ]{2,20}) : (.+)$#u';
    private const SIGNATURE = 'Cap Monta';

    /** A line at least this long was wrapped by hand: the next one continues the sentence. */
    private const WRAPPED = 60;

    public function __construct(
        private Environment $twig,
        #[Autowire('%app.mail_from%')] private string $from,
        /** Only links to the site itself become clickable inside a sentence. */
        #[Autowire('%app.front_url%')] private string $frontUrl,
    ) {
    }

    public function compose(string $to, string $subject, string $text): Email
    {
        return (new Email())
            ->from($this->from)
            ->to($to)
            ->subject($subject)
            ->text($text)
            ->html($this->twig->render('emails/layout.html.twig', [
                'subject' => $subject,
                'blocks' => $this->blocks($text),
            ]));
    }

    /**
     * @return list<MailBlock>
     */
    private function blocks(string $text): array
    {
        $blocks = [];

        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $chunk) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $chunk)), static fn (string $line): bool => '' !== $line));

            // The layout signs every email: no need to repeat it.
            if ([self::SIGNATURE] === $lines) {
                continue;
            }

            array_push($blocks, ...$this->chunk($lines));
        }

        return $blocks;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<MailBlock>
     */
    private function chunk(array $lines): array
    {
        if ([] === $lines) {
            return [];
        }

        $facts = [];
        foreach ($lines as $line) {
            if (1 !== preg_match(self::FACT, $line, $match)) {
                $facts = [];
                break;
            }
            $facts[] = [$match[1], $match[2]];
        }
        if ([] !== $facts) {
            return [MailBlock::facts($facts)];
        }

        $blocks = [];
        $paragraph = [];

        foreach ($lines as $index => $line) {
            $isLink = 1 === preg_match(self::URL, $line);
            // A message quoted between « »: everything from here to the end of the chunk.
            $isQuote = str_starts_with($line, '«');

            if (!$isLink && !$isQuote) {
                $paragraph[] = $line;
                continue;
            }

            if ([] !== $paragraph) {
                $blocks[] = MailBlock::paragraph($this->paragraph($paragraph));
                $paragraph = [];
            }

            if ($isLink) {
                $blocks[] = MailBlock::button($line, self::label($line));
                continue;
            }

            $blocks[] = MailBlock::quote(trim(implode("\n", array_slice($lines, $index)), "«» \n"));

            return $blocks;
        }

        if ([] !== $paragraph) {
            $blocks[] = MailBlock::paragraph($this->paragraph($paragraph));
        }

        return $blocks;
    }

    /**
     * The text is wrapped by hand around 80 characters: a long line followed by another is one
     * sentence, joined back so the mail client wraps it. Short lines (a name, an email, a
     * phone) keep their line break.
     *
     * @param list<string> $lines
     */
    private function paragraph(array $lines): string
    {
        $html = '';

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $html .= mb_strlen($lines[$index - 1]) >= self::WRAPPED ? ' ' : '<br>';
            }
            $html .= $this->inline($line);
        }

        return $html;
    }

    /**
     * Escapes a line, then turns the links to the site left inside a sentence into links.
     * Any other address stays plain text: a visitor's message must not bring clickable links
     * into an email the site sends.
     */
    private function inline(string $line): string
    {
        $html = htmlspecialchars($line, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $site = preg_quote(htmlspecialchars(rtrim($this->frontUrl, '/'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), '#');

        return (string) preg_replace('#'.$site.'(?:/[^\s<]*)?#', '<a href="$0" style="color:#185fa5">$0</a>', $html);
    }

    private static function label(string $url): string
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);

        foreach (self::BUTTONS as $pattern => $label) {
            if (1 === preg_match($pattern, $path)) {
                return $label;
            }
        }

        return 'Ouvrir le lien';
    }
}
