<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Base des tests qui touchent la base : schéma propre et factories disponibles.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    use Factories;
    use ResetDatabase;
}
