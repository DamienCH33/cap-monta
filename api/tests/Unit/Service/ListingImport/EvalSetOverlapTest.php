<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Service\ListingImport\Evaluation\EvalSetOverlap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A control set must hold listings never seen while tuning, even when one was republished under
 * another address with a few words changed.
 */
final class EvalSetOverlapTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/eval-overlap-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/cases', recursive: true);
        mkdir($this->root.'/holdout');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->root);
    }

    public function testARepublishedListingIsTheSameListing(): void
    {
        $text = "Mobil home 35 m2, 2 chambres, terrasse couverte de 18 m2, grande parcelle entourée d'une haie. Lave linge, lave vaisselle, cafetière Senseo, 2 vélos à disposition. 700 € en juillet et août, 400 € en septembre et octobre.";
        $this->write('cases/15-ecureuil.json', 'https://example.org/annonce-a', $text);
        $this->write('holdout/v01-ecureuil.json', 'https://example.org/annonce-b', str_replace(['700 €', '2 vélos à disposition'], ['750 €', ''], $text));
        $this->write('holdout/v02-autre.json', 'https://example.org/annonce-c', 'Bungalow Floride, 3 couchages, tout électrique, 2 transats, disponible à partir du samedi 29 août.');
        $this->write('holdout/v03-meme-adresse.json', 'https://example.org/annonce-a/', 'Un tout autre texte, la même adresse.');

        $shared = EvalSetOverlap::shared($this->root.'/holdout', $this->root.'/cases');

        self::assertSame(['v01-ecureuil.json', 'v03-meme-adresse.json'], array_keys($shared));
    }

    public function testTwoListingsOfTheSameOwnerAreNot(): void
    {
        $template = 'Caution de 300€, un acompte de 30% vous sera demandé lors de la réservation. Draps et linge de toilette non fournis, possibilité de les louer auprès des clés de Nat qui vous accueillera. En sus les frais de séjour à régler au CHM.';
        $a = EvalSetOverlap::words('Mobil home 3 chambres, emplacement La Lande 987, proche de la piscine. Cuisine équipée, four micro-onde, cafetière à filtre, bouilloire. '.$template);
        $b = EvalSetOverlap::words('Bungalow typique, emplacement Atlantique 46, grande terrasse couverte, plancha, douche extérieure, lave linge, gazinière et four. '.$template);

        self::assertLessThan(EvalSetOverlap::SAME_LISTING, EvalSetOverlap::similarity($a, $b));
    }

    private function write(string $path, string $source, string $text): void
    {
        file_put_contents($this->root.'/'.$path, json_encode(['id' => basename($path, '.json'), 'source' => $source, 'text' => $text], \JSON_THROW_ON_ERROR));
    }
}
