<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Every date of the site is a date at the resorts: "today" changes at midnight in France,
     * not at midnight UTC (the server's clock on Railway).
     */
    public function boot(): void
    {
        date_default_timezone_set('Europe/Paris');

        parent::boot();
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
