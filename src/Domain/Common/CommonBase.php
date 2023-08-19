<?php

namespace App\Domain\Common;

use App\Domain\API\v1\Database;
use App\Domain\API\v1\GameSession;
use App\Domain\API\v1\Log;
use App\Domain\API\v1\Security;
use App\Domain\Services\ConnectionManager;
use Doctrine\DBAL\ParameterType;
use Drift\DBAL\Connection;
use Drift\DBAL\Result;
use Exception;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\await;
use function App\resolveOnFutureTick;

abstract class CommonBase
{
    private ?Connection $asyncDatabase = null;
    private ?int $gameSessionId = null;
    private ?string $token = null;
    private bool $async = false;
    private ?Log $logger = null;
    private ?string $projectDir = null;

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

    /**
     * returns the base API endpoint. e.g. http://localhost/1/
     * moved from GameSession class, since that class will be deprecated
     * and this function makes more sense in CommonBase anyway
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetRequestApiRoot(): string
    {
        return await($this->getRequestApiRootAsync());
    }

    /**
     * used to communicate "game_session_api" URL to the watchdog
     * and see previous function GetRequestApiRoot
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function getRequestApiRootAsync(): PromiseInterface
    {
        if (isset($GLOBALS['RequestApiRoot'])) {
            $deferred = new Deferred();
            return resolveOnFutureTick($deferred, $GLOBALS['RequestApiRoot'])->promise();
        }
        /*$apiRoot = preg_replace('/(.*)\/(api|_profiler)\/(.*)/', '$1/', $_SERVER["REQUEST_URI"]);
        $apiRoot = str_replace("//", "/", $apiRoot);*/
        // this can be so much simpler...
        $apiRoot = '/'.$this->getGameSessionId().'/';

        $_SERVER['HTTPS'] ??= 'off';
        /** @noinspection HttpUrlsUsage */
        $protocol = ($_SERVER['HTTPS'] == 'on') ? "https://" : ($_ENV['URL_WEB_SERVER_SCHEME'] ?? "http://");

        $connection = ConnectionManager::getInstance()->getCachedAsyncServerManagerDbConnection(Loop::get());
        return $connection->query(
            $connection->createQueryBuilder()
                ->select('address')
                ->from('game_servers')
                ->setMaxResults(1)
        )
            ->then(
                function (Result $result) use ($protocol, $apiRoot) {
                    $row = $result->fetchFirstRow() ?? [];
                    $serverName = $_ENV['URL_WEB_SERVER_HOST'] ?? $row['address'] ?? $_SERVER["SERVER_NAME"] ??
                        gethostname();
                    $port = ':' . ($_ENV['URL_WEB_SERVER_PORT'] ?? 80);
                    $GLOBALS['RequestApiRoot'] = $protocol.$serverName.$port.$apiRoot;
                    return $GLOBALS['RequestApiRoot'];
                }
            );
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
        $other->setAsyncDatabase($this->getAsyncDatabase());
        $other->setToken($this->getToken());
        $other->setAsync($this->isAsync());
        $other->setProjectDir($this->getProjectDir());
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

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function selectRowsFromTable(
        string $table,
        array $whereColumnData = [],
        bool $forceMultiRow = false
    ): array|null|PromiseInterface {
        if (count($whereColumnData) == 2) {
            throw new \Exception(
                'whereColumnData needs to contain 1 or at least 3 elements: and/or, and 2+ columns-value pairs.'
            );
        } elseif (count($whereColumnData) > 2 &&
            (empty($whereColumnData[0]) || ($whereColumnData[0] != 'or' && $whereColumnData[0] != 'and'))
        ) {
            throw new \Exception('with multiple elements, whereColumnData 1st needs to be "or" or "and".');
        }
        $andOrWhere = 'orWhere';
        if (!empty($whereColumnData[0])) {
            $andOrWhere = $whereColumnData[0].'Where';
            array_shift($whereColumnData);
        }
        $firstLetter = substr($table, 0, 1);
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $query = $qb->select($firstLetter.'.*')
                    ->from($table, $firstLetter);
        $firstWhereColumn = true;
        foreach ($whereColumnData as $key => $val) {
            if ($firstWhereColumn) {
                $query->where($qb->expr()->eq($firstLetter.'.'.$key, $qb->createPositionalParameter($val)));
                $firstWhereColumn = false;
            } else {
                $query->$andOrWhere($qb->expr()->eq($firstLetter.'.'.$key, $qb->createPositionalParameter($val)));
            }
        }
        $promise = $this->getAsyncDatabase()->query($query)->then(function (Result $result) use ($forceMultiRow) {
            $allRows = $result->fetchAllRows();
            if (empty($allRows)) {
                return null;
            }
            if (count($allRows) == 1 && !$forceMultiRow) {
                return $allRows[0];
            }
            return $allRows;
        });
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function insertRowIntoTable(string $table, array $columns): int|null|PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $query = $qb->insert($table);
        foreach ($columns as $column => $value) {
            if (!str_ends_with($value, '()')) { // allowing SQL functions
                $columns[$column] = $qb->createPositionalParameter($value);
            }
        }
        $query->values($columns);
        $promise = $this->getAsyncDatabase()->query($query)
            ->then(function (Result $result) {
                return $result->getLastInsertedId();
            });
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function updateRowInTable(
        string|array $table,
        array $columns,
        array $whereColumnData
    ): int|null|PromiseInterface {
        return $this->updateOrDeleteRowInTable('update', $table, $whereColumnData, $columns);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function deleteRowFromTable(
        string|array $table,
        array $whereColumnData
    ): int|null|PromiseInterface {
        return $this->updateOrDeleteRowInTable('delete', $table, $whereColumnData);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    private function updateOrDeleteRowInTable(
        string $updateOrDelete,
        string|array $table,
        array $whereColumnData,
        array $columns = []
    ): int|null|PromiseInterface {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        if (is_array($table)) {
            if (count($table) != 2) {
                throw new \Exception('Table arrays need to contain exactly two values: name and alias');
            }
            $query = $qb->$updateOrDelete($table[0], $table[1]);
        } else {
            $query = $qb->$updateOrDelete($table);
        }
        foreach ($columns as $column => $value) {
            if (str_ends_with($value, '()') ||
                (is_array($table) && isset($table[1]) && substr($value, 1, 1) == '.' &&
                    substr($value, 0, 1) == $table[1])
            ) {
                $query->set($column, $value); // allowing SQL functions and column references in SET clause
            } else {
                $query->set($column, $qb->createPositionalParameter($value));
            }
        }
        if (is_null(current($whereColumnData))) {
            $query->where($qb->expr()->isNull(key($whereColumnData)));
        } else {
            $query->where($qb->expr()->eq(
                key($whereColumnData),
                $qb->createPositionalParameter(current($whereColumnData))
            ));
        }
        $promise = $this->getAsyncDatabase()->query($query)
            ->then(function (Result $result) {
                return $result->getAffectedRows();
            });
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @return string|null
     */
    public function getProjectDir(): ?string
    {
        return $this->projectDir;
    }

    /**
     * @param string|null $projectDir
     */
    public function setProjectDir(?string $projectDir): self
    {
        $this->projectDir = $projectDir;

        return $this;
    }
}
