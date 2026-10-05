<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\I18n\Translator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Un propriétaire qui se connecte depuis la version allemande du site reçoit ensuite ses emails
 * en allemand : la langue de la dernière connexion est celle qu'il lit.
 */
#[AsEventListener]
final readonly class OwnerLocaleListener
{
    public function __construct(
        private Translator $translator,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $locale = $this->translator->locale();

        if ($user instanceof User && $user->getLocale() !== $locale) {
            $user->setLocale($locale);
            $this->em->flush();
        }
    }
}
