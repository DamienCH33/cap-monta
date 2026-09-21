<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Accommodation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Seul le propriétaire d'un logement peut le consulter ou le modifier
 * dans son espace. Deux attributs distincts, même règle pour l'instant :
 * ils pourront diverger (archivé = lecture seule) sans toucher aux appelants.
 *
 * @extends Voter<string, Accommodation>
 */
final class AccommodationVoter extends Voter
{
    public const VIEW = 'ACCOMMODATION_VIEW';
    public const EDIT = 'ACCOMMODATION_EDIT';

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Accommodation;
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $subject->getOwner()->getId()->equals($user->getId());
    }
}
