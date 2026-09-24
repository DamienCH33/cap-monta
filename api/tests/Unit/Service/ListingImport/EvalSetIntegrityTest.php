<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Service\ListingImport\Evaluation\EvalCase;
use App\Service\ListingImport\Evaluation\EvalCaseLoader;
use App\Service\ListingImport\Evaluation\ExtractionScorer;
use App\Service\ListingImport\Evaluation\SourceAmounts;
use App\Service\ListingImport\ExtractionRules;
use App\Service\ListingImport\ListingExtraction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The hand-written answers obey the rules the agent is held to. The committed examples are
 * always checked; the real listings (git-ignored) only on a machine that has them.
 */
final class EvalSetIntegrityTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../../../evals/listing-import/';

    #[DataProvider('sets')]
    public function testEveryExpectedAnswerIsConsistent(string $directory): void
    {
        if (!is_dir($directory)) {
            self::markTestSkipped('Jeu absent sur cette machine : '.$directory);
        }

        $cases = new EvalCaseLoader()->load($directory);
        $ids = array_map(static fn (EvalCase $case): string => $case->id, $cases);

        self::assertSame(array_unique($ids), $ids, 'ids are unique');

        foreach ($cases as $case) {
            self::assertSame(
                [],
                SourceAmounts::invented($case->expected->amounts(), $case->text),
                $case->id.' : every expected amount is written in the text',
            );

            self::assertSame([], $case->expected->listing->rejected ?? [], $case->id.' : expected listing values are valid');

            if ([] !== ExtractionRules::questions($case->expected)) {
                self::assertTrue($case->needsClarification, $case->id.' : the PHP rules will ask a question, the answer must expect one');
            }

            $asItself = new ListingExtraction(
                $case->expected->periods,
                $case->expected->unavailable,
                $case->needsClarification ? ['?'] : [],
                $case->expected->listing,
            );
            self::assertTrue(new ExtractionScorer()->score($case, $asItself)->passed(), $case->id);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sets(): iterable
    {
        yield 'committed examples' => [self::ROOT.'examples'];
        yield 'real listings' => [self::ROOT.'cases'];
        yield 'control set' => [self::ROOT.'holdout'];
    }

    public function testEachCaseFileIsNamedAfterItsId(): void
    {
        foreach (glob(self::ROOT.'{examples,cases,holdout}/*.json', \GLOB_BRACE) ?: [] as $file) {
            $data = new EvalCaseLoader()->decode($file);
            self::assertSame(basename($file, '.json'), $data['id'] ?? null, $file);
        }
    }
}
