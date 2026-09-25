<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
        parent::__construct($registry, User::class);
    }

    /**
     * Used by the login. The address is stored in lower case (Registrar): « Damien@Example.com »
     * must find the same account.
     *
     * An unknown address costs a password hash too. Without it, the login answered in 25 ms for
     * an unknown address and 500 ms for a known one: anyone could list who has an account.
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        $user = $this->findOneBy(['email' => mb_strtolower(trim($identifier))]);

        if (null === $user) {
            $this->hashers->getPasswordHasher(User::class)->hash('timing-equaliser');
        }

        return $user;
    }
}
