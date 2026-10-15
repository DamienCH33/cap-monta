<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Every connection talks in the time zone of the site (set in Kernel::boot): PostgreSQL
 * returns the TIMESTAMP WITH TIME ZONE columns in local time (+01:00 / +02:00, as the API
 * always answered), and reads a date-time written without offset, in a query or a parameter
 * typed by Doctrine, as local time too. The database server itself (Railway) runs in UTC.
 */
final class SessionTimeZoneMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                $connection->exec('SET TIME ZONE '.$connection->quote(date_default_timezone_get()));

                return $connection;
            }
        };
    }
}
