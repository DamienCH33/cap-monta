<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Enum\HeardFrom;
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

    public function testTheOwnerMaySayHowHeHeardOfTheSite(): void
    {
        $this->register('facebook@example.com', extra: ['heardFrom' => 'facebook_group']);
        self::assertResponseStatusCodeSame(202);
        self::assertSame(HeardFrom::FacebookGroup, $this->findUser('facebook@example.com')?->getHeardFrom());

        $this->register('muet@example.com', extra: ['heardFrom' => null]);
        self::assertResponseStatusCodeSame(202);
        self::assertNull($this->findUser('muet@example.com')?->getHeardFrom());

        $this->register('tiktok@example.com', extra: ['heardFrom' => 'tiktok']);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->findUser('tiktok@example.com'));
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

    public function testAnOwnerSigningUpInEnglishGetsHisEmailsInEnglish(): void
    {
        $this->register('english@example.com', 'en-GB,en;q=0.8');

        $email = $this->lastEmail();
        self::assertSame('Confirm your email address — Cap Monta', $email->getSubject());
        self::assertEmailTextBodyContains($email, 'Hello Proprietaire test,');
        self::assertEmailTextBodyContains($email, "This link is valid for 24 hours.\nIf you did not sign up, please ignore this message.");
        self::assertEmailHtmlBodyContains($email, '<html lang="en">');
        self::assertEmailHtmlBodyContains($email, '>Confirm my address</a>');

        // The link lands on the English login page.
        $this->client->request('GET', $this->verificationLinkFrom($email));
        self::assertResponseRedirects();
        self::assertStringEndsWith('/en/connexion?verification=ok', (string) $this->client->getResponse()->headers->get('Location'));

        // A second sign-up with the same address: the account's language, whatever the page.
        $this->register('english@example.com');
        self::assertSame('Your Cap Monta account', $this->lastEmail()->getSubject());
        self::assertMatchesRegularExpression('#^http://\S+/en/connexion$#m', (string) $this->lastEmail()->getTextBody());
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

    /**
     * The first email can be lost (spam folder, mail outage on launch day): a connected owner
     * asks for a new link, and that link verifies the account.
     */
    public function testAConnectedOwnerCanAskForANewLinkThatWorks(): void
    {
        $this->register('perdu@example.com');
        $this->client->loginUser($this->reload('perdu@example.com'), 'main');

        $this->client->request('POST', '/api/owner/me/verification');

        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        $this->client->request('GET', $this->verificationLinkFrom($this->lastEmail()));
        self::assertTrue($this->reload('perdu@example.com')->isVerified());
    }

    public function testNoLinkIsSentOnceTheAddressIsConfirmed(): void
    {
        $owner = new User('confirme@example.com', 'Deja Confirme');
        $owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($owner);
        $this->em->flush();
        $this->client->loginUser($owner, 'main');

        $this->client->request('POST', '/api/owner/me/verification');

        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(0);
    }

    public function testAnAnonymousVisitorCannotAskForALink(): void
    {
        $this->client->request('POST', '/api/owner/me/verification');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * The display name is repeated in « Bonjour … » of the emails sent to the address given at
     * sign-up. With a line break and a link, it became a button in a genuine Cap Monta email
     * sent to someone else's address (audit of 25/09).
     */
    public function testTheDisplayNameCannotCarryALink(): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'victime@example.com',
                'displayName' => "Audit\nhttps://evil.example/connexion",
                'password' => 'chevalpileagrafe',
            ]),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    /**
     * @param array<string, string|null> $extra
     */
    private function register(string $email, string $language = 'fr', array $extra = []): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT_LANGUAGE' => $language],
            content: (string) json_encode([
                'email' => $email,
                'displayName' => 'Proprietaire test',
                'password' => 'chevalpileagrafe',
            ] + $extra),
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
