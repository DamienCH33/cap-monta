<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use App\I18n\Translator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class TranslatorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/cm-i18n-'.uniqid();
        mkdir($this->directory);
        file_put_contents($this->directory.'/messages.en.php', <<<'PHP'
            <?php return [
                'Ajoutez au moins une photo.' => 'Add at least one photo.',
                '%d photos maximum par logement : supprimez-en une pour en ajouter une autre.' => 'Up to %d photos per home: delete one to add another.',
                'Bonjour %name%,' => 'Hello %name%,',
            ];
            PHP);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testFrenchIsTheSourceAndPassesUntouched(): void
    {
        self::assertSame('Ajoutez au moins une photo.', $this->translator('fr')->translateMessage('Ajoutez au moins une photo.'));
    }

    public function testAComposedMessageIsTranslatedLineByLine(): void
    {
        self::assertSame(
            "Add at least one photo.\nUp to 12 photos per home: delete one to add another.\nUn texte inconnu reste tel quel.",
            $this->translator('en')->translateMessage("Ajoutez au moins une photo.\n12 photos maximum par logement : supprimez-en une pour en ajouter une autre.\nUn texte inconnu reste tel quel."),
        );
    }

    public function testParametersAreReplacedAfterTranslation(): void
    {
        self::assertSame('Hello Anna,', $this->translator('fr')->trans('Bonjour %name%,', ['%name%' => 'Anna'], 'en'));
        self::assertSame('Bonjour Anna,', $this->translator('en')->trans('Bonjour %name%,', ['%name%' => 'Anna'], 'fr'));
    }

    public function testAnUnsupportedLanguageFallsBackToFrench(): void
    {
        self::assertSame('Ajoutez au moins une photo.', $this->translator('es')->translateMessage('Ajoutez au moins une photo.'));
    }

    private function translator(string $locale): Translator
    {
        $request = new Request();
        $request->setLocale($locale);
        $stack = new RequestStack();
        $stack->push($request);

        return new Translator($stack, $this->directory);
    }
}
