<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\LoginFailureHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final class LoginFailureHandlerTest extends TestCase
{
    public function testBlockedLoginsGetATooManyRequestsAnswer(): void
    {
        $response = (new LoginFailureHandler())->onAuthenticationFailure(new Request(), new TooManyLoginAttemptsAuthenticationException(15));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('Trop de tentatives', (string) $response->getContent());
    }

    public function testWrongCredentialsGetTheSameAnswerWhateverTheReason(): void
    {
        $response = (new LoginFailureHandler())->onAuthenticationFailure(new Request(), new BadCredentialsException());

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('mot de passe incorrect', (string) $response->getContent());
    }
}
