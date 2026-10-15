<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ApiProblem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * A refused login, in the API's one error format and in the visitor's language (LocaleSubscriber):
 * 401 for wrong credentials — the same answer whether the account exists or not — and 429 once
 * the login limiters (LoginRateLimiter) block, instead of Symfony's English 401.
 */
final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return ApiProblem::response(
                Response::HTTP_TOO_MANY_REQUESTS,
                'Trop de tentatives de connexion : réessayez dans un quart d\'heure.',
            );
        }

        return ApiProblem::response(Response::HTTP_UNAUTHORIZED, 'Adresse email ou mot de passe incorrect.');
    }
}
