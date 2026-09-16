<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Base des tests qui passent par HTTP : client, base propre, factories disponibles.
 */
abstract class ApiTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;
}
