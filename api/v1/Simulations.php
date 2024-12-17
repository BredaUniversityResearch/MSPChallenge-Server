<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Common\MSPBrowserFactory;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\Simulation;
use App\Entity\Watchdog;
use App\Message\Watchdog\GameStateChangedMessage;
use App\Message\Watchdog\Token;
use Closure;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Drift\DBAL\Result;
use Exception;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\Uid\Uuid;
use function App\assertFulfilled;
use function App\tpf;

class Simulations extends Base
{
    private const ALLOWED = array(
        "GetConfiguredSimulationTypes",
        ["GetWatchdogTokenForServer", Security::ACCESS_LEVEL_FLAG_NONE],
        "GetWatchdogAddress",
        ["StartWatchdog", Security::ACCESS_LEVEL_FLAG_NONE], // nominated for full security
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Simulations
     * @throws Exception
     * @api {POST} /Simulations/GetConfiguredSimulationTypes Get Configured Simulation Types
     * @apiDescription Get Configured Simulation Types (e.g. ["MEL", "SEL", "CEL"])
     * @apiSuccess {array} Returns the type name of the simulations present in the current configuration.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetConfiguredSimulationTypes(): array
    {
        $result = array();
        $game = new Game();
        $this->asyncDataTransferTo($game);
        $config = $game->GetGameConfigValues();
        $possibleSims = SymfonyToLegacyHelper::getInstance()->getProvider()->getComponentsVersions();
        foreach ($possibleSims as $possibleSim => $possibleSimVersion) {
            if (array_key_exists($possibleSim, $config) && is_array($config[$possibleSim])) {
                $versionString = $possibleSimVersion;
                if (array_key_exists("force_version", $config[$possibleSim])) {
                    $versionString = $config[$possibleSim]["force_version"];
                }
                $result[$possibleSim] = $versionString;
            }
        }
        return $result;
    }

    /**
     * @apiGroup Simulations
     * @throws Exception
     * @api {POST} /Simulations/GetWatchdogTokenForServer Get Watchdog Token ForServer
     * @apiDescription Get the watchdog token for the current server. Used for setting up debug bridge in simulations.
     * @apiSuccess {array} with watchdog_token key and value
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetWatchdogTokenForServer(): array
    {
        $token = null;
        $data = $this->getDatabase()->query("SELECT token FROM watchdog LIMIT 0,1");
        if (count($data) > 0) {
            $token = $data[0]["token"];
        }
        return array("watchdog_token" => $token);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetTokensForWatchdog(): array
    {
        $user = new User();
        $user->setUserId(999999);
        $user->setUsername('Watchdog_' . uniqid());
        $jsonResponse = SymfonyToLegacyHelper::getInstance()->getAuthenticationSuccessHandler()
            ->handleAuthenticationSuccess($user);
        return json_decode($jsonResponse->getContent(), true);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function getSimulations(
        ?WatchdogStatus $statusFilter = null,
        ?int            $lastMonthFilter = null,
        ?float          $afterUpdateTimestamp = null,
        bool            $archived = false
    ): PromiseInterface {
        $expr = $this->getAsyncDatabase()->createQueryBuilder()->expr();
        $andExpressions = ['1=1'];
        $parameters = [];
        if ($statusFilter !== null) {
            $andExpressions[] = $expr->eq('w.status', '?');
            $parameters[] = $statusFilter->value;
        }
        if ($lastMonthFilter !== null) {
            $andExpressions[] = $expr->eq('s.last_month', $lastMonthFilter);
        }
        if ($afterUpdateTimestamp !== null) {
            $andExpressions[] = $expr->gt('UNIX_TIMESTAMP(s.updated_at)', '?');
            $parameters[] = $afterUpdateTimestamp;
        }
        return $this->findByWhere(
            $expr->and(...$andExpressions),
            $parameters,
            $archived
        );
    }

    /**
     * @description find simulations that are not yet up-to-date to the specified month
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function getUnsynchronizedSimulations(int $month): PromiseInterface
    {
        $expr = $this->getAsyncDatabase()->createQueryBuilder()->expr();
        return $this->findByWhere(
            // so the simulation is not yet up-to-date to the specified month
            $expr->and($expr->lt('s.last_month', $month))
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function findByWhere(
        CompositeExpression $where,
        array $parameters = [],
        bool $archived = false
    ) : PromiseInterface {
        $qb = $this->getAsyncDatabase()->createQueryBuilder()
            ->select('s.*')
            ->from('simulation', 's');
        if (!empty($parameters)) {
            $qb->setParameters($parameters);
        }
        $qb
            ->innerJoin('s', 'watchdog', 'w', 's.watchdog_id = w.id')
            ->andWhere($qb->expr()->eq('w.archived', $archived ? 1 : 0))
            ->andWhere($where);
        return $this->getAsyncDatabase()->query($qb);
    }

    /**
     * @ForceNoTransaction
     * @noinspection PhpUnused
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function StartWatchdog(): void
    {
        // no need to startup watchdog in docker, handled by supervisor.
        if (getenv('DOCKER') !== false) {
            return;
        }
        // below code is only necessary for Windows
        if (!str_starts_with(php_uname(), "Windows")) {
            return;
        }
        self::StartSimulationExe([
            'exe' => 'MSW.exe',
            'working_directory' => SymfonyToLegacyHelper::getInstance()->getProjectDir().'/'.(
                $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'] ?? 'simulations/.NETFramework/MSW/'
            )
        ]);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function StartSimulationExe(array $params): void
    {
        // below code is only necessary for Windows
        if (!str_starts_with(php_uname(), "Windows")) {
            return;
        }
        $args = isset($params["args"])? $params["args"]." " : "";
        $args = $args."APIEndpoint=".GameSession::GetRequestApiRoot();
        $workingDirectory = "";
        if (isset($params["working_directory"])) {
            $workingDirectory = "cd ".$params["working_directory"]." & ";
        }
        Database::execInBackground(
            'start cmd.exe @cmd /c "'.$workingDirectory.'start '.$params["exe"].' '.$args.'"'
        );
    }

    /**
     * @throws Exception
     */
    private function assureWatchdogAlive(): ?PromiseInterface
    {
        // note(MH): GetWatchdogAddress is not async, but it is cached once it has been retrieved once, so that's "fine"
        $url = $this->GetWatchdogAddress();
        if (empty($url)) {
            return null;
        }
        $deferred = new Deferred();
        $loop = Loop::get();
        $maxAttempts = 4;
        $numAttemptsLeft = $maxAttempts;
        $loop->futureTick($this->createWatchdogRequestRepeatedFunction(
            $loop,
            $deferred,
            function () use ($url) {
                return ($this->createWatchdogRequestPromiseFunction($url))();
            },
            $numAttemptsLeft,
            $maxAttempts
        ));
        return $deferred->promise();
    }

    private function createWatchdogRequestRepeatedFunction(
        LoopInterface $loop,
        Deferred $deferred,
        Closure $promiseFunction,
        int &$numAttemptsLeft,
        int $maxAttempts
    ): Closure {
        return function () use ($loop, $deferred, $promiseFunction, &$numAttemptsLeft, $maxAttempts) {
            assertFulfilled(
                $promiseFunction(),
                $this->createWatchdogRequestRepeatedOnFulfilledFunction(
                    $loop,
                    $deferred,
                    $this->createWatchdogRequestRepeatedFunction(
                        $loop,
                        $deferred,
                        $promiseFunction,
                        $numAttemptsLeft,
                        $maxAttempts
                    ),
                    $numAttemptsLeft,
                    $maxAttempts
                )
            );
        };
    }

    private function createWatchdogRequestPromiseFunction(string $url): ToPromiseFunction
    {
        return tpf(function () use ($url) {
            $browser = MSPBrowserFactory::create($url);
            $deferred = new Deferred();
            $browser
                // any response is acceptable, even 4xx or 5xx status codes
                ->withRejectErrorResponse(false)
                ->withTimeout(1)
                ->request('GET', $url)
                ->done(
                    // watchdog is running
                    function (/*ResponseInterface $response*/) use ($deferred) {
                        $deferred->resolve(true); // we have a response
                    },
                    // so the Watchdog is off, and now it should be switched on
                    function (/*Exception $e*/) use ($deferred) {
                        $deferred->resolve(false); // no response yet..
                    }
                );
            return $deferred->promise();
        });
    }

    private function createWatchdogRequestRepeatedOnFulfilledFunction(
        LoopInterface $loop,
        Deferred $deferred,
        Closure $repeatedFunction,
        int &$numAttemptsLeft,
        int $maxAttempts
    ): Closure {
        // so if the "promise function" is fulfilled, it gets repeated.
        return function (bool $requestSucceeded) use (
            $loop,
            $deferred,
            $repeatedFunction,
            &$numAttemptsLeft,
            $maxAttempts
        ) {
            if ($requestSucceeded) {
                $deferred->resolve();
                return;
            }
            if ($numAttemptsLeft == $maxAttempts) { // first attempt
                self::StartWatchdog(); // try to start watchdog if the first attempt fails.
            }
            $numAttemptsLeft--;
            if ($numAttemptsLeft <= 0) {
                $deferred->resolve();
                return; // do not repeat anymore, even if the watchdog is not alive
            }
            $loop->futureTick($repeatedFunction);
        };
    }

    /**
     * @throws DateMalformedStringException
     */
    private function createWatchdogEntityFromAssociative(array $watchdog): Watchdog
    {
        $address = $watchdog['address'];
        $port = $watchdog['port'];
        if ($watchdog['server_id'] == Watchdog::getInternalServerId()->toBinary()) {
            // prefer the environment variable over the database value for the internal watchdog
            $address = $_ENV['WATCHDOG_ADDRESS'] ?? $address;
            $port = $_ENV['WATCHDOG_PORT'] ?? $port;
        }
        $w = new Watchdog();
        $w
            ->setServerId(Uuid::fromBinary($watchdog['server_id']))
            ->setScheme($watchdog['scheme'])
            ->setAddress($address)
            ->setPort($port)
            ->setStatus(WatchdogStatus::from($watchdog['status']))
            ->setToken($watchdog['token'])
            ->setCreatedAt(new DateTime($watchdog['created_at']))
            ->setDeletedAt(new DateTime($watchdog['deleted_at']))
            ->setUpdatedAt(new DateTime($watchdog['updated_at']));
        return $w;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function createSimulationEntityFromAssociative(array $sim): Simulation
    {
        $s = new Simulation();
        $s
            ->setName($sim['name'])
            ->setVersion($sim['version'])
            ->setLastMonth($sim['last_month'])
            ->setCreatedAt(new DateTime($sim['s_created_at']))
            ->setUpdatedAt(new DateTime($sim['s_updated_at']));
        return $s;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    private function getWatchdogs(): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder()
            ->select(
                'w.*',
                's.*',
                // aliases for the columns, to be able to distinguish between the two tables
                'w.id as w_id',
                's.created_at as s_created_at',
                's.updated_at as s_updated_at'
            )
            ->from('simulation', 's')
            ->innerJoin('s', 'watchdog', 'w', 's.watchdog_id = w.id')
            ->where('w.deleted_at IS NULL'); // only active simulations
        return $this->getAsyncDatabase()->query($qb)->then(function (Result $result) {
            $sims = ($result->fetchAllRows() ?? []) ?: [];
            $watchdogs = [];
            foreach ($sims as $sim) {
                $watchdogs[$sim['w_id']] ??= $this->createWatchdogEntityFromAssociative($sim);
                $simEntity = $this->createSimulationEntityFromAssociative($sim);
                // this will also assign the watchdog to the simulation
                $watchdogs[$sim['w_id']]->getSimulations()->add($simEntity);
            }
            return $watchdogs;
        });
    }

    /**
     * @throws Exception
     */
    public function changeWatchdogState(string $newWatchdogGameState): ?PromiseInterface
    {
        if (null === $promise = $this->assureWatchdogAlive()) {
            return null;
        }
        return $promise
            ->then(function () {
                return GameSession::getRequestApiRootAsync(getenv('DOCKER') !== false);
            })
            ->then(function (string $apiRoot) {
                // set registered watchdogs to status ready
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                $qb
                    ->update('watchdog')
                    ->set('status', '?')
                    ->where($qb->expr()->eq('status', '?'))
                    ->setParameters([WatchdogStatus::READY->value, WatchdogStatus::REGISTERED->value]);
                return $this->getAsyncDatabase()->query($qb)->then(fn() => $apiRoot);
            })
            ->then(function (string $apiRoot) use ($newWatchdogGameState) {
                return $this->getWatchdogs()->then(function (array $watchdogs) use (
                    $apiRoot,
                    $newWatchdogGameState
                ) {
                    /** @var Watchdog[] $watchdogs */
                    foreach ($watchdogs as $watchdog) {
                        $simulationsHelper = new Simulations();
                        $this->asyncDataTransferTo($simulationsHelper);
                        $game = new Game();
                        $this->asyncDataTransferTo($game);
                        $tokens = $simulationsHelper->GetTokensForWatchdog();
                        $message = new GameStateChangedMessage();
                        $message
                            ->setGameSessionId($this->getGameSessionId())
                            ->setWatchdog($watchdog)
                            ->setGameSessionApi($apiRoot)
                            ->setGameState($newWatchdogGameState)
                            // sets an array keyed by the simulation name with the corresponding version
                            ->setRequiredSimulations(
                                collect($watchdog->getSimulations()->toArray())
                                    ->mapWithKeys(fn(Simulation $sim) => [$sim->getName() => $sim->getVersion()])
                                    ->toArray()
                            )
                            ->setApiAccessToken(new Token($tokens['token'], new DateTime('+1 hour')))
                            ->setApiAccessRenewToken(new Token(
                                $tokens['api_refresh_token'],
                                DateTime::createFromFormat('U', $tokens['exp'])
                            ))
                            ->setMonth($game->GetCurrentMonthAsId());
                        SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch($message);
                    }
                });
            });
    }
}
