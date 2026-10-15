<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use App\Enum\ListingImportFailure;
use App\I18n\Translator;
use App\Reference\EuronatDistricts;
use App\Service\Account\AccountDeleter;
use App\Service\Booking\BookingDesk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The API messages' catalogues (config/i18n/messages.<langue>.php) against the code.
 *
 * The French messages are read from the source: constraint messages, exceptions, refusals.
 * A new message without its translations would otherwise reach a Dutch or German visitor in
 * French, silently.
 */
final class MessageCatalogueTest extends TestCase
{
    private const array LOCALES = ['en', 'nl', 'de'];

    /**
     * Not a message sent to a client: CLI, fixtures, emails (emails.<langue>.php), the import's
     * evaluation, the JSON schema given to the model.
     */
    private const string SKIPPED = '#/(?:Command|DataFixtures|Story|Factory|Service/Mail|Service/Moderation|Service/ListingImport/Evaluation)/|(?:Mailer|ListingImportSchema)\.php$#';

    /** A PHP string literal, single or double quoted, possibly passed through sprintf(). */
    private const string LITERAL = '(?:\\\\?sprintf\(\s*)?(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")';

    /**
     * Where a message is written. Internal exceptions (LogicException, the import's
     * InvalidExtractionException, RuntimeException) are left out: they never reach a client.
     */
    private const array PLACES = [
        // Constraints: message: '…', maxSizeMessage: '…', and a custom constraint's default.
        '\b\w*[mM]essage:\s*',
        'public string \$message\s*=\s*',
        // throw new …HttpException('…'), a domain exception, TooManyRequestsHttpException($retry, '…').
        'throw new (?!\\\\?(?:LogicException|InvalidArgumentException|RuntimeException|InvalidExtractionException)\b)\\\\?[\w\\\\]*(?:Exception|Refused)\(\s*(?:[^\'";]*?,\s*)?',
        // Domain exceptions' named constructors and constructors.
        '\bnew self\(\s*(?:[\w\\\\]+::\w+\s*,\s*)?',
        'parent::__construct\(\s*',
        // Controllers' and listeners' helpers: refuse('field', '…'), refuse(Response::…, '…'), problem(403, '…').
        '\b(?:refuse|invalid|problem|conflict|buildViolation)\(\s*(?:(?:\'\w+\'|[\w:]+)\s*,\s*)?',
        // Refusals as [field, message] pairs (RateRules, ImportApplier), JSON bodies, messages built in a match.
        '=>\s*\[\s*\'\w+\'\s*,\s*',
        '\'(?:message|detail|title|description|district|photos)\'\s*=>\s*',
        '\$(?:message|questions\[\])\s*=\s*',
        // Explanations returned as they are (ChangeAccommodationStatusProcessor::explain()), or by a closure.
        '\breturn\s+',
        '\):\s*string\s*=>\s*',
        // Messages kept in a constant (ResetPasswordController::INVALID, AccountDeleter::CLOSING_MESSAGE).
        '\bconst\s+(?:string\s+)?[A-Z_]+\s*=\s*',
    ];

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    public function testTheScanFindsTheMessages(): void
    {
        // A guard for the extraction itself: if it found nothing, every check below would pass.
        $messages = self::messagesInCode();

        self::assertContains('Choisissez un mot de passe.', $messages);
        self::assertContains('Votre mot de passe doit faire au moins %d caractères.', $messages);
        self::assertContains('Équipement inconnu : %s.', $messages);
        self::assertContains('Ces dates viennent d’être prises. Choisissez-en d’autres.', $messages);
        self::assertContains('Ce logement accueille %d personnes au maximum (%d demandées).', $messages);
        self::assertContains('Choisissez le premier jour indisponible.', $messages);
        self::assertContains('Trop de requêtes depuis cette adresse. Réessayez dans quelques minutes.', $messages);
        self::assertContains('Le minimum de nuits va de 1 à %d.', $messages);
        self::assertContains('Quel est le prix pour « %s » ?', $messages);
        self::assertGreaterThan(100, \count($messages));
        // Internal exceptions stay out.
        self::assertNotContains('Cette lecture a déjà été utilisée.', $messages);
    }

    public function testEveryMessageOfTheCodeIsTranslated(): void
    {
        $expected = [
            ...self::messagesInCode(),
            // Built or stored outside the places scanned above.
            ...array_map(static fn (ListingImportFailure $failure): string => $failure->messageForOwner(), ListingImportFailure::cases()),
            AccountDeleter::CLOSING_MESSAGE,
            BookingDesk::DATES_TAKEN_MESSAGE,
            ...array_merge(...array_map(static fn (array $district): array => $district[1], EuronatDistricts::ALL)),
        ];

        $missing = array_values(array_diff(array_unique($expected), array_keys(self::catalogue('en'))));

        self::assertSame([], $missing, 'Missing from messages.en.php (then nl, de).');
    }

    public function testTheCataloguesHaveTheSameKeys(): void
    {
        $reference = array_keys(self::catalogue('en'));

        foreach (self::LOCALES as $locale) {
            $keys = array_keys(self::catalogue($locale));
            self::assertSame([], array_values(array_diff($reference, $keys)), "Missing from messages.$locale.php.");
            self::assertSame([], array_values(array_diff($keys, $reference)), "Only in messages.$locale.php.");
            self::assertSame(\count($keys), self::countEntries($locale), "A key is written twice in messages.$locale.php.");
        }
    }

    #[DataProvider('locales')]
    public function testTranslationsKeepTheirPlaceholders(string $locale): void
    {
        foreach (self::catalogue($locale) as $source => $translation) {
            self::assertNotSame('', trim($translation), $source);
            self::assertSame(self::placeholders($source), self::placeholders($translation), "Placeholders differ for: $source");
            self::assertStringNotContainsString("\n", $translation, $source);
            // No emoji, no pictograms.
            self::assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $translation, $source);

            // French typography stays French: no space before a colon or a question mark.
            self::assertDoesNotMatchRegularExpression('/\s[:?]/u', $translation, $source);
        }
    }

    public function testComposedMessagesAreTranslated(): void
    {
        $translator = new Translator(new RequestStack(), \dirname(__DIR__, 3).'/config/i18n');

        self::assertSame(
            'This home sleeps 4 people at most (6 requested).',
            $translator->translateMessage(\sprintf('Ce logement accueille %d personnes au maximum (%d demandées).', 4, 6), 'en'),
        );
        self::assertSame(
            'Der Aufenthalt dauert 3 Nächte: Der Eigentümer verlangt in diesem Zeitraum mindestens 7.',
            $translator->translateMessage(\sprintf('Le séjour dure %d nuits : le propriétaire en demande au moins %d sur cette période.', 3, 7), 'de'),
        );
        // A validator's message once composed, and the lines of an UnprocessableEntityHttpException.
        self::assertSame(
            "Unbekanntes Ausstattungsmerkmal: \"sauna\".\nFügen Sie mindestens ein Foto hinzu.",
            $translator->translateMessage("Équipement inconnu : \"sauna\".\nAjoutez au moins une photo.", 'de'),
        );
        self::assertSame(
            'Wat is de prijs voor ‘Juli’?',
            $translator->translateMessage('Quel est le prix pour « Juli » ?', 'nl'),
        );
        // An exact key wins over the pattern it also matches.
        self::assertSame(
            'A period could not be read: check this part of your listing.',
            $translator->translateMessage('« une période » n\'a pas pu être lu : vérifiez cette partie de votre annonce.', 'en'),
        );
        self::assertSame(
            'The deposit must be between 10 and 50 %.',
            $translator->translateMessage("L'acompte doit être compris entre 10 et 50 %.", 'en'),
        );
        // The language of the request when none is given.
        $requests = new RequestStack();
        $request = new Request();
        $request->setLocale('nl');
        $requests->push($request);
        self::assertSame('Kies een wachtwoord.', new Translator($requests, \dirname(__DIR__, 3).'/config/i18n')->translateMessage('Choisissez un mot de passe.'));
    }

    /**
     * The French messages written in the code, as the client receives them once composed:
     * a validator's {{ limit }} becomes %d, its {{ value }} %s.
     *
     * @return list<string>
     */
    private static function messagesInCode(): array
    {
        static $messages = null;

        if (null !== $messages) {
            return $messages;
        }

        $root = \dirname(__DIR__, 3).'/src';
        $pattern = '#(?:'.implode('|', self::PLACES).')'.self::LITERAL.'#s';
        $found = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if ('php' !== $file->getExtension() || 1 === preg_match(self::SKIPPED, $path)) {
                continue;
            }

            preg_match_all($pattern, (string) file_get_contents($path), $matches);

            foreach ($matches[1] as $code) {
                $text = self::literal($code);

                if (self::isFrench($text)) {
                    $found[] = (string) preg_replace(
                        ['/\{\{ (?:value|found) \}\}/', '/\{\{ \w+ \}\}/'],
                        ['%s', '%d'],
                        $text,
                    );
                }
            }
        }

        return $messages = array_values(array_unique($found));
    }

    private static function isFrench(string $text): bool
    {
        // A regular expression kept in a constant is not a message.
        if (1 === preg_match('~^([/#]).*\1[a-z]*$~s', $text)) {
            return false;
        }

        return 1 === preg_match('/[àâçéèêëîïôûùüœ’«]/iu', $text)
            || 1 === preg_match('/\b(?:le|la|les|des|de|du|une|vous|votre|vos|ce|cette|ces|pas|au|aux|est|sont)\b/iu', $text);
    }

    /** A PHP string literal as written in the source, back to its value. */
    private static function literal(string $code): string
    {
        $body = substr($code, 1, -1);

        return "'" === $code[0]
            ? strtr($body, ['\\\\' => '\\', "\\'" => "'"])
            : stripcslashes($body);
    }

    /** @return list<string> sprintf conversions (%s, %d, %1$s) and %name% parameters, in order */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%(?:\d+\$)?[sd]|%[a-z_]+%/', $text, $matches);

        return $matches[0];
    }

    /** @return array<string, string> */
    private static function catalogue(string $locale): array
    {
        $file = \dirname(__DIR__, 3).'/config/i18n/messages.'.$locale.'.php';
        self::assertFileExists($file);

        /** @var array<string, string> $entries */
        $entries = require $file;

        return $entries;
    }

    /** The entries as written in the file: a key written twice is silently merged by PHP. */
    private static function countEntries(string $locale): int
    {
        $tokens = token_get_all((string) file_get_contents(\dirname(__DIR__, 3).'/config/i18n/messages.'.$locale.'.php'));

        return \count(array_filter($tokens, static fn (mixed $token): bool => \is_array($token) && \T_DOUBLE_ARROW === $token[0]));
    }
}
