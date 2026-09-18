<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

final class RegistrationApiTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testANewOwnerReceivesAVerificationLink(): void
    {
        $this->register('nouveau@example.com');

        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        self::assertSame('Confirmez votre adresse — Cap Monta', $this->lastEmail()->getSubject());

        $user = $this->findUser('nouveau@example.com');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified(), 'Le compte ne doit pas être vérifié avant le clic.');
    }

    public function testAnAlreadyRegisteredAddressGetsTheExactSameAnswer(): void
    {
        $this->em->persist(new User('deja@example.com', 'Deja La'));
        $this->em->flush();

        $this->register('deja@example.com');

        // Même code, même corps : rien ne distingue les deux cas de l'extérieur.
        self::assertResponseStatusCodeSame(202);

        // Seul l'email diffère, et aucun second compte n'a été créé.
        self::assertSame('Votre compte Cap Monta', $this->lastEmail()->getSubject());
        self::assertCount(1, $this->users()->findBy(['email' => 'deja@example.com']));
    }

    public function testAnInvalidPayloadIsRefusedAndSendsNothing(): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'pas-un-email',
                'displayName' => 'X',
                'password' => 'court',
            ]),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testTheSignedLinkVerifiesTheAccount(): void
    {
        $this->register('a-verifier@example.com');

        $this->client->request('GET', $this->verificationLinkFrom($this->lastEmail()));

        self::assertResponseRedirects();
        self::assertTrue($this->reload('a-verifier@example.com')->isVerified());
    }

    public function testATamperedLinkVerifiesNothing(): void
    {
        $this->register('intact@example.com');

        $tampered = str_replace('_hash=', '_hash=zz', $this->verificationLinkFrom($this->lastEmail()));
        $this->client->request('GET', $tampered);

        self::assertResponseRedirects();
        self::assertStringContainsString(
            'lien-invalide',
            (string) $this->client->getResponse()->headers->get('Location'),
        );
        self::assertFalse($this->reload('intact@example.com')->isVerified());
    }

    private function register(string $email): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => $email,
                'displayName' => 'Proprietaire test',
                'password' => 'chevalpileagrafe',
            ]),
        );
    }

    private function lastEmail(): Email
    {
        $message = self::getMailerMessage();

        if (!$message instanceof Email) {
            self::fail('Aucun email n\'a été envoyé.');
        }

        return $message;
    }

    private function verificationLinkFrom(Email $email): string
    {
        if (1 !== preg_match('#https?://\S+/api/verify-email\S*#', (string) $email->getTextBody(), $matches)) {
            self::fail('Le lien de vérification est absent du message.');
        }

        return $matches[0];
    }

    private function reload(string $email): User
    {
        $this->em->clear();
        $user = $this->findUser($email);

        if (null === $user) {
            self::fail(sprintf('Aucun compte pour %s.', $email));
        }

        return $user;
    }

    private function findUser(string $email): ?User
    {
        return $this->users()->findOneBy(['email' => $email]);
    }

    private function users(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }
}
