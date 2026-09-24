<?php

declare(strict_types=1);

namespace App\Service\ListingImport\Evaluation;

use App\Service\ListingImport\MonthLabel;

/**
 * The listings of a control set (holdout) that are also in the main set. The main set is the one
 * the rules were tuned on: a listing seen there proves nothing.
 *
 * Same address, or close texts: a listing is often republished under another address with a few
 * words changed (24/09: "écureuil 391" was in both sets). Two texts are the same listing when most
 * of their words are shared.
 */
final class EvalSetOverlap
{
    /**
     * Share of words (Jaccard) above which two texts are the same listing. Measured on 24/09: two
     * listings of the same owner, written from the same template, share 35 % at most; a listing
     * republished with a price and two lines changed, 96 %.
     */
    public const float SAME_LISTING = 0.6;

    /**
     * @return array<string, string> file of the control set => explanation
     */
    public static function shared(string $control, string $main): array
    {
        $inMain = self::fingerprints($main);
        $shared = [];

        foreach (self::fingerprints($control) as $file => [$source, $words]) {
            foreach ($inMain as $mainFile => [$mainSource, $mainWords]) {
                $similarity = self::similarity($words, $mainWords);
                if ((null !== $source && $source === $mainSource) || $similarity >= self::SAME_LISTING) {
                    $shared[$file] = \sprintf('%s = %s (%d %% de mots communs)', $file, $mainFile, (int) round($similarity * 100));

                    continue 2;
                }
            }
        }

        return $shared;
    }

    /**
     * Words in common over words in either text.
     *
     * @param array<string, true> $a
     * @param array<string, true> $b
     */
    public static function similarity(array $a, array $b): float
    {
        $union = \count($a + $b);

        return 0 === $union ? 0.0 : \count(array_intersect_key($a, $b)) / $union;
    }

    /**
     * @return array<string, true>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[a-z0-9]{4,}/', MonthLabel::fold($text), $words);

        return array_fill_keys($words[0], true);
    }

    /**
     * @return array<string, array{0: string|null, 1: array<string, true>}> file => [source address, set of words]
     */
    private static function fingerprints(string $directory): array
    {
        $found = [];
        foreach (glob($directory.'/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!\is_array($data) || !\is_string($data['text'] ?? null)) {
                continue;
            }
            $source = \is_string($data['source'] ?? null) && str_starts_with($data['source'], 'http') ? rtrim($data['source'], '/') : null;
            $found[basename($file)] = [$source, self::words($data['text'])];
        }

        return $found;
    }
}
