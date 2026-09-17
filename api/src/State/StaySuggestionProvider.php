<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StaySuggestionResource;
use App\Enum\Resort;
use App\Repository\AccommodationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<StaySuggestionResource>
 */
final class StaySuggestionProvider implements ProviderInterface
{
    public function __construct(
        private readonly AccommodationRepository $accommodationRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<StaySuggestionResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];

        $arrival = $this->parseDate($filters['arrival'] ?? null, 'arrival');
        $departure = $this->parseDate($filters['departure'] ?? null, 'departure');

        if ($departure <= $arrival) {
            throw new BadRequestHttpException('"departure" must be after "arrival".');
        }

        $guestsValue = $filters['guests'] ?? null;
        $guests = is_numeric($guestsValue) ? max(1, (int) $guestsValue) : 1;

        $resortValue = $filters['resort'] ?? null;
        $resort = is_string($resortValue) ? Resort::tryFrom($resortValue) : null;

        $districtValue = $filters['district'] ?? null;
        $district = is_string($districtValue) && '' !== $districtValue ? $districtValue : null;

        $rows = $this->accommodationRepository->findNearestAvailableStays(
            $arrival,
            $departure,
            $this->clock->now(),
            $guests,
            $resort,
            $district,
        );

        return array_map(
            static fn (array $row): StaySuggestionResource => new StaySuggestionResource(
                $row['arrival'],
                $row['departure'],
                $row['available_count'],
            ),
            $rows,
        );
    }

    private function parseDate(mixed $value, string $name): \DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('Missing "%s" parameter.', $name));
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException(sprintf('Invalid "%s" date, expected YYYY-MM-DD.', $name));
        }

        return $date;
    }
}
