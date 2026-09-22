<?php

declare(strict_types=1);

namespace App\Service\Mail;

/**
 * One piece of an HTML email, in reading order: what the layout template knows how to draw.
 */
final readonly class MailBlock
{
    /**
     * @param list<array{string, string}> $rows
     */
    private function __construct(
        public string $type,
        /** Already escaped: the paragraph's links are made by MailComposer. */
        public string $html = '',
        public string $text = '',
        public string $url = '',
        public array $rows = [],
    ) {
    }

    public static function paragraph(string $html): self
    {
        return new self('paragraph', html: $html);
    }

    public static function button(string $url, string $label): self
    {
        return new self('button', text: $label, url: $url);
    }

    /**
     * @param list<array{string, string}> $rows
     */
    public static function facts(array $rows): self
    {
        return new self('facts', rows: $rows);
    }

    public static function quote(string $text): self
    {
        return new self('quote', text: $text);
    }
}
