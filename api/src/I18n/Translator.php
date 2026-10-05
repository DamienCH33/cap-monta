<?php

declare(strict_types=1);

namespace App\I18n;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Traduction des textes de l'API, sans le composant Translation de Symfony : un catalogue par
 * langue (config/i18n/messages.<langue>.php), dont les clés sont les textes français eux-mêmes.
 *
 * - trans() : un texte du code, avec ses paramètres %nom% remplacés après traduction ;
 * - translateMessage() : un message déjà composé (exception, violation, sprintf). Il est cherché
 *   tel quel, puis parmi les clés qui contiennent %s ou %d, reconnues comme des modèles.
 * Un texte absent du catalogue reste en français : jamais de message vide.
 */
final class Translator
{
    /** @var array<string, array<string, string>> */
    private array $catalogues = [];

    /** @var array<string, list<array{string, string}>> modèle regex => traduction, par langue */
    private array $patterns = [];

    public function __construct(
        private readonly RequestStack $requests,
        #[Autowire('%kernel.project_dir%/config/i18n')] private readonly string $directory,
    ) {
    }

    /** La langue de la requête en cours, posée par LocaleSubscriber ; le français sinon. */
    public function locale(): string
    {
        return Locales::normalize($this->requests->getCurrentRequest()?->getLocale());
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    public function trans(string $text, array $parameters = [], ?string $locale = null): string
    {
        $locale = Locales::normalize($locale ?? $this->locale());
        $translated = Locales::SOURCE === $locale ? $text : ($this->catalogue($locale)[$text] ?? $text);

        return strtr($translated, array_map(static fn (string|int|float $value): string => (string) $value, $parameters));
    }

    /** Un message déjà composé, ligne par ligne (les violations sont jointes par des retours à la ligne). */
    public function translateMessage(string $message, ?string $locale = null): string
    {
        $locale = Locales::normalize($locale ?? $this->locale());

        if (Locales::SOURCE === $locale || '' === trim($message)) {
            return $message;
        }

        return implode("\n", array_map(fn (string $line): string => $this->translateLine($line, $locale), explode("\n", $message)));
    }

    private function translateLine(string $line, string $locale): string
    {
        $catalogue = $this->catalogue($locale);
        $trimmed = trim($line);

        if (isset($catalogue[$trimmed])) {
            return str_replace($trimmed, $catalogue[$trimmed], $line);
        }

        foreach ($this->patterns($locale) as [$regex, $translation]) {
            if (1 === preg_match($regex, $trimmed, $matches)) {
                array_shift($matches);
                $index = 0;
                $result = (string) preg_replace_callback('/%(?:(\d+)\$)?[sd]/', static function (array $spec) use ($matches, &$index): string {
                    $position = '' !== ($spec[1] ?? '') ? (int) $spec[1] - 1 : $index++;

                    return $matches[$position] ?? '';
                }, $translation);

                return str_replace($trimmed, $result, $line);
            }
        }

        return $line;
    }

    /** @return array<string, string> */
    private function catalogue(string $locale): array
    {
        if (!isset($this->catalogues[$locale])) {
            $file = $this->directory.'/messages.'.$locale.'.php';
            /** @var array<string, string> $entries */
            $entries = is_file($file) ? require $file : [];
            $this->catalogues[$locale] = $entries;
        }

        return $this->catalogues[$locale];
    }

    /** @return list<array{string, string}> */
    private function patterns(string $locale): array
    {
        if (!isset($this->patterns[$locale])) {
            $this->patterns[$locale] = [];

            foreach ($this->catalogue($locale) as $source => $translation) {
                if (1 !== preg_match('/%(?:\d+\$)?[sd]/', $source)) {
                    continue;
                }

                $regex = preg_replace_callback(
                    '/%(?:\d+\$)?([sd])|[^%]+|%/',
                    static fn (array $part): string => match ($part[1] ?? null) {
                        's' => '(.+?)',
                        'd' => '(-?\d+)',
                        default => preg_quote($part[0], '/'),
                    },
                    $source,
                );
                $this->patterns[$locale][] = ['/^'.$regex.'$/u', $translation];
            }
        }

        return $this->patterns[$locale];
    }
}
