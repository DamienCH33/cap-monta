<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Cible du lien reçu par email. L'API valide, puis renvoie le visiteur
 * sur le front : il a cliqué depuis sa messagerie, il doit atterrir sur le site.
 */
final class VerifyEmailController
{
    public function __construct(
        private readonly UriSigner $signer,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        #[Autowire('%app.front_url%')] private readonly string $frontUrl,
    ) {
    }

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['GET'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->signer->checkRequest($request)) {
            return new RedirectResponse($this->frontUrl.'/connexion?verification=lien-invalide');
        }

        $id = $request->query->get('id');
        $user = is_string($id) && Uuid::isValid($id) ? $this->users->find(Uuid::fromString($id)) : null;

        if (null === $user) {
            return new RedirectResponse($this->frontUrl.'/connexion?verification=lien-invalide');
        }

        $user->verifyEmail($this->clock->now());
        $this->em->flush();

        return new RedirectResponse($this->frontUrl.'/connexion?verification=ok');
    }
}
