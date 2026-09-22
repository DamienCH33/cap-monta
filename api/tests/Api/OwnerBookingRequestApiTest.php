<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\BookingRequest;
use App\Entity\Photo;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\BookingRequestStatus;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Message\ExpireBookingRequest;
use App\MessageHandler\ExpireBookingRequestHandler;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

final class OwnerBookingRequestApiTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private \DateTimeImmutable $today;
    private User $alice;
    private Accommodation $home;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        (new ORMPurger($this->em))->purge();

        $this->today = new \DateTimeImmutable('today');
        $this->alice = $this->owner('alice@example.com', '06 11 22 33 44');
        $this->home = $this->published('bungalow-alice', $this->alice);
    }

    public function testAnAnonymousVisitorSeesNoInbox(): void
    {
        $this->client->request('GET', '/api/owner/booking-requests');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheInboxHidesTheGuestsContactUntilAcceptance(): void
    {
        $this->request($this->home, 10, 17, 'Jeanne Martin');
        $this->request($this->published('bungalow-bob', $this->owner('bob@example.com')), 10, 17, 'Chez Bob');
        $this->login();

        $this->client->request('GET', '/api/owner/booking-requests');
        $inbox = $this->json();

        self::assertResponseIsSuccessful();
        self::assertSame(['Jeanne Martin'], array_column($inbox, 'guestName'));
        self::assertNull($inbox[0]['contact']);
        self::assertSame('Bungalow · Euronat', $inbox[0]['accommodation']['title']);
    }

    public function testAcceptingBlocksTheDatesSharesTheContactsAndDeclinesTheOthers(): void
    {
        $this->rate($this->home, 0, 60, weekly: 70000);
        $chosen = $this->request($this->home, 10, 17, 'Jeanne Martin', estimated: 70000);
        $other = $this->request($this->home, 14, 21, 'Paul Durand');
        $this->request($this->home, 30, 37, 'Luc Petit');
        $this->login();

        $this->client->request('POST', '/api/owner/booking-requests/'.$chosen->getId()->toRfc4122().'/accept', content: '{"message":"Bienvenue !"}');

        self::assertResponseIsSuccessful();
        $inbox = array_column($this->json(), null, 'guestName');
        self::assertSame('accepted', $inbox['Jeanne Martin']['status']);
        self::assertSame(70000, $inbox['Jeanne Martin']['agreedPrice']);
        self::assertSame(['email' => 'jeanne-martin@example.com', 'phone' => '06 12 34 56 78'], $inbox['Jeanne Martin']['contact']);
        self::assertSame('declined', $inbox['Paul Durand']['status'], 'Same dates: declined at once.');
        self::assertSame('pending', $inbox['Luc Petit']['status'], 'Other dates: untouched.');

        // Guest (owner's contact), owner (guest's contact), and the guest declined automatically.
        self::assertEmailCount(3);
        self::assertEmailTextBodyContains(self::getMailerMessage(0) ?? self::fail(), '06 11 22 33 44');
        self::assertEmailTextBodyContains(self::getMailerMessage(0) ?? self::fail(), 'Bienvenue !');
        self::assertEmailTextBodyContains(self::getMailerMessage(1) ?? self::fail(), 'jeanne-martin@example.com');
        self::assertEmailAddressContains(self::getMailerMessage(2) ?? self::fail(), 'To', 'paul-durand@example.com');

        $this->em->clear();
        $blocked = $this->em->getRepository(Unavailability::class)->findAll();
        self::assertCount(1, $blocked);
        self::assertSame(UnavailabilitySource::Booking, $blocked[0]->getSource());

        $this->client->request('GET', '/api/accommodations/bungalow-alice/availability');
        self::assertCount(1, $this->json()['busy'], 'The public calendar shows the new booking at once.');
    }

    public function testAPriceIsRequiredWhenThereWasNoRate(): void
    {
        $request = $this->request($this->home, 10, 17, 'Jeanne Martin');
        $this->login();
        $url = '/api/owner/booking-requests/'.$request->getId()->toRfc4122().'/accept';

        $this->client->request('POST', $url);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('price', $this->json()['violations'][0]['propertyPath']);

        $this->client->request('POST', $url, content: '{"price":45000}');
        self::assertResponseIsSuccessful();
        self::assertSame(45000, $this->json()[0]['agreedPrice']);
    }

    public function testDatesTakenMeanwhileAreFlaggedAndCannotBeAccepted(): void
    {
        $request = $this->request($this->home, 10, 17, 'Jeanne Martin', estimated: 50000);
        $this->em->persist(new Unavailability($this->home, $this->day(12), $this->day(14), UnavailabilitySource::Block));
        $this->em->flush();
        $this->login();

        $this->client->request('GET', '/api/owner/booking-requests');
        self::assertTrue($this->json()[0]['conflict']);

        $this->client->request('POST', '/api/owner/booking-requests/'.$request->getId()->toRfc4122().'/accept');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('dates', $this->json()['violations'][0]['propertyPath']);
    }

    public function testDecliningSendsTheOwnersMessage(): void
    {
        $request = $this->request($this->home, 10, 17, 'Jeanne Martin');
        $this->login();

        $this->client->request('POST', '/api/owner/booking-requests/'.$request->getId()->toRfc4122().'/decline', content: '{"message":"Nous y serons nous-mêmes."}');

        self::assertResponseIsSuccessful();
        self::assertSame('declined', $this->json()[0]['status']);
        self::assertEmailCount(1);
        self::assertEmailTextBodyContains(self::getMailerMessage(0) ?? self::fail(), 'Nous y serons nous-mêmes.');

        // Answered once: a second answer is refused.
        $this->client->request('POST', '/api/owner/booking-requests/'.$request->getId()->toRfc4122().'/accept', content: '{"price":45000}');
        self::assertResponseStatusCodeSame(409);
    }

    public function testCancellingABookingFreesTheDates(): void
    {
        $request = $this->request($this->home, 10, 17, 'Jeanne Martin', estimated: 50000);
        $this->login();
        $id = $request->getId()->toRfc4122();

        $this->client->request('POST', '/api/owner/booking-requests/'.$id.'/cancel');
        self::assertResponseStatusCodeSame(409, 'A pending request is declined, not cancelled.');

        $this->client->request('POST', '/api/owner/booking-requests/'.$id.'/accept');
        $this->client->request('POST', '/api/owner/booking-requests/'.$id.'/cancel', content: '{"message":"Dégât des eaux."}');

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->json()[0]['status']);
        self::assertSame(0, $this->em->getRepository(Unavailability::class)->count([]));
        self::assertEmailTextBodyContains(self::getMailerMessage(0) ?? self::fail(), 'Dégât des eaux.');
    }

    public function testSomeoneElsesRequestIsNotFound(): void
    {
        $bobs = $this->request($this->published('bungalow-bob', $this->owner('bob@example.com')), 10, 17, 'Chez Bob');
        $this->login();

        $this->client->request('POST', '/api/owner/booking-requests/'.$bobs->getId()->toRfc4122().'/decline');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheGuestCancelsHisBookingFromHisLink(): void
    {
        $request = $this->request($this->home, 10, 17, 'Jeanne Martin', estimated: 50000);
        $this->login();
        $this->client->request('POST', '/api/owner/booking-requests/'.$request->getId()->toRfc4122().'/accept');

        $this->client->request('POST', '/api/booking-requests/track/'.$request->getTrackingToken().'/cancel');

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->json()['status']);
        self::assertFalse($this->json()['cancellable']);
        self::assertSame(0, $this->em->getRepository(Unavailability::class)->count([]));
        self::assertEmailAddressContains(self::getMailerMessage(0) ?? self::fail(), 'To', 'alice@example.com');
    }

    public function testAnUnansweredRequestExpiresAfter48Hours(): void
    {
        $request = $this->request($this->home, 10, 17, 'Jeanne Martin');
        $id = $request->getId()->toRfc4122();
        $handler = static::getContainer()->get(ExpireBookingRequestHandler::class);

        // Delivered early (clock skew, manual retry): nothing happens.
        $handler(new ExpireBookingRequest($id));
        self::assertSame(BookingRequestStatus::Pending, $this->reload($request)->getStatus());

        self::mockTime('+49 hours');
        $handler(new ExpireBookingRequest($id));
        self::assertSame(BookingRequestStatus::Expired, $this->reload($request)->getStatus());

        // Delivered twice: still harmless.
        $handler(new ExpireBookingRequest($id));
        self::assertSame(BookingRequestStatus::Expired, $this->reload($request)->getStatus());
    }

    public function testTheCommandCatchesUpWithLostMessages(): void
    {
        $late = $this->request($this->home, 10, 17, 'Jeanne Martin');
        $fresh = $this->request($this->home, 20, 27, 'Paul Durand');
        // Its delayed message was lost: the deadline passed an hour ago.
        (new \ReflectionProperty(BookingRequest::class, 'expiresAt'))->setValue($late, new \DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $kernel = self::$kernel ?? self::fail();
        $tester = new CommandTester((new Application($kernel))->find('app:booking-requests:expire'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(BookingRequestStatus::Expired, $this->reload($late)->getStatus());
        self::assertSame(BookingRequestStatus::Pending, $this->reload($fresh)->getStatus());
    }

    public function testAnAnswerAfterTheDeadlineIsRefused(): void
    {
        $request = $this->request($this->home, 10, 17, 'Jeanne Martin', estimated: 50000);
        $this->login();
        self::mockTime('+49 hours');

        $this->client->request('POST', '/api/owner/booking-requests/'.$request->getId()->toRfc4122().'/accept');

        self::assertResponseStatusCodeSame(409);
        self::assertSame(BookingRequestStatus::Expired, $this->reload($request)->getStatus());
    }

    private function login(): void
    {
        $this->client->loginUser($this->alice, 'main');
    }

    private function owner(string $email, ?string $phone = null): User
    {
        $owner = new User($email, 'Alice Propriétaire');
        $owner->verifyEmail(new \DateTimeImmutable());
        $owner->setPhone($phone);
        $this->em->persist($owner);
        $this->em->flush();

        return $owner;
    }

    private function published(string $slug, User $owner): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation with a description long enough to be published.', $owner);
        $accommodation->addPhoto(new Photo($accommodation, 1600, 1066));
        $accommodation->publish();
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    private function rate(Accommodation $accommodation, int $from, int $to, int $weekly): void
    {
        $period = new PricePeriod($accommodation, $this->day($from), $this->day($to));
        $period->setWeeklyPrice($weekly);
        $this->em->persist($period);
        $this->em->flush();
    }

    private function request(Accommodation $accommodation, int $from, int $to, string $guest, ?int $estimated = null): BookingRequest
    {
        $email = strtolower(str_replace(' ', '-', $guest)).'@example.com';
        $request = new BookingRequest($accommodation, $this->day($from), $this->day($to), 2, $guest, $email);
        $request->setGuestPhone('06 12 34 56 78')->setEstimatedPrice($estimated);
        $this->em->persist($request);
        $this->em->flush();

        return $request;
    }

    private function reload(BookingRequest $request): BookingRequest
    {
        $this->em->clear();

        return $this->em->find(BookingRequest::class, $request->getId()) ?? self::fail('Request gone.');
    }

    private function day(int $offset): \DateTimeImmutable
    {
        return $this->today->modify(sprintf('+%d days', $offset));
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
