<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\StayAlert;
use App\Enum\Resort;
use App\Factory\AccommodationFactory;
use App\Factory\UnavailabilityFactory;
use App\Tests\ApiTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

final class StayAlertApiTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const array STAY = ['arrival' => '2027-07-10', 'departure' => '2027-07-17', 'guests' => 4];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::mockTime('2027-03-01 10:00:00');
    }

    public function testAGuestLeavesAnAlertThenActivatesItFromTheEmail(): void
    {
        $this->post(['email' => 'Anna@Example.com', 'consent' => true, 'resort' => 'chm'] + self::STAY);
        self::assertResponseStatusCodeSame(202);

        $mail = $this->onlyMail();
        self::assertEmailAddressContains($mail, 'To', 'anna@example.com');
        self::assertSame('Activez votre alerte — Cap Monta', $mail->getSubject());
        self::assertStringContainsString('Séjour : du samedi 10 juillet 2027 au samedi 17 juillet 2027 (7 nuits)', (string) $mail->getTextBody());
        self::assertStringContainsString('Voyageurs : 4 personnes', (string) $mail->getTextBody());
        self::assertStringContainsString('Domaine : CHM Montalivet', (string) $mail->getTextBody());
        self::assertStringContainsString('Activer mon alerte', (string) $mail->getHtmlBody());
        $key = self::keyIn($mail);

        $this->client->request('GET', '/api/stay-alerts/'.$key);
        self::assertResponseIsSuccessful();
        self::assertSame(['arrival' => '2027-07-10', 'departure' => '2027-07-17', 'guests' => 4, 'resort' => 'chm', 'confirmed' => false], $this->json());

        $this->client->request('POST', '/api/stay-alerts/'.$key.'/confirm', server: ['CONTENT_TYPE' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['confirmed']);
    }

    public function testTheSameAlertTwiceIsOneAlertAndAnActiveOneSendsNothingMore(): void
    {
        $this->post(['email' => 'anna@example.com', 'consent' => true] + self::STAY);
        $key = self::keyIn($this->onlyMail());

        // Lost the first email: asking again sends it again, still one alert.
        $this->post(['email' => 'ANNA@example.com', 'consent' => true] + self::STAY);
        self::assertResponseStatusCodeSame(202);
        self::assertSame($key, self::keyIn($this->onlyMail()));
        self::assertSame(1, $this->alerts());

        $this->client->request('POST', '/api/stay-alerts/'.$key.'/confirm', server: ['CONTENT_TYPE' => 'application/json']);
        $this->post(['email' => 'anna@example.com', 'consent' => true] + self::STAY);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(0);
    }

    public function testAnAddressKeepsTenAlertsAtMostWithoutBeingTold(): void
    {
        for ($week = 0; $week < StayAlert::MAX_PER_EMAIL + 1; ++$week) {
            $arrival = new \DateTimeImmutable('2027-06-05')->modify("+$week weeks");
            $this->post(['email' => 'anna@example.com', 'consent' => true, 'guests' => 2, 'arrival' => $arrival->format('Y-m-d'), 'departure' => $arrival->modify('+7 days')->format('Y-m-d')]);
            self::assertResponseStatusCodeSame(202);
        }

        self::assertEmailCount(0);
        self::assertSame(StayAlert::MAX_PER_EMAIL, $this->alerts());
    }

    public function testTheEmailsSpeakTheLanguageOfThePage(): void
    {
        $this->post(['email' => 'jan@example.nl', 'consent' => true] + self::STAY, 'nl');

        $mail = $this->onlyMail();
        self::assertSame('Activeer uw melding — Cap Monta', $mail->getSubject());
        self::assertStringContainsString('/nl/alerte/', (string) $mail->getTextBody());
        self::assertStringContainsString('Mijn melding activeren', (string) $mail->getHtmlBody());
    }

    public function testAnAlertNeedsConsentRealDatesAndAFewGuests(): void
    {
        $this->post(['email' => 'pas-un-email', 'consent' => false, 'guests' => 0, 'arrival' => 'demain', 'departure' => '2027-07-17']);
        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['email', 'consent', 'guests', 'arrival'], array_column($this->json()['violations'], 'propertyPath'));

        $this->post(['email' => 'anna@example.com', 'consent' => true, 'guests' => 2, 'arrival' => '2027-07-17', 'departure' => '2027-07-10']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('departure', $this->json()['violations'][0]['propertyPath']);

        $this->post(['email' => 'anna@example.com', 'consent' => true, 'guests' => 2, 'arrival' => '2027-03-01', 'departure' => '2027-03-08']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('arrival', $this->json()['violations'][0]['propertyPath']);

        $this->post(['email' => 'anna@example.com', 'consent' => true, 'guests' => 2, 'arrival' => '2027-04-01', 'departure' => '2027-12-01']);
        self::assertResponseStatusCodeSame(422);

        self::assertEmailCount(0);
        self::assertSame(0, $this->alerts());
    }

    public function testAKeyTheSiteDidNotSignLeadsNowhere(): void
    {
        $this->post(['email' => 'anna@example.com', 'consent' => true] + self::STAY);
        [$id] = explode('.', self::keyIn($this->onlyMail()));

        foreach (['n-importe-quoi', $id, $id.'.'.str_repeat('A', 32), '.'] as $key) {
            $this->client->request('GET', '/api/stay-alerts/'.$key);
            self::assertResponseStatusCodeSame(404, $key);
        }
    }

    public function testTheGuestIsToldOnceWhenAHomeFreesUpAndCanStop(): void
    {
        $alreadyFree = AccommodationFactory::createOne(['slug' => 'deja-libre']);
        $blocked = AccommodationFactory::createOne(['slug' => 'bientot-libre']);
        $tooSmall = AccommodationFactory::createOne(['slug' => 'trop-petit', 'capacity' => 2, 'maxCapacity' => 2]);
        $elsewhere = AccommodationFactory::createOne(['slug' => 'a-euronat', 'resort' => Resort::Euronat]);
        $week = UnavailabilityFactory::createOne([
            'accommodation' => $blocked,
            'startDate' => new \DateTimeImmutable('2027-07-10'),
            'endDate' => new \DateTimeImmutable('2027-07-17'),
        ]);

        $this->post(['email' => 'anna@example.com', 'consent' => true, 'resort' => 'chm'] + self::STAY);
        $key = self::keyIn($this->onlyMail());

        // Not active yet: nothing goes out.
        self::assertStringContainsString('0 alerte(s)', $this->runConsole('app:stay-alerts:notify'));

        $this->client->request('POST', '/api/stay-alerts/'.$key.'/confirm', server: ['CONTENT_TYPE' => 'application/json']);
        // He saw « deja-libre » in his search: not news.
        self::assertStringContainsString('0 alerte(s)', $this->runConsole('app:stay-alerts:notify'));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->remove($em->getReference($week::class, $week->getId()));
        $em->flush();

        self::assertStringContainsString('1 alerte(s)', $this->runConsole('app:stay-alerts:notify'));
        $mail = $this->onlyMail();
        self::assertSame('Un logement est libre à vos dates — Cap Monta', $mail->getSubject());
        $text = (string) $mail->getTextBody();
        self::assertStringContainsString('Un logement vient de se libérer à vos dates.', $text);
        self::assertStringContainsString('/logement/bientot-libre', $text);
        foreach ([$alreadyFree, $tooSmall, $elsewhere] as $other) {
            self::assertStringNotContainsString('/logement/'.$other->getSlug(), $text);
        }
        self::assertStringContainsString('/recherche?arrivee=2027-07-10&depart=2027-07-17&adultes=4&domaine=chm', $text);
        self::assertStringContainsString('/alerte/'.$key, $text);

        // Each home once.
        self::assertStringContainsString('0 alerte(s)', $this->runConsole('app:stay-alerts:notify'));

        $this->client->request('DELETE', '/api/stay-alerts/'.$key);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/stay-alerts/'.$key);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAlertsGoWithTheirArrivalDayOrAfterTwoDaysUnconfirmed(): void
    {
        $this->post(['email' => 'oublie@example.com', 'consent' => true] + self::STAY);
        $this->post(['email' => 'active@example.com', 'consent' => true] + self::STAY);
        $this->client->request('POST', '/api/stay-alerts/'.self::keyIn($this->onlyMail()).'/confirm', server: ['CONTENT_TYPE' => 'application/json']);
        $this->post(['email' => 'recent@example.com', 'consent' => true, 'guests' => 2, 'arrival' => '2027-08-07', 'departure' => '2027-08-14']);
        $this->client->request('POST', '/api/stay-alerts/'.self::keyIn($this->onlyMail()).'/confirm', server: ['CONTENT_TYPE' => 'application/json']);

        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement("UPDATE stay_alert SET created_at = created_at - interval '3 days' WHERE email IN ('oublie@example.com', 'active@example.com')");

        $this->runConsole('app:privacy:purge');
        self::assertEqualsCanonicalizing(['active@example.com', 'recent@example.com'], $db->fetchFirstColumn('SELECT email FROM stay_alert'));

        static::mockTime('2027-07-10 08:00:00');
        $this->runConsole('app:privacy:purge');
        self::assertSame(['recent@example.com'], $db->fetchFirstColumn('SELECT email FROM stay_alert'));
    }

    public function testTheStatsCountWaitingGuestsByMonth(): void
    {
        foreach (['anna@example.com', 'jan@example.nl'] as $email) {
            $this->post(['email' => $email, 'consent' => true] + self::STAY);
            $this->client->request('POST', '/api/stay-alerts/'.self::keyIn($this->onlyMail()).'/confirm', server: ['CONTENT_TYPE' => 'application/json']);
        }
        $this->post(['email' => 'jamais-active@example.com', 'consent' => true] + self::STAY);

        $display = $this->runConsole('app:stay-alerts:stats');
        self::assertMatchesRegularExpression('/2027-07\s+les deux\s+2/', $display);
        self::assertStringNotContainsString('@', $display);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body, string $language = 'fr'): void
    {
        $this->client->request('POST', '/api/stay-alerts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => $language,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function onlyMail(): Email
    {
        self::assertEmailCount(1);
        $mail = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $mail);

        return $mail;
    }

    private static function keyIn(Email $mail): string
    {
        self::assertSame(1, preg_match('#/alerte/([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)#', (string) $mail->getTextBody(), $match));

        return $match[1] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }

    private function alerts(): int
    {
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);

        return (int) $db->fetchOne('SELECT count(*) FROM stay_alert');
    }

    private function runConsole(string $command): string
    {
        $tester = new CommandTester(new Application($this->client->getKernel())->find($command));
        self::assertSame(0, $tester->execute([]));

        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
