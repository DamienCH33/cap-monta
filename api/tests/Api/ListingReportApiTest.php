<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\ListingReport;
use App\Entity\Photo;
use App\Entity\User;
use App\Enum\AccommodationStatus;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

final class ListingReportApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        (new ORMPurger($this->em))->purge();

        $owner = new User('alice@example.com', 'Alice');
        $owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($owner);

        foreach (['en-ligne' => true, 'brouillon' => false] as $slug => $published) {
            $home = new Accommodation($slug, Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation with a description long enough to be published.', $owner);
            $home->addPhoto(new Photo($home, 1600, 1066));
            if ($published) {
                $home->publish();
            }
            $this->em->persist($home);
        }

        $this->em->flush();
    }

    public function testAVisitorReportsAListingAndTheModerationIsWarned(): void
    {
        $this->report('en-ligne', ['reason' => 'people_visible', 'message' => "On me voit sur la photo 3.\n\nhttps://evil.example/x", 'email' => 'Temoin@Example.com']);

        self::assertResponseStatusCodeSame(202);

        $report = $this->em->getRepository(ListingReport::class)->findOneBy([]);
        self::assertInstanceOf(ListingReport::class, $report);
        self::assertSame('temoin@example.com', $report->getReporterEmail());

        $mail = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $mail);
        self::assertEmailAddressContains($mail, 'To', 'moderation@example.com');
        self::assertStringStartsWith('URGENT', (string) $mail->getSubject());
        self::assertStringNotContainsString('href="https://evil.example', (string) $mail->getHtmlBody());
    }

    public function testOnlyAPublishedListingCanBeReported(): void
    {
        $this->report('brouillon', ['reason' => 'scam']);
        self::assertResponseStatusCodeSame(404);

        $this->report('inconnu', ['reason' => 'scam']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAReportNeedsAKnownReason(): void
    {
        $this->report('en-ligne', ['reason' => 'je-sais-pas', 'message' => str_repeat('a', 1001)]);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($this->json()['violations'], 'propertyPath');
        self::assertContains('reason', $fields);
        self::assertContains('message', $fields);
    }

    public function testTheSuspendCommandTakesTheListingOffAndTellsTheOwner(): void
    {
        $this->report('en-ligne', ['reason' => 'misleading']);

        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:accommodation:suspend'));
        $tester->execute(['slug' => 'en-ligne', '--reason' => 'photos d’un autre logement']);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        $home = $this->em->getRepository(Accommodation::class)->findOneBy(['slug' => 'en-ligne']);
        self::assertInstanceOf(Accommodation::class, $home);
        self::assertSame(AccommodationStatus::Archived, $home->getStatus());
        self::assertNotNull($this->em->getRepository(ListingReport::class)->findOneBy([])?->getHandledAt());

        $toOwner = array_values(array_filter(
            self::getMailerMessages(),
            static fn ($message): bool => $message instanceof Email && 'alice@example.com' === $message->getTo()[0]->getAddress(),
        ))[0] ?? null;
        self::assertInstanceOf(Email::class, $toOwner);
        self::assertEmailAddressContains($toOwner, 'To', 'alice@example.com');
        self::assertEmailTextBodyContains($toOwner, 'photos d’un autre logement');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function report(string $slug, array $body): void
    {
        $this->client->request('POST', '/api/accommodations/'.$slug.'/reports', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($body, \JSON_THROW_ON_ERROR));
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
