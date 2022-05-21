<?php

namespace App\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;

final class MultiDbConnectionWrapper extends Connection
{
    /**
     * @var array<string,mixed>
     */
    private $params;

    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
       // $params['dbname'] ??= 'msp_session_1'; // the default
        $this->params = $params;
        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @throws Exception
     */
    public function selectDatabase(string $dbName): void
    {
        if ($this->isConnected()) {
            $this->close();
        }
        $this->params['dbname'] = $dbName;
        parent::__construct($this->params, $this->_driver, $this->_config, $this->_eventManager);
    }
}
