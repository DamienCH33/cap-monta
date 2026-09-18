<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Dto\RegistrationRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class Registrar
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private AccountMailer $mailer,
    ) {
    }

    /**
     * Ne dit jamais à l'appelant ce qui s'est passé : c'est la boîte mail
     * du titulaire de l'adresse qui reçoit l'information, pas la réponse HTTP.
     */
    public function register(RegistrationRequest $request): void
    {
        // Une adresse email n'est pas sensible à la casse : sans ça,
        // Damien@example.com et damien@example.com deviennent deux comptes.
        $email = mb_strtolower(trim($request->email));

        if (null !== $this->users->findOneBy(['email' => $email])) {
            $this->mailer->sendAlreadyRegistered($email);

            return;
        }

        $user = new User($email, trim($request->displayName));
        $user->setPassword($this->hasher->hashPassword($user, $request->password));

        $this->em->persist($user);
        $this->em->flush();

        $this->mailer->sendVerificationLink($user);
    }
}
