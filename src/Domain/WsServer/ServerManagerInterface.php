<?php

namespace App\Domain\WsServer;

use Drift\DBAL\Connection;
use React\Promise\PromiseInterface;

interface ServerManagerInterface
{
    public function getGameSessionIds(): PromiseInterface;
    public function getAsyncDatabase(int $gameSessionId): Connection;
}
