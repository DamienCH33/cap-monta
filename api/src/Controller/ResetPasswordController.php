<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ResetPasswordRequest;
use App\Repository\UserRepository;
use App\Service\Account\AccountMailer;
use App\Service\Http\FloodGuard;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class ResetPasswordController
{
    private const INVALID = 'Ce lien n\'est plus valable. Demandez-en un nouveau.';

    public function __construct(
        private readonly UriSigner $signer,
        private readonly UrlGeneratorInterface $urls,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ClockInterface $clock,
        private readonly FloodGuard $floodGuard,
        #[Target('password_resets')]
        private readonly RateLimiterFactoryInterface $passwordResetsLimiter,
    ) {
    }

    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ResetPasswordRequest $payload): JsonResponse
    {
        $this->floodGuard->check($this->passwordResetsLimiter);

        // On reconstruit l'URL exactement telle qu'elle a été signée,
        // puis on laisse UriSigner juger de la signature et de l'expiration.
        $base = $this->urls->generate('api_password_reset', [], UrlGeneratorInterface::ABSOLUTE_URL);

        if (!$this->signer->check($base.'?'.$payload->jeton)) {
            throw new BadRequestHttpException(self::INVALID);
        }

        parse_str($payload->jeton, $params);

        $id = $params['id'] ?? null;
        $fingerprint = $params['v'] ?? null;
        $user = is_string($id) && Uuid::isValid($id) ? $this->users->find(Uuid::fromString($id)) : null;

        // hash_equals compare en temps constant : une comparaison naïve laisse
        // fuir, par sa durée, le nombre de caractères devinés.
        if (
            null === $user
            || !is_string($fingerprint)
            || !hash_equals(AccountMailer::fingerprint($user), $fingerprint)
        ) {
            throw new BadRequestHttpException(self::INVALID);
        }

        $user->setPassword($this->hasher->hashPassword($user, $payload->password));

        // Avoir cliqué ce lien prouve l'accès à la boîte mail : autant valider
        // l'adresse au passage si elle ne l'était pas encore.
        $user->verifyEmail($this->clock->now());

        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
