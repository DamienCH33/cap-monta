<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

final class ForgottenPasswordApiTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testAKnownAddressReceivesTheLink(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new User('proprio@example.com', 'Proprio'));
        $em->flush();

        $this->ask('Proprio@Example.com');

        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(0) ?? self::fail(), 'To', 'proprio@example.com');
    }

    public function testAnUnknownAddressGetsTheSameAnswerAndNoEmail(): void
    {
        $this->ask('inconnu@example.com');

        // Même réponse qu'avec un compte : rien ne révèle quelles adresses sont inscrites.
        self::assertResponseStatusCodeSame(202);
        self::assertSame('{"status":"check_your_inbox"}', (string) $this->client->getResponse()->getContent());
        // Et aucun email : le site n'écrit jamais à une adresse qui ne lui a rien demandé.
        self::assertEmailCount(0);
    }

    private function ask(string $email): void
    {
        $this->client->request(
            'POST',
            '/api/password/forgotten',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => $email]),
        );
    }
}
