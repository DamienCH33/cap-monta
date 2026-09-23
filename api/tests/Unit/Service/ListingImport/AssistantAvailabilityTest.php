<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Enum\ListingImportFailure;
use App\Service\ListingImport\AssistantAvailability;
use App\Service\ListingImport\InvalidExtractionException;
use App\Service\ListingImport\ListingImporter;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;

final class AssistantAvailabilityTest extends TestCase
{
    public function testNoKeyMeansNoAssistant(): void
    {
        $availability = new AssistantAvailability(new ArrayAdapter(), new MockClock(), '  ');

        self::assertFalse($availability->isEnabled());
        self::assertFalse($availability->isAvailable());
    }

    public function testAPauseEndsOnItsOwnAndIsNeverShortened(): void
    {
        $clock = new MockClock('2026-09-23 10:00:00');
        $availability = new AssistantAvailability(new ArrayAdapter(), $clock, 'cle');

        $availability->pauseAfter(ListingImportFailure::Configuration); // 1 hour
        $availability->pauseAfter(ListingImportFailure::Unavailable); // 5 minutes: ignored
        self::assertEquals($clock->now()->modify('+1 hour'), $availability->pausedUntil());

        $clock->sleep(3601);
        self::assertTrue($availability->isAvailable());
    }

    public function testAnUnreadableAnswerDoesNotPauseAnyone(): void
    {
        $availability = new AssistantAvailability(new ArrayAdapter(), new MockClock(), 'cle');

        $availability->pauseAfter(ListingImportFailure::Unreadable);

        self::assertTrue($availability->isAvailable());
    }

    public function testEachProviderErrorMeansSomethingForTheNextAttempt(): void
    {
        self::assertSame(ListingImportFailure::ProviderLimit, ListingImporter::classify(new RateLimitExceededException(30)));
        self::assertSame(ListingImportFailure::Configuration, ListingImporter::classify(new AuthenticationException('clé')));
        self::assertSame(ListingImportFailure::Unavailable, ListingImporter::classify(new ServerException(502, 'bad gateway')));
        self::assertSame(ListingImportFailure::Unavailable, ListingImporter::classify(new TransportException('timeout')));
        self::assertSame(ListingImportFailure::Unreadable, ListingImporter::classify(new InvalidExtractionException('forme')));
        self::assertFalse(ListingImportFailure::Configuration->isWorthRetrying());
    }
}
