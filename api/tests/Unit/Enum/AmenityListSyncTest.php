<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Amenity;
use PHPUnit\Framework\TestCase;

/**
 * The equipment list exists twice: the API validates with the enum, the front displays its
 * labels. A key added on one side only would be rejected (422) or never offered.
 */
final class AmenityListSyncTest extends TestCase
{
    private const string FRONT_LIST = __DIR__.'/../../../../web/src/app/core/models/search-filters.ts';

    public function testTheFrontAndTheApiOfferTheSameEquipment(): void
    {
        if (!is_file(self::FRONT_LIST)) {
            self::markTestSkipped('Front absent (API seule).');
        }

        $source = (string) file_get_contents(self::FRONT_LIST);
        // Le libellé est traduit : `label: $localize`:@@amenity.x:Libellé``.
        preg_match_all("/\\{ key: '([a-z-]+)', label: [^,]+, group: '[a-z]+'/", $source, $matches);

        $front = $matches[1];
        $api = Amenity::values();
        sort($front);
        sort($api);

        self::assertSame($api, $front);
    }
}
