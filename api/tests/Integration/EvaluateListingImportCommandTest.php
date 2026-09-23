<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\ListingImport\ContactDetector;
use App\Service\ListingImport\ListingImporter;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The --run mode end to end, with a scripted model instead of Mistral: every example gets an
 * answer, the answers land in runs/, the report scores them.
 */
final class EvaluateListingImportCommandTest extends KernelTestCase
{
    private const string EVALS = __DIR__.'/../../evals/listing-import';

    /** @var list<string> */
    private array $runsBefore = [];

    protected function setUp(): void
    {
        $this->runsBefore = glob(self::EVALS.'/runs/*') ?: [];
    }

    protected function tearDown(): void
    {
        $created = array_diff(glob(self::EVALS.'/runs/*') ?: [], $this->runsBefore);
        new Filesystem()->remove($created);
        parent::tearDown();
    }

    public function testRunCallsTheModelOnEveryCaseAndScoresTheAnswers(): void
    {
        $kernel = self::bootKernel();
        $calls = 0;

        // Answers "no rate, nothing else" to every listing: right for example 03 only.
        $platform = new InMemoryPlatform(static function (Model $model, MessageBag $input) use (&$calls): string {
            ++$calls;

            return '{"periods": [], "unavailable": [], "questions": ["Quels sont vos tarifs ?"], "listing": null}';
        });
        self::getContainer()->set(ListingImporter::class, new ListingImporter(
            $platform, new ContactDetector(), new NullLogger(), __DIR__.'/../../config/prompts/listing-import.md',
        ));

        $tester = new CommandTester(new Application($kernel)->find('app:listing-import:eval'));
        $tester->execute(['--run' => true, '--cases' => self::EVALS.'/examples', '--model' => 'modele-test']);

        $tester->assertCommandIsSuccessful();
        self::assertSame(3, $calls);

        $runs = array_values(array_diff(glob(self::EVALS.'/runs/*') ?: [], $this->runsBefore));
        self::assertCount(1, $runs);
        self::assertStringEndsWith('-modele-test', $runs[0]);
        self::assertCount(3, glob($runs[0].'/*.json') ?: []);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Modèle modele-test — 3 cas', $display);
        self::assertMatchesRegularExpression('/Annonces réussies\s+1 \/ 3/', $display);
    }

    public function testARateLimitIsWaitedOutInsteadOfCountedAsAFailure(): void
    {
        $kernel = self::bootKernel();
        $calls = 0;

        // The free plan refuses the first call of each listing, then answers.
        $platform = new InMemoryPlatform(static function () use (&$calls): string {
            if (1 === ++$calls % 2) {
                throw new RateLimitExceededException(0);
            }

            return '{"periods": [], "unavailable": [], "questions": ["Quels sont vos tarifs ?"], "listing": null}';
        });
        self::getContainer()->set(ListingImporter::class, new ListingImporter(
            $platform, new ContactDetector(), new NullLogger(), __DIR__.'/../../config/prompts/listing-import.md',
        ));

        $tester = new CommandTester(new Application($kernel)->find('app:listing-import:eval'));
        $tester->execute(['--run' => true, '--cases' => self::EVALS.'/examples', '--case' => '03']);

        $tester->assertCommandIsSuccessful();
        self::assertSame(2, $calls);
        self::assertMatchesRegularExpression('/Annonces réussies\s+1 \/ 1/', $tester->getDisplay());
    }
}
