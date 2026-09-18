<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationApiTest extends ApiTestCase
{
    private const EMAIL = 'owner@example.com';
    private const PASSWORD = 'un-mot-de-passe';
    private const NAME = 'Proprietaire test';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $user = new User(self::EMAIL, self::NAME);
        $user->setPassword(
            self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD),
        );

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();
    }

    public function testAnOwnerLogsInAndGetsTheirProfile(): void
    {
        $payload = $this->login(self::PASSWORD);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::EMAIL, $payload['email'] ?? null);
        self::assertSame(self::NAME, $payload['displayName'] ?? null);

        // Le jour où quelqu'un renvoie l'entité au lieu du DTO, ce test tombe.
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('roles', $payload);
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->login('faux');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheOwnerAreaIsClosedToAnonymousVisitors(): void
    {
        $this->client->request('GET', '/api/owner/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoggingOutClosesTheSessionServerSide(): void
    {
        $this->login(self::PASSWORD);

        $this->client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/owner/me');
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function login(string $password): array
    {
        $this->client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => self::EMAIL, 'password' => $password]),
        );

        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
