<?php

namespace App\Domain\Common;

use App\Domain\API\v1\Database;
use App\Domain\API\v1\GameSession;
use App\Domain\API\v1\Log;
use App\Domain\Common\Stopwatch\Stopwatch;
use App\Domain\Log\LogContainerInterface;
use App\Domain\Log\LogContainerTrait;
use App\Domain\Services\ConnectionManager;
use Drift\DBAL\Connection;
use Exception;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use function App\await;
use function App\resolveOnFutureTick;

abstract class CommonBase implements LogContainerInterface
{
    const INVALID_SESSION_ID = -1;

    use LogContainerTrait;

    private ?Connection $asyncDatabase = null;
    private ?int $gameSessionId = null;
    private ?string $token = null;
    private bool $async = false;
    private ?Log $logger = null;

    private ?Stopwatch $stopwatch = null;
    protected function getDatabase(): Database
    {
        return Database::GetInstance($this->getGameSessionId());
    }

    /**
     * @throws Exception
     */
    protected function getAsyncDatabase(): Connection
    {
        if (null === $this->asyncDatabase) {
            // fail-safe: try to create an async database from current request information if there is no instance set.
            if (self::INVALID_SESSION_ID === $gameSessionId = $this->getGameSessionId()) {
                throw new Exception('Missing required game session id for creating an async database connection');
            }
            $this->asyncDatabase = ConnectionManager::getInstance()->getCachedAsyncGameSessionDbConnection(
                Loop::get(),
                $gameSessionId
            );
            // another fail-safe:
            // once a loop is created, ReactPHP expects it to be been run at least once before exiting the script,
            //  otherwise it will assume it still needs to be run, causing an api web request to end in an endless loop.
            // So, to prevent this, and in case "await" is not called elsewhere,
            //   just run an "empty" async task to be resolved in a single tick, and await it, before continuing
            await(resolveOnFutureTick(new Deferred())->promise());
        }
        return $this->asyncDatabase;
    }

    public function setAsyncDatabase(Connection $asyncDatabase): static
    {
        $this->asyncDatabase = $asyncDatabase;
        return $this;
    }

    public function getGameSessionId(): int
    {
        // Use the game session that was "set" to this instance
        if ($this->gameSessionId != null) {
            return $this->gameSessionId;
        }
        // Otherwise, try to retrieve the game session id from the request
        return self::GetGameSessionIdForCurrentRequest();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetGameSessionIdForCurrentRequest(): int
    {
        $sessionId = $_POST['game_id'] ?? $_GET['session'] ?? self::INVALID_SESSION_ID;
        if (1 === preg_match('/^\/(?:\/)*(\d+)\/(?:\/)*api\/(?:\/)*(.*)/', $_SERVER["REQUEST_URI"], $matches)) {
            $sessionId = (int)$matches[1];
        }
        return intval($sessionId) < 1 ? self::INVALID_SESSION_ID : (int)$sessionId;
    }

    public function setGameSessionId(?int $gameSessionId): static
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
    }

    public function getToken(): ?string
    {
            return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function isAsync(): bool
    {
        return $this->async;
    }

    public function setAsync(bool $async): static
    {
        $this->async = $async;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function asyncDataTransferTo(CommonBase $other): void
    {
        $other->setGameSessionId($this->getGameSessionId());
        $other->setAsyncDatabase($this->getAsyncDatabase());
        $other->setToken($this->getToken());
        $other->setAsync($this->isAsync());
        $other->setStopwatch($this->getStopwatch());
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

    public function getStopwatch(): ?Stopwatch
    {
        return $this->stopwatch;
    }

    public function setStopwatch(?Stopwatch $stopwatch): static
    {
        $this->stopwatch = $stopwatch;
        return $this;
    }
}
