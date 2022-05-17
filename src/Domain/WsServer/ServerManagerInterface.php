<?php

namespace App\Domain\WsServer;

use Drift\DBAL\Connection;
use React\Promise\PromiseInterface;

interface ServerManagerInterface
{
    public function getGameSessionIds(bool $onlyPlaying = false): PromiseInterface;
    public function getGameSessionDbConnection(int $gameSessionId): Connection;
    public function getServerManagerDbConnection(): Connection;
}
