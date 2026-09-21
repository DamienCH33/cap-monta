<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Accommodation;
use App\Entity\District;
use App\Entity\User;
use App\Enum\AccommodationStatus;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Exception\InvalidStatusTransitionException;
use App\Exception\PublicationRefusedException;
use PHPUnit\Framework\TestCase;

final class AccommodationPublicationTest extends TestCase
{
    private const string LONG_DESCRIPTION = 'A description long enough to go online, fifty characters at least.';

    public function testACompleteDraftCanBePublished(): void
    {
        $accommodation = $this->accommodation();
        $accommodation->publish();

        self::assertSame(AccommodationStatus::Published, $accommodation->getStatus());
    }

    public function testAnUnverifiedOwnerCannotPublish(): void
    {
        $accommodation = $this->accommodation(verifiedOwner: false);

        try {
            $accommodation->publish();
            self::fail('Publishing should have been refused.');
        } catch (PublicationRefusedException $e) {
            self::assertTrue($e->ownerUnverified);
        }

        self::assertSame(AccommodationStatus::Draft, $accommodation->getStatus());
    }

    public function testAnIncompleteListingNamesWhatIsMissing(): void
    {
        $accommodation = $this->accommodation(description: 'Trop court', withDistrict: false);

        try {
            $accommodation->publish();
            self::fail('Publishing should have been refused.');
        } catch (PublicationRefusedException $e) {
            self::assertSame(['description', 'district'], $e->missing);
        }

        self::assertSame(AccommodationStatus::Draft, $accommodation->getStatus());
    }

    public function testAnArchivedListingCanBePublishedAgain(): void
    {
        $accommodation = $this->accommodation();
        $accommodation->publish();
        $accommodation->archive();
        $accommodation->publish();

        self::assertSame(AccommodationStatus::Published, $accommodation->getStatus());
    }

    public function testADraftCannotBeArchived(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        $this->accommodation()->archive();
    }

    public function testPublishingOrArchivingTwiceChangesNothing(): void
    {
        $accommodation = $this->accommodation();
        $accommodation->publish();
        $accommodation->publish();
        self::assertSame(AccommodationStatus::Published, $accommodation->getStatus());

        $accommodation->archive();
        $accommodation->archive();
        self::assertSame(AccommodationStatus::Archived, $accommodation->getStatus());
    }

    private function accommodation(
        bool $verifiedOwner = true,
        string $description = self::LONG_DESCRIPTION,
        bool $withDistrict = true,
    ): Accommodation {
        $owner = new User('owner@example.com', 'Owner');

        if ($verifiedOwner) {
            $owner->verifyEmail(new \DateTimeImmutable());
        }

        $accommodation = new Accommodation('home', Resort::Chm, AccommodationType::MobileHome, 4, 2, $description, $owner);

        if ($withDistrict) {
            $accommodation->setDistrict(new District('europa', 'Europa', Resort::Chm));
        }

        return $accommodation;
    }
}
