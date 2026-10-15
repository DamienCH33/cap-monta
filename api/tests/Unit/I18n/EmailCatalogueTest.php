<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use App\Service\Mail\MailComposer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The emails' catalogues (config/i18n/emails.<langue>.php) against the code that uses them.
 *
 * The keys are the French texts passed to t() / trans() / count() in the mailers, read from
 * their source: a sentence changed in French without its translations would otherwise fall back
 * to French silently, in the middle of an English email.
 */
final class EmailCatalogueTest extends TestCase
{
    private const array SOURCES = [
        'src/Service/Booking/BookingMailer.php',
        'src/Service/Account/AccountMailer.php',
        'src/Service/Moderation/ModerationMailer.php',
        'src/Service/Mail/MailComposer.php',
    ];

    /** MailComposer reads "Séjour : …" lines as a table: a translation must keep that shape, or lose it. */
    private const string FACT = '#^([\p{L} ]{1,19}\p{L}) ?: (.+)$#u';

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        yield 'en' => ['en'];
        yield 'nl' => ['nl'];
        yield 'de' => ['de'];
    }

    public function testTheMailersUseTheCatalogues(): void
    {
        // A guard for the extraction itself: if it found nothing, every check below would pass.
        self::assertContains('Bonjour %name%,', self::keys());
        self::assertContains('Séjour : du %from% au %to% (%nights%)', self::keys());
        self::assertContains('Voir mes demandes', self::keys());
        self::assertContains('%count% nuits', self::keys());
        self::assertGreaterThan(50, \count(self::keys()));
    }

    #[DataProvider('locales')]
    public function testEveryTextOfTheMailersIsTranslated(string $locale): void
    {
        $missing = array_values(array_diff(self::keys(), array_keys(self::catalogue($locale))));

        self::assertSame([], $missing, "Missing from emails.$locale.php.");
    }

    #[DataProvider('locales')]
    public function testTheCatalogueHoldsNoForgottenText(string $locale): void
    {
        $unused = array_values(array_diff(array_keys(self::catalogue($locale)), self::keys()));

        self::assertSame([], $unused, "In emails.$locale.php but no longer in the code.");
    }

    #[DataProvider('locales')]
    public function testTranslationsKeepTheirPlaceholdersAndTheLayout(string $locale): void
    {
        foreach (self::catalogue($locale) as $source => $translation) {
            self::assertNotSame('', trim($translation), $source);
            self::assertSame(self::placeholders($source), self::placeholders($translation), "Placeholders differ for: $source");
            // The layout prints its texts as they are, and a blank line would start a new block.
            self::assertStringNotContainsString('<', $translation, $source);
            self::assertStringNotContainsString("\n\n", $translation, $source);
            // Only %name% placeholders: "%s" or "%d" would turn a key into a pattern for translateMessage().
            self::assertDoesNotMatchRegularExpression('/%(?:\d+\$)?[sd]/', $source, 'Use %name% placeholders, not %s / %d.');
            // No emoji, no pictograms.
            self::assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $translation, $source);

            if (!str_contains($source, "\n")) {
                self::assertSame(
                    1 === preg_match(self::FACT, $source),
                    1 === preg_match(self::FACT, $translation),
                    "A « Label : value » line must stay one in translation (and the reverse): $source",
                );
            }
        }
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%[a-z_]+%/', $text, $matches);
        $found = array_unique($matches[0]);
        sort($found);

        return $found;
    }

    /** @return array<string, string> */
    private static function catalogue(string $locale): array
    {
        $file = \dirname(__DIR__, 3).'/config/i18n/emails.'.$locale.'.php';
        self::assertFileExists($file);

        /** @var array<string, string> $entries */
        $entries = require $file;

        return $entries;
    }

    /**
     * The French texts of the mailers: the literal arguments of ->t(), ->trans() and ->count(),
     * plus MailComposer's button labels.
     *
     * @return list<string>
     */
    private static function keys(): array
    {
        static $keys = null;

        if (null !== $keys) {
            return $keys;
        }

        $found = [];

        foreach (self::SOURCES as $source) {
            $tokens = token_get_all((string) file_get_contents(\dirname(__DIR__, 3).'/'.$source));
            $count = \count($tokens);

            for ($i = 2; $i < $count; ++$i) {
                $token = $tokens[$i];

                if (!\is_array($token) || \T_STRING !== $token[0] || !\in_array($token[1], ['t', 'trans', 'count'], true)) {
                    continue;
                }
                if (!\is_array($tokens[$i - 1]) || \T_OBJECT_OPERATOR !== $tokens[$i - 1][0] || '(' !== ($tokens[$i + 1] ?? null)) {
                    continue;
                }

                // The literal arguments of the call, at its own depth.
                $depth = 0;
                for ($j = $i + 1; $j < $count; ++$j) {
                    $part = $tokens[$j];
                    if (\in_array($part, ['(', '['], true)) {
                        ++$depth;
                    } elseif (\in_array($part, [')', ']'], true)) {
                        if (0 === --$depth) {
                            break;
                        }
                    } elseif (1 === $depth && \is_array($part) && \T_CONSTANT_ENCAPSED_STRING === $part[0]) {
                        $found[] = self::literal($part[1]);
                    }
                }
            }
        }

        $composer = new \ReflectionClass(MailComposer::class);
        /** @var array<string, string> $buttons */
        $buttons = $composer->getConstant('BUTTONS');
        array_push($found, ...array_values($buttons));
        $found[] = (string) $composer->getConstant('SUPPORT_BUTTON');
        $found[] = (string) $composer->getConstant('OTHER_BUTTON');

        return $keys = array_values(array_unique($found));
    }

    /** A PHP string literal as written in the source, back to its value. */
    private static function literal(string $code): string
    {
        $body = substr($code, 1, -1);

        return "'" === $code[0]
            ? strtr($body, ['\\\\' => '\\', "\\'" => "'"])
            : stripcslashes($body);
    }
}
