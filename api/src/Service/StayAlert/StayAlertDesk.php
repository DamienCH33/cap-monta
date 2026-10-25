<?php

declare(strict_types=1);

namespace App\Service\StayAlert;

use App\Entity\Accommodation;
use App\Entity\StayAlert;
use App\Enum\Resort;
use App\Repository\AccommodationRepository;
use App\Repository\StayAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What « Prévenez-moi » does: leave an alert, confirm it, stop it, and the hourly round that
 * writes to the guests whose dates have freed up.
 */
final readonly class StayAlertDesk
{
    public function __construct(
        private StayAlertRepository $alerts,
        private AccommodationRepository $accommodations,
        private StayAlertMailer $mailer,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Never tells the caller what happened: the confirmation email goes to the mailbox, and
     * only its owner learns whether the address already had alerts.
     */
    public function register(string $email, string $locale, \DateTimeImmutable $arrival, \DateTimeImmutable $departure, int $guests, ?Resort $resort): void
    {
        $same = $this->alerts->findSame($email, $arrival, $departure, $guests, $resort);

        if (null !== $same) {
            // Asked again before clicking: the first email was lost or deleted, send it again.
            if (!$same->isConfirmed()) {
                $this->mailer->confirmation($same);
            }

            return;
        }

        if ($this->alerts->countForEmail($email) >= StayAlert::MAX_PER_EMAIL) {
            return;
        }

        $alert = new StayAlert($email, $locale, $arrival, $departure, $guests, $resort, $this->clock->now());
        $this->em->persist($alert);
        $this->em->flush();

        $this->mailer->confirmation($alert);
    }

    public function find(Uuid $id): ?StayAlert
    {
        return $this->alerts->find($id);
    }

    /** The homes free today are the ones his search showed: only the next ones are news. */
    public function confirm(StayAlert $alert): void
    {
        $alert->confirm($this->clock->now(), self::slugs($this->freeHomes($alert)));
        $this->em->flush();
    }

    public function stop(StayAlert $alert): void
    {
        $this->em->remove($alert);
        $this->em->flush();
    }

    /**
     * One email per alert with new homes free at its dates, each home announced once.
     *
     * @return int the emails sent
     */
    public function notify(): int
    {
        $sent = 0;

        foreach ($this->alerts->findActive($this->clock->now()->setTime(0, 0)) as $alert) {
            $new = array_values(array_filter(
                $this->freeHomes($alert),
                static fn (Accommodation $home): bool => !\in_array($home->getSlug(), $alert->getNotifiedSlugs(), true),
            ));

            if ([] === $new) {
                continue;
            }

            $this->mailer->matches($alert, $new);
            $alert->markNotified(self::slugs($new));
            $this->em->flush();
            ++$sent;
        }

        return $sent;
    }

    /**
     * @return list<Accommodation>
     */
    private function freeHomes(StayAlert $alert): array
    {
        return $this->accommodations->search(
            arrival: $alert->getArrival(),
            departure: $alert->getDeparture(),
            guests: $alert->getGuests(),
            resort: $alert->getResort(),
        );
    }

    /**
     * @param list<Accommodation> $homes
     *
     * @return list<string>
     */
    private static function slugs(array $homes): array
    {
        return array_map(static fn (Accommodation $home): string => $home->getSlug(), $homes);
    }
}
