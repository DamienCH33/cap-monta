<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Mail\MailComposer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class MailComposerTest extends KernelTestCase
{
    public function testTheHtmlVersionIsBuiltFromTheText(): void
    {
        $text = <<<TXT
            Bonjour <Jeanne>,

            Séjour : du samedi 4 juillet au samedi 11 juillet (7 nuits)
            Prix : 700 €

            Son message :
            « Nous venons avec <b>un chien</b> »

            Suivre ou annuler votre demande :
            https://cap-monta.test/demande/abc123

            Vous pouvez en créer un ici : https://cap-monta.test/inscription

            Répondez avant le jeudi 24 septembre à 14h00 : passé ce délai, la demande expire
            et le voyageur en est prévenu.

            Voir aussi https://ailleurs.example/piege

            Jeanne Martin
            jeanne@example.com

            Cap Monta
            TXT;

        $email = $this->composer()->compose('jeanne@example.com', 'Votre demande — Cap Monta', $text);
        $html = (string) $email->getHtmlBody();

        self::assertSame($text, $email->getTextBody());
        self::assertSame('noreply@cap-monta.test', $email->getFrom()[0]->getAddress());
        // What a guest types is escaped, never interpreted.
        self::assertStringContainsString('Bonjour &lt;Jeanne&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;un chien&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>un chien', $html);
        // The summary becomes a table, the lone link a labelled button, the inline one a link.
        self::assertMatchesRegularExpression('#>Séjour</td>\s*<td[^>]*>du samedi 4 juillet#u', $html);
        self::assertMatchesRegularExpression('#<a href="https://cap-monta.test/demande/abc123"[^>]*>Suivre ma demande</a>#', $html);
        self::assertStringContainsString('<a href="https://cap-monta.test/inscription"', $html);
        // A link to another site stays text.
        self::assertStringNotContainsString('href="https://ailleurs.example', $html);
        // A sentence wrapped by hand is joined back; a contact block keeps its lines.
        self::assertStringContainsString('la demande expire et le voyageur', $html);
        self::assertStringContainsString('Jeanne Martin<br>jeanne@example.com', $html);
        // The layout signs: the text signature is not repeated in the body.
        self::assertStringNotContainsString('>Cap Monta</p>', $html);
    }

    private function composer(): MailComposer
    {
        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);

        return new MailComposer($twig, 'noreply@cap-monta.test', 'https://cap-monta.test');
    }
}
