<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\AccommodationStatus;
use App\Enum\HeardFrom;
use App\Factory\AccommodationFactory;
use App\Factory\UserFactory;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class OwnerSourcesCommandTest extends DatabaseTestCase
{
    public function testCountsConfirmedOwnersByChannelAndThoseWhoPublished(): void
    {
        $facebook = UserFactory::createOne(['heardFrom' => HeardFrom::FacebookGroup]);
        UserFactory::createOne(['heardFrom' => HeardFrom::FacebookGroup]);
        UserFactory::createOne(['heardFrom' => HeardFrom::Press]);
        UserFactory::createOne();
        // Never confirmed: not counted.
        $ghost = UserFactory::createOne(['heardFrom' => HeardFrom::Press]);

        AccommodationFactory::createOne(['owner' => $facebook]);
        AccommodationFactory::createOne(['owner' => $facebook, 'status' => AccommodationStatus::Draft]);

        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement('UPDATE "user" SET email_verified_at = now() WHERE id <> ?', [$ghost->getId()->toRfc4122()]);

        $tester = new CommandTester(new Application(self::$kernel)->find('app:owners:sources'));
        self::assertSame(0, $tester->execute([]));
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());

        self::assertStringContainsString('Groupe Facebook 2 1', $display);
        self::assertStringContainsString('Presse 1 0', $display);
        self::assertStringContainsString('sans réponse 1 0', $display);
    }
}
