<?php

declare(strict_types=1);

namespace App\Service\Accommodation;

use App\Enum\AccommodationType;
use App\Repository\AccommodationRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Builds a readable, SEO-friendly slug: "mobil-home-europa-6-personnes",
 * then "-2", "-3" when it is taken.
 *
 * Set once at creation and never changed afterwards: an accommodation's URL
 * gets pasted in listings and messages, it must not break.
 */
final readonly class AccommodationSlugger
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @param string $place district slug, or resort when there is no district
     */
    public function generate(AccommodationType $type, string $place, int $capacity): string
    {
        $label = match ($type) {
            AccommodationType::Caravan => 'caravane',
            AccommodationType::MobileHome => 'mobil-home',
            AccommodationType::Bungalow => 'bungalow',
        };

        $base = $this->slugger->slug(sprintf('%s %s %d personnes', $label, $place, $capacity))->lower()->toString();

        $slug = $base;
        $suffix = 2;

        while (null !== $this->accommodations->findOneBy(['slug' => $slug])) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
