<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OwnerProfileApiTest extends WebTestCase
{
    private const PASSWORD = 'mot-de-passe-solide';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $alice;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        (new ORMPurger($this->em))->purge();

        $this->alice = new User('alice@example.com', 'Alice');
        $this->alice->verifyEmail(new \DateTimeImmutable());
        $this->alice->setPassword($this->hasher()->hashPassword($this->alice, self::PASSWORD));
        $this->em->persist($this->alice);
        $this->em->flush();
    }

    public function testAnAnonymousVisitorCannotChangeAProfile(): void
    {
        $this->send('PATCH', '/api/owner/me', ['displayName' => 'Pirate']);
        self::assertResponseStatusCodeSame(401);

        $this->send('POST', '/api/owner/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'nouveau-mot-de-passe']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheOwnerUpdatesHisNameAndPhone(): void
    {
        $this->client->loginUser($this->alice, 'main');

        $this->send('PATCH', '/api/owner/me', ['displayName' => '  Alice Martin ', 'phone' => '06 12 34 56 78']);

        self::assertResponseIsSuccessful();
        self::assertSame('Alice Martin', $this->json()['displayName']);
        self::assertSame('06 12 34 56 78', $this->json()['phone']);

        // An empty phone removes it.
        $this->send('PATCH', '/api/owner/me', ['displayName' => 'Alice Martin', 'phone' => '']);
        self::assertNull($this->json()['phone']);
    }

    public function testAnInvalidProfileIsRefusedWithTheFieldToFix(): void
    {
        $this->client->loginUser($this->alice, 'main');

        $this->send('PATCH', '/api/owner/me', ['displayName' => 'A', 'phone' => '12']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($this->json()['violations'], 'propertyPath');
        self::assertContains('displayName', $fields);
        self::assertContains('phone', $fields);
    }

    public function testTheOwnerChangesHisPasswordOnlyWithTheCurrentOne(): void
    {
        $this->client->loginUser($this->alice, 'main');

        $this->send('POST', '/api/owner/me/password', ['currentPassword' => 'pas-le-bon-mot-de-passe', 'newPassword' => 'nouveau-mot-de-passe']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('currentPassword', $this->json()['violations'][0]['propertyPath']);

        $this->send('POST', '/api/owner/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'court']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('newPassword', $this->json()['violations'][0]['propertyPath']);

        $this->send('POST', '/api/owner/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'nouveau-mot-de-passe']);
        self::assertResponseStatusCodeSame(204);

        $this->em->clear();
        $alice = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        self::assertInstanceOf(User::class, $alice);
        self::assertTrue($this->hasher()->isPasswordValid($alice, 'nouveau-mot-de-passe'));
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        return $hasher;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function send(string $method, string $path, array $body): void
    {
        $this->client->request($method, $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
