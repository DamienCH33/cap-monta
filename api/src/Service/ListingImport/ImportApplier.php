<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Dto\CreateAccommodationInput;
use App\Entity\Accommodation;
use App\Entity\ListingImport;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\UnavailabilitySource;
use App\Repository\PricePeriodRepository;
use App\Repository\UnavailabilityRepository;
use App\Service\Accommodation\OwnerAccommodationCreator;
use App\Service\Calendar\PublicCalendar;
use App\Service\Pricing\RateRules;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * "C'est bon, on enregistre" (lot 4c): what the owner validated on the check screen becomes a
 * draft accommodation, or goes into one of his, with its rates and taken dates. Everything or
 * nothing: one refused row and nothing is written, each refusal says which row and why.
 *
 * The rows come back from the browser: they are checked again as if typed on the rates and
 * calendar screens (RateRules, same messages). The proposal is a help, not a proof.
 */
final readonly class ImportApplier
{
    public const int MAX_ROWS = 60;

    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private OwnerAccommodationCreator $creator,
        private PricePeriodRepository $periods,
        private UnavailabilityRepository $unavailabilities,
        private PublicCalendar $publicCalendar,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param Accommodation|null   $target  an accommodation of the owner, or null for a new draft
     * @param array<string, mixed> $payload {accommodation?, periods, unavailable}
     *
     * @return Accommodation|list<array{propertyPath: string, message: string}> the accommodation
     *                                                                          written, or the refusals
     */
    public function apply(ListingImport $import, User $owner, ?Accommodation $target, array $payload): Accommodation|array
    {
        $today = $this->clock->now()->setTime(0, 0);
        $violations = [];

        $input = null;
        if (null === $target) {
            [$input, $found] = $this->accommodationInput($payload['accommodation'] ?? null);
            $violations = [...$violations, ...$found];
        }

        [$periods, $found] = $this->periods($payload['periods'] ?? [], $target, $today);
        $violations = [...$violations, ...$found];

        [$ranges, $found] = $this->ranges($payload['unavailable'] ?? [], $today);
        $violations = [...$violations, ...$found];

        if ([] !== $violations) {
            return $violations;
        }

        return $this->em->wrapInTransaction(function () use ($import, $owner, $target, $input, $periods, $ranges): Accommodation|array {
            if (null === $target) {
                \assert($input instanceof CreateAccommodationInput);
                try {
                    $target = $this->creator->create($owner, $input);
                } catch (UnprocessableEntityHttpException $e) {
                    return [['propertyPath' => 'accommodation.district', 'message' => $e->getMessage()]];
                }
            }

            foreach ($periods as $row) {
                $period = new PricePeriod($target, $row['start'], $row['end'], $row['minimumNights']);
                $period->setWeeklyPrice($row['weeklyPrice'])
                    ->setNightlyPrice($row['nightlyPrice'])
                    ->setSaturdayArrival($row['saturdayArrival']);
                $this->em->persist($period);
            }

            // Dates already unavailable in his calendar stay as they are: only the rest is added.
            foreach ($ranges as [$start, $end]) {
                foreach ($this->freeParts($target, $start, $end) as [$from, $to]) {
                    $this->em->persist(new Unavailability($target, $from, $to, UnavailabilitySource::Import));
                }
            }

            $import->markApplied($target, $this->clock->now());
            $this->em->flush();

            if ([] !== $ranges) {
                $this->publicCalendar->invalidate($target);
            }

            return $target;
        });
    }

    /**
     * @return array{0: CreateAccommodationInput|null, 1: list<array{propertyPath: string, message: string}>}
     */
    private function accommodationInput(mixed $data): array
    {
        if (!\is_array($data)) {
            return [null, [['propertyPath' => 'accommodation', 'message' => 'Choisissez le logement à remplir.']]];
        }

        $input = new CreateAccommodationInput();
        $input->resort = self::stringOrNull($data['resort'] ?? null);
        $input->type = self::stringOrNull($data['type'] ?? null);
        $input->capacity = self::intOrNull($data['capacity'] ?? null);
        $input->bedrooms = self::intOrNull($data['bedrooms'] ?? null);
        $input->surface = self::intOrNull($data['surface'] ?? null);
        $input->district = self::stringOrNull($data['district'] ?? null);
        $input->description = \is_string($data['description'] ?? null) ? trim($data['description']) : '';
        $input->amenities = \is_array($data['amenities'] ?? null) ? array_values(array_filter($data['amenities'], \is_string(...))) : [];
        $input->petsPolicy = \is_string($data['petsPolicy'] ?? null) ? $data['petsPolicy'] : 'on_request';

        $violations = [];
        foreach ($this->validator->validate($input) as $violation) {
            $violations[] = ['propertyPath' => 'accommodation.'.$violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        return [$input, $violations];
    }

    /**
     * @return array{0: list<array{start: \DateTimeImmutable, end: \DateTimeImmutable, weeklyPrice: int|null, nightlyPrice: int|null, minimumNights: int, saturdayArrival: bool}>, 1: list<array{propertyPath: string, message: string}>}
     */
    private function periods(mixed $rows, ?Accommodation $target, \DateTimeImmutable $today): array
    {
        if (!\is_array($rows) || !array_is_list($rows) || \count($rows) > self::MAX_ROWS) {
            return [[], [['propertyPath' => 'periods', 'message' => \sprintf('Liste de tarifs illisible (%d au plus).', self::MAX_ROWS)]]];
        }

        $valid = [];
        $violations = [];

        foreach ($rows as $i => $row) {
            $row = \is_array($row) ? $row : [];
            $start = RateRules::date($row['start'] ?? null);
            $end = RateRules::date($row['end'] ?? null);
            $weekly = $row['weeklyPrice'] ?? null;
            $nightly = $row['nightlyPrice'] ?? null;
            $minimum = $row['minimumNights'] ?? 1;
            $saturday = $row['saturdayArrival'] ?? false;

            $refusal = RateRules::refusal($start, $end, $weekly, $nightly, $minimum, $saturday, $today);
            if (null !== $refusal) {
                $violations[] = ['propertyPath' => \sprintf('periods[%d].%s', $i, $refusal[0]), 'message' => $refusal[1]];

                continue;
            }
            \assert(null !== $start && null !== $end && \is_int($minimum) && \is_bool($saturday));
            \assert((null === $weekly || \is_int($weekly)) && (null === $nightly || \is_int($nightly)));

            foreach ($valid as $j => $other) {
                if ($start < $other['end'] && $end > $other['start']) {
                    $violations[] = ['propertyPath' => \sprintf('periods[%d].start', $i), 'message' => \sprintf('Ces dates chevauchent la période n° %d : gardez-en une seule.', $j + 1)];

                    continue 2;
                }
            }

            if (null !== $target && $this->periods->hasOverlap($target, $start, $end)) {
                $violations[] = ['propertyPath' => \sprintf('periods[%d].start', $i), 'message' => 'Ces dates chevauchent une période déjà dans vos tarifs : modifiez-la depuis la page Tarifs, ou décochez cette ligne.'];

                continue;
            }

            $valid[$i] = ['start' => $start, 'end' => $end, 'weeklyPrice' => $weekly, 'nightlyPrice' => $nightly, 'minimumNights' => $minimum, 'saturdayArrival' => $saturday];
        }

        return [array_values($valid), $violations];
    }

    /**
     * @return array{0: list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>, 1: list<array{propertyPath: string, message: string}>}
     */
    private function ranges(mixed $rows, \DateTimeImmutable $today): array
    {
        if (!\is_array($rows) || !array_is_list($rows) || \count($rows) > self::MAX_ROWS) {
            return [[], [['propertyPath' => 'unavailable', 'message' => \sprintf('Liste de dates illisible (%d au plus).', self::MAX_ROWS)]]];
        }

        $ranges = [];
        $violations = [];

        foreach ($rows as $i => $row) {
            $row = \is_array($row) ? $row : [];
            $start = RateRules::date($row['start'] ?? null);
            $end = RateRules::date($row['end'] ?? null);

            $refusal = match (true) {
                null === $start => ['start', 'Choisissez le premier jour indisponible.'],
                null === $end => ['end', 'Choisissez le jour où le logement redevient libre.'],
                $end <= $start => ['end', 'Le jour de fin doit venir après le jour de début.'],
                $end > $today->modify(ImportProposal::CALENDAR_HORIZON) => ['end', 'Le calendrier s’arrête à 18 mois : décochez cette ligne.'],
                default => null,
            };
            if (null !== $refusal) {
                $violations[] = ['propertyPath' => \sprintf('unavailable[%d].%s', $i, $refusal[0]), 'message' => $refusal[1]];

                continue;
            }

            // Past days are not blocked: nobody can book them any more.
            if ($end > $today) {
                $ranges[] = new ExtractedUnavailability(max($start, $today), $end);
            }
        }

        return [
            array_map(static fn (ExtractedUnavailability $r): array => [$r->start, $r->end], ExtractedUnavailability::merged($ranges)),
            $violations,
        ];
    }

    /**
     * The days of [start, end) not already unavailable in the accommodation's calendar.
     *
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    private function freeParts(Accommodation $accommodation, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        // A new draft has nothing in the database yet: the query would find nothing anyway.
        if (!$this->em->contains($accommodation) || $this->em->getUnitOfWork()->isScheduledForInsert($accommodation)) {
            return [[$start, $end]];
        }

        $parts = [];
        $cursor = $start;

        foreach ($this->unavailabilities->findForPeriod($accommodation, $start, $end) as $taken) {
            if ($taken->getStartDate() > $cursor) {
                $parts[] = [$cursor, min($taken->getStartDate(), $end)];
            }
            $cursor = max($cursor, $taken->getEndDate());
        }

        if ($cursor < $end) {
            $parts[] = [$cursor, $end];
        }

        return $parts;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }
}
