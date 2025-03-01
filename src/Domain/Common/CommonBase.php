<?php

namespace App\Domain\Common;

use App\Domain\API\v1\Database;
use App\Domain\API\v1\GameSession;
use App\Domain\API\v1\Log;
use App\Domain\API\v1\Security;
use App\Domain\Common\Stopwatch\Stopwatch;
use App\Domain\Services\ConnectionManager;
use App\Domain\Log\LogContainerInterface;
use App\Domain\Log\LogContainerTrait;
use Drift\DBAL\Connection;
use Drift\DBAL\Result;
use Exception;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\await;
use function App\resolveOnFutureTick;

abstract class CommonBase implements LogContainerInterface
{
    use LogContainerTrait;

    private ?string $watchdog_address = null;
    const DEFAULT_WATCHDOG_PORT = 45000;

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
            if (GameSession::INVALID_SESSION_ID === $gameSessionId = $this->getGameSessionId()) {
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
        return GameSession::GetGameSessionIdForCurrentRequest();
    }

    public function setGameSessionId(?int $gameSessionId): static
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
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

    public function setAsync(bool $async): static
    {
        $this->async = $async;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function asyncDataTransferTo(CommonBase $other)
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

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetWatchdogAddress(): string
    {
        $this->watchdog_address ??= ($_ENV['WATCHDOG_ADDRESS'] ?? $this->getWatchdogAddressFromDb());
        if (null === $this->watchdog_address) {
            return '';
        }
        /** @noinspection HttpUrlsUsage */
        $this->watchdog_address = 'http://'.preg_replace('~^https?://~', '', $this->watchdog_address);
        return $this->watchdog_address.':'.($_ENV['WATCHDOG_PORT'] ?? self::DEFAULT_WATCHDOG_PORT);
    }

    private function getWatchdogAddressFromDb(): ?string
    {
        $result = $this->getDatabase()->query(
            "SELECT game_session_watchdog_address FROM game_session LIMIT 0,1"
        );
        if (count($result) == 0) {
            return null;
        }
        return $result[0]['game_session_watchdog_address'];
    }

    /**
     * @throws Exception
     */
    protected function getWatchdogSessionUniqueToken(): PromiseInterface
    {
        return $this->getAsyncDatabase()->query(
            $this->getAsyncDatabase()->createQueryBuilder()
                ->select('game_session_watchdog_token')
                ->from('game_session')
                ->setMaxResults(1)
        )
        ->then(function (Result $result) {
            $row = $result->fetchFirstRow();
            return $row['game_session_watchdog_token'] ?? '0';
        });
    }

    /**
     * @throws Exception
     */
    protected function logWatchdogResponse(string $requestName, ResponseInterface $response): PromiseInterface
    {
        $log = new Log();
        $this->asyncDataTransferTo($log);
        $log->setAsync(true); // force async in this context

        $responseContent = $response->getBody()->getContents();
        $decodedResponse = json_decode($responseContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $funcArgs = [
                "Watchdog",
                Log::ERROR,
                "Received invalid response from watchdog. Response: \"" . $responseContent . "\"",
                "..."
            ];
            return $log->postEvent(...$funcArgs)->then(function () use ($funcArgs) {
                return $funcArgs;
            });
        }

        if ($decodedResponse["success"] != 1) {
            $funcArgs = [
                "Watchdog",
                Log::ERROR,
                "Watchdog responded with failure on requesting $requestName. Response: \"" .
                $decodedResponse["message"] . "\"",
                "..."
            ];
            return $log->postEvent(...$funcArgs)->then(function () use ($funcArgs) {
                return $funcArgs;
            });
        }

        return resolveOnFutureTick(new Deferred(), $decodedResponse)->promise();
    }
}
