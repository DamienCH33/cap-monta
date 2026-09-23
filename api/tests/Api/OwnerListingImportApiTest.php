<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\ListingImport;
use App\Entity\User;
use App\Message\RunListingImport;
use App\MessageHandler\RunListingImportHandler;
use App\Service\ListingImport\AssistantAvailability;
use App\Service\ListingImport\ContactDetector;
use App\Service\ListingImport\ListingImporter;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The assistant never runs during the owner's request: POST stores and queues, the worker reads,
 * GET tells where it stands. The provider is replaced by InMemoryPlatform.
 */
final class OwnerListingImportApiTest extends WebTestCase
{
    private const string TEXT = 'Bungalow 3 chambres au calme. Septembre : 450 € la semaine. Tél 06 11 22 33 44.';
    private const string ANSWER = '{"periods":[{"label":"septembre","start":"2026-09-01","end":"2026-10-01","prices":[{"amount":450,"unit":"week"}],"minimumNights":null,"saturdayArrival":false}],"unavailable":[],"questions":[],"listing":{"type":"bungalow","capacity":null,"bedrooms":3,"surface":null,"district":null,"amenities":[],"petsPolicy":null,"otherFeatures":[]}}';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $alice;
    private int $calls = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        (new ORMPurger($this->em))->purge();

        $this->alice = $this->owner('alice@example.com', verified: true);
        $this->em->flush();
        $this->availability()->resume();
    }

    public function testAnAnonymousVisitorCannotImport(): void
    {
        $this->client->request('POST', '/api/owner/listing-imports', content: '{"text":"x"}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAnUnconfirmedAddressCannotUseTheAssistant(): void
    {
        $bob = $this->owner('bob@example.com', verified: false);
        $this->em->flush();
        $this->client->loginUser($bob, 'main');

        $this->post(self::TEXT);

        self::assertResponseStatusCodeSame(403);
    }

    public function testATooShortTextIsRefused(): void
    {
        $this->client->loginUser($this->alice, 'main');

        $this->post('Bungalow');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('text', $this->json()['violations'][0]['propertyPath']);
    }

    public function testTheTextIsStoredAndReadByTheWorkerNotDuringTheRequest(): void
    {
        $this->useModel(fn (): string => self::ANSWER);
        $this->client->loginUser($this->alice, 'main');

        $this->post(self::TEXT);

        self::assertResponseStatusCodeSame(202);
        $created = $this->json();
        self::assertSame('pending', $created['status']);
        self::assertSame(self::TEXT, $created['text'], 'the text is kept whatever happens');
        self::assertSame(0, $this->calls, 'the provider is not called during the request');
        self::assertCount(1, $this->queued());

        $this->work();

        $this->client->request('GET', '/api/owner/listing-imports/'.$created['id']);
        $read = $this->json();
        self::assertSame('done', $read['status']);
        self::assertSame(450, $read['result']['periods'][0]['prices'][0]['amount']);
        self::assertSame(['06 11 22 33 44'], $read['result']['contacts']);
        self::assertNull($read['message']);
    }

    public function testTheSameTextIsReadOnlyOnce(): void
    {
        $this->useModel(fn (): string => self::ANSWER);
        $this->client->loginUser($this->alice, 'main');

        $this->post(self::TEXT);
        $first = $this->json()['id'];
        $this->work();
        $this->post("  bungalow 3 chambres au calme.   Septembre : 450 € la semaine. Tél 06 11 22 33 44.\n");

        self::assertResponseStatusCodeSame(200);
        self::assertSame($first, $this->json()['id']);
        self::assertSame('done', $this->json()['status']);
        self::assertSame(1, $this->calls);
    }

    public function testAProviderOutageIsRetriedThenGivenUpCleanly(): void
    {
        $this->useModel(fn (): never => throw new ServerException(503, 'surcharge'));
        $this->client->loginUser($this->alice, 'main');
        $this->post(self::TEXT);
        $id = $this->json()['id'];

        $this->work();
        $retry = $this->queued();
        self::assertCount(1, $retry, 'a second attempt is queued');
        self::assertSame(15_000, $retry[0]->last(DelayStamp::class)?->getDelay());
        $this->work();
        self::assertSame(60_000, $this->queued()[0]->last(DelayStamp::class)?->getDelay());
        // (No HTTP request between two work(): a request resets the in-memory transport.)
        $this->work();

        self::assertSame(3, $this->calls);
        self::assertCount(0, $this->queued(), 'no fourth attempt');
        $this->client->request('GET', '/api/owner/listing-imports/'.$id);
        $failed = $this->json();
        self::assertSame('failed', $failed['status']);
        self::assertSame('unavailable', $failed['failure']);
        self::assertStringContainsString('Votre texte est gardé', (string) $failed['message']);
        self::assertSame(self::TEXT, $failed['text']);

        // For a few minutes nobody is offered a button that would only fail.
        $this->client->request('GET', '/api/owner/listing-imports/assistant');
        self::assertFalse($this->json()['available']);
        self::assertSame('paused', $this->json()['reason']);
        $this->post('Un autre bungalow, octobre à 380 € la semaine, deux chambres.');
        self::assertResponseStatusCodeSame(503);
    }

    public function testTheOwnerIsToldWhenASecondAttemptIsRunning(): void
    {
        $this->useModel(fn (): never => throw new ServerException(503, 'surcharge'));
        $this->client->loginUser($this->alice, 'main');
        $this->post(self::TEXT);
        $id = $this->json()['id'];
        $this->work();

        $this->client->request('GET', '/api/owner/listing-imports/'.$id);

        self::assertSame('pending', $this->json()['status']);
        self::assertNull($this->json()['failure'], 'not a failure yet');
        self::assertStringContainsString('nouvel essai en cours', (string) $this->json()['message']);
    }

    public function testAConfigurationErrorIsNotRetried(): void
    {
        $this->useModel(fn (): never => throw new AuthenticationException('clé invalide'));
        $this->client->loginUser($this->alice, 'main');
        $this->post(self::TEXT);
        $id = $this->json()['id'];

        $this->work();

        self::assertSame(1, $this->calls);
        self::assertCount(0, $this->queued());
        $this->client->request('GET', '/api/owner/listing-imports/'.$id);
        self::assertSame('configuration', $this->json()['failure']);
        self::assertFalse($this->availability()->isAvailable());
    }

    public function testSomeoneElsesImportIsNotFound(): void
    {
        $bob = $this->owner('bob@example.com', verified: true);
        $import = new ListingImport($bob, self::TEXT, new \DateTimeImmutable());
        $this->em->persist($import);
        $this->em->flush();
        $this->client->loginUser($this->alice, 'main');

        $this->client->request('GET', '/api/owner/listing-imports/'.$import->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(404);
    }

    private function useModel(\Closure $answer): void
    {
        $platform = new InMemoryPlatform(function () use ($answer): string {
            ++$this->calls;

            return $answer();
        });
        self::getContainer()->set(ListingImporter::class, new ListingImporter(
            $platform, new ContactDetector(), new NullLogger(), __DIR__.'/../../config/prompts/listing-import.md',
        ));
    }

    /** What the worker would do: handle every queued message once. */
    private function work(): void
    {
        $transport = $this->transport();
        $envelopes = $transport->getSent();
        $transport->reset();
        $handler = self::getContainer()->get(RunListingImportHandler::class);
        self::assertInstanceOf(RunListingImportHandler::class, $handler);

        foreach ($envelopes as $envelope) {
            $message = $envelope->getMessage();
            self::assertInstanceOf(RunListingImport::class, $message);
            $handler($message);
        }
        $this->em->clear();
    }

    /** @return list<Envelope> */
    private function queued(): array
    {
        return array_values(array_filter(
            $this->transport()->getSent(),
            static fn (Envelope $e): bool => $e->getMessage() instanceof RunListingImport,
        ));
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function availability(): AssistantAvailability
    {
        $availability = self::getContainer()->get(AssistantAvailability::class);
        self::assertInstanceOf(AssistantAvailability::class, $availability);

        return $availability;
    }

    private function post(string $text): void
    {
        $this->client->request(
            'POST',
            '/api/owner/listing-imports',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => $text], \JSON_THROW_ON_ERROR),
        );
    }

    private function owner(string $email, bool $verified): User
    {
        $owner = new User($email, 'Propriétaire');
        if ($verified) {
            $owner->verifyEmail(new \DateTimeImmutable());
        }
        $this->em->persist($owner);

        return $owner;
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
