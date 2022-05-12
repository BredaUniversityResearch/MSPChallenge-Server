<?php

namespace App\Domain\Common;

use App\Domain\API\v1\GameSession;
use App\Domain\API\v1\Log;
use App\Domain\API\v1\Security;
use App\Domain\Helper\AsyncDatabase;
use Drift\DBAL\Connection;
use Exception;
use React\EventLoop\Loop;

abstract class CommonBase
{
    private ?Connection $asyncDatabase = null;
    private ?int $gameSessionId = null;
    private ?string $token = null;
    private bool $async = false;
    private ?Log $logger = null;

    /**
     * @throws Exception
     */
    protected function getAsyncDatabase(): Connection
    {
        if ($this->async === false) {
            throw new Exception('Attempt to retrieve async database although async is disabled');
        }

        if (null === $this->asyncDatabase) {
            // fail-safe: try to create an async database from current request information if there is no instance set.
            if (GameSession::INVALID_SESSION_ID === $gameSessionId = $this->getGameSessionId()) {
                throw new Exception('Missing required async database connection.');
            }
            $this->asyncDatabase = AsyncDatabase::createGameSessionConnection(Loop::get(), $gameSessionId);
        }
        return $this->asyncDatabase;
    }

    public function setAsyncDatabase(Connection $asyncDatabase): void
    {
        $this->asyncDatabase = $asyncDatabase;
    }

    public function getGameSessionId(): int
    {
        // Use the game session that was "set" to this instance
        if ($this->gameSessionId != null) {
            return $this->gameSessionId;
        }
        // Otherwise, try to retrieve the game session id from the request
        return GameSession::GetGameSessionIdForCurrentRequest();
    }

    public function setGameSessionId(?int $gameSessionId): void
    {
        $this->gameSessionId = $gameSessionId;
    }

    public function getToken(): ?string
    {
        // Use the token that was "set" to this instance
        if ($this->token != null) {
            return $this->token;
        }
        // Otherwise, try to retrieve the token from the request
        return Security::findAuthenticationHeaderValue();
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function isAsync(): bool
    {
        return $this->async;
    }

    public function setAsync(bool $async): void
    {
        $this->async = $async;
    }

    /**
     * @throws Exception
     */
    public function asyncDataTransferTo(CommonBase $other)
    {
        $other->setGameSessionId($this->getGameSessionId());
        if ($this->isAsync()) {
            $other->setAsyncDatabase($this->getAsyncDatabase());
        }
        $other->setToken($this->getToken());
        $other->setAsync($this->isAsync());
    }

    /**
     * @throws Exception
     */
    public function getLogger(): Log
    {
        if (null == $this->logger) {
            $this->logger = new Log();
            $this->asyncDataTransferTo($this->logger);
        }
        return $this->logger;
    }
}
