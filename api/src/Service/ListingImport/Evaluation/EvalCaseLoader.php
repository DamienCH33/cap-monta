<?php

declare(strict_types=1);

namespace App\Service\ListingImport\Evaluation;

use App\Service\ListingImport\InvalidExtractionException;

/**
 * Reads a folder of JSON files, one case per file, sorted by name.
 */
final class EvalCaseLoader
{
    /**
     * @return list<EvalCase>
     */
    public function load(string $directory): array
    {
        $files = glob(rtrim($directory, '/').'/*.json');

        if (false === $files || [] === $files) {
            throw new \RuntimeException(\sprintf('Aucun cas trouvé dans %s.', $directory));
        }

        sort($files);

        return array_map(function (string $file): EvalCase {
            try {
                return EvalCase::fromArray($this->decode($file));
            } catch (InvalidExtractionException $e) {
                throw new InvalidExtractionException(\sprintf('%s : %s', basename($file), $e->getMessage()), previous: $e);
            }
        }, $files);
    }

    /**
     * @return array<mixed>
     */
    public function decode(string $file): array
    {
        $content = file_get_contents($file);

        if (false === $content) {
            throw new \RuntimeException(\sprintf('Lecture impossible : %s.', $file));
        }

        try {
            $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidExtractionException(\sprintf('JSON invalide : %s.', $e->getMessage()), previous: $e);
        }

        if (!\is_array($data)) {
            throw new InvalidExtractionException('Le fichier doit contenir un objet JSON.');
        }

        return $data;
    }
}
