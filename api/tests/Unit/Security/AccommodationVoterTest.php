<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Accommodation;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Security\Voter\AccommodationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AccommodationVoterTest extends TestCase
{
    private AccommodationVoter $voter;
    private User $owner;
    private Accommodation $accommodation;

    protected function setUp(): void
    {
        $this->voter = new AccommodationVoter();
        $this->owner = new User('owner@example.com', 'Owner');
        $this->accommodation = new Accommodation('test-home', Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation', $this->owner);
    }

    public function testTheOwnerCanViewAndEdit(): void
    {
        $token = new UsernamePasswordToken($this->owner, 'main', $this->owner->getRoles());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->accommodation, [AccommodationVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->accommodation, [AccommodationVoter::EDIT]));
    }

    public function testAnotherOwnerIsDenied(): void
    {
        $intruder = new User('intruder@example.com', 'Intruder');
        $token = new UsernamePasswordToken($intruder, 'main', $intruder->getRoles());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->accommodation, [AccommodationVoter::EDIT]));
    }

    public function testAnAnonymousVisitorIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote(new NullToken(), $this->accommodation, [AccommodationVoter::VIEW]));
    }

    public function testAnUnknownAttributeAbstains(): void
    {
        $token = new UsernamePasswordToken($this->owner, 'main', $this->owner->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $this->accommodation, ['SOMETHING_ELSE']));
    }
}
