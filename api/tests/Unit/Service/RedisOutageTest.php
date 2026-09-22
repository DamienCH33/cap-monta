<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Accommodation;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Repository\UnavailabilityRepository;
use App\Service\Calendar\PublicCalendar;
use App\Service\Http\FloodGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Redis is a speed-up and a guard, not a dependency: with Redis down, the site must work.
 */
final class RedisOutageTest extends TestCase
{
    public function testTheFloodGuardLetsRequestsThroughWhenItsStorageIsDown(): void
    {
        $limiter = $this->createStub(RateLimiterFactoryInterface::class);
        $limiter->method('create')->willThrowException(new InvalidArgumentException('Redis connection failed: Connection refused'));

        $requests = new RequestStack();
        $requests->push(Request::create('/api/register', 'POST'));

        (new FloodGuard($requests, new NullLogger()))->check($limiter);

        $this->addToAssertionCount(1); // No exception: the request goes on.
    }

    public function testThePublicCalendarIsReadFromTheDatabaseWhenTheCacheIsDown(): void
    {
        $home = new Accommodation('bungalow', Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test', new User('a@example.com', 'Owner'));

        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('get')->willThrowException(new InvalidArgumentException('Redis connection failed'));
        $cache->method('invalidateTags')->willThrowException(new InvalidArgumentException('Redis connection failed'));

        $repository = $this->createMock(UnavailabilityRepository::class);
        $repository->expects(self::once())->method('findForPeriod')->willReturn([]);

        $calendar = new PublicCalendar($repository, $cache, new NullLogger());

        self::assertSame([], $calendar->busyPeriods($home, new \DateTimeImmutable('today'), new \DateTimeImmutable('+1 year')));
        $calendar->invalidate($home); // Logged, not thrown.
    }
}
