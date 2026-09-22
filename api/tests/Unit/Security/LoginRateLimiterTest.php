<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\LoginRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginRateLimiterTest extends TestCase
{
    public function testChangingIpDoesNotResetTheCounterOfAnAccount(): void
    {
        $limiter = $this->limiter();

        // 20 attempts on one account, each from a different address: all counted.
        for ($i = 1; $i <= 20; ++$i) {
            self::assertTrue($limiter->consume($this->loginFrom('alice@example.com', '10.0.0.'.$i))->isAccepted());
        }

        self::assertFalse($limiter->consume($this->loginFrom('Alice@Example.com', '10.0.1.1'))->isAccepted());
        // Another account is not affected.
        self::assertTrue($limiter->consume($this->loginFrom('bob@example.com', '10.0.1.1'))->isAccepted());
    }

    public function testFiveMistakesFromOneMachineBlockThatPair(): void
    {
        $limiter = $this->limiter();

        for ($i = 1; $i <= 5; ++$i) {
            $limiter->consume($this->loginFrom('alice@example.com', '10.0.0.1'));
        }

        self::assertFalse($limiter->consume($this->loginFrom('alice@example.com', '10.0.0.1'))->isAccepted());
    }

    private function limiter(): LoginRateLimiter
    {
        $factory = static fn (string $id, int $limit, string $interval): RateLimiterFactory => new RateLimiterFactory(
            ['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => $interval],
            new InMemoryStorage(),
        );

        return new LoginRateLimiter(
            $factory('email_ip', 5, '15 minutes'),
            $factory('ip', 50, '15 minutes'),
            $factory('email', 20, '1 hour'),
            'test-secret',
        );
    }

    private function loginFrom(string $email, string $ip): Request
    {
        $request = Request::create('/api/login', 'POST', server: ['REMOTE_ADDR' => $ip]);
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return $request;
    }
}
