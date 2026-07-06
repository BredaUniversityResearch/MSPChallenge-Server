<?php

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

#[AsMiddleware(priority: 100)]
final class ConnectionTrackerMiddleware implements Middleware, ConnectionNameAwareInterface
{
    private string $connectionName = 'default';

    public function setConnectionName(string $name): void
    {
        $this->connectionName = $name;
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->connectionName) extends AbstractDriverMiddleware {
            public function __construct(Driver $wrappedDriver, private readonly string $connectionName)
            {
                parent::__construct($wrappedDriver);
            }

            public function connect(array $params): DriverConnection
            {
                $connection = parent::connect($params);

                return new class($connection, $params, $this->connectionName) extends AbstractConnectionMiddleware {
                    private bool $tracked = false;

                    public function __construct(
                        DriverConnection $wrappedConnection,
                        private readonly array $params,
                        private readonly string $connectionName,
                    ) {
                        parent::__construct($wrappedConnection);
                        // Track immediately on connection creation to catch commands that don't execute queries
                        $this->trackConnection();
                    }

                    private function trackConnection(): void
                    {
                        if ($this->tracked) {
                            return;
                        }

                        $this->tracked = true;
                        DbalConnectionTracker::trackDriverConnection(
                            $this,
                            $this->params['dbname'] ?? null,
                            $this->connectionName,
                        );
                    }
                };
            }
        };
    }
}
