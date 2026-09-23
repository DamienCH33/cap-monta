<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Service\ListingImport\ContactDetector;
use App\Service\ListingImport\ListingImporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Goes through the real OpenAI bridge with a fake HTTP answer: checks what is sent to
 * api.openai.com and that the answer, token counts included, comes back. Breaks if an upgrade
 * of symfony/ai changes either.
 */
final class ListingImporterOpenAiTest extends TestCase
{
    public function testTheRequestAndTheAnswerOfTheResponsesApi(): void
    {
        $sent = [];
        $answer = [
            'periods' => [['label' => 'septembre', 'start' => '2026-09-01', 'end' => '2026-10-01', 'prices' => [['amount' => 450, 'unit' => 'week']], 'minimumNights' => null, 'saturdayArrival' => false]],
            'unavailable' => [],
            'questions' => [],
            'listing' => ['type' => 'bungalow', 'capacity' => null, 'bedrooms' => 3, 'surface' => null, 'district' => null, 'amenities' => ['chauffage'], 'petsPolicy' => null, 'otherFeatures' => ['véranda']],
        ];

        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent, $answer): MockResponse {
            $sent = ['url' => $url, 'body' => json_decode((string) $options['body'], true, flags: \JSON_THROW_ON_ERROR)];

            return new MockResponse((string) json_encode([
                'id' => 'resp_1',
                'object' => 'response',
                'status' => 'completed',
                'model' => ListingImporter::DEFAULT_MODEL,
                'output' => [[
                    'type' => 'message', 'id' => 'msg_1', 'role' => 'assistant', 'status' => 'completed',
                    'content' => [['type' => 'output_text', 'text' => json_encode($answer), 'annotations' => []]],
                ]],
                'usage' => ['input_tokens' => 2100, 'output_tokens' => 310, 'total_tokens' => 2410],
            ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
        });

        $importer = new ListingImporter(
            Factory::createPlatform('sk-test', $http),
            new ContactDetector(),
            new NullLogger(),
            __DIR__.'/../../../../config/prompts/listing-import.md',
        );

        $result = $importer->import('Bungalow 3 chambres, septembre 450 € la semaine.', new \DateTimeImmutable('2026-09-05'), ['Médoc']);

        self::assertSame('https://api.openai.com/v1/responses', $sent['url']);
        self::assertSame(ListingImporter::DEFAULT_MODEL, $sent['body']['model']);
        self::assertSame('json_schema', $sent['body']['text']['format']['type']);
        self::assertTrue($sent['body']['text']['format']['strict']);
        self::assertSame(['Médoc', null], $sent['body']['text']['format']['schema']['properties']['listing']['properties']['district']['enum']);

        self::assertTrue($result->succeeded(), (string) $result->error);
        self::assertSame(2100, $result->inputTokens);
        self::assertSame(310, $result->outputTokens);
        self::assertSame($answer['periods'], $result->extraction?->toArray()['periods']);
    }
}
