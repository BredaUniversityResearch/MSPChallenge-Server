<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Common\MSPBrowserFactory;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\Services\SymfonyToLegacyHelper;
use Closure;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Exception;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
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
        $andExpressions = [];
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
            ->then(function (string $apiRoot) use ($newWatchdogGameState) {
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
                $simulationsHelper = new Simulations();
                $this->asyncDataTransferTo($simulationsHelper);
                $simulations = json_encode($simulationsHelper->GetConfiguredSimulationTypes(), JSON_FORCE_OBJECT);
                $tokens = $simulationsHelper->GetTokensForWatchdog();
                $newAccessToken = json_encode([
                    'token' => $tokens['token'],
                    'valid_until' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s')
                ]);
                $recoveryToken = json_encode([
                    'token' => $tokens['api_refresh_token'],
                    'valid_until' => \DateTime::createFromFormat('U', $tokens['exp'])->format('Y-m-d H:i:s')
                ]);
                return $this->getWatchdogSessionUniqueToken()
                    ->then(function (string $watchdogSessionUniqueToken) use (
                        $simulations,
                        $apiRoot,
                        $newWatchdogGameState,
                        $newAccessToken,
                        $recoveryToken
                    ) {
                        // note(MH): GetWatchdogAddress is not async, but it is cached once it
                        //   has been retrieved once, so that's "fine"
                        $url = $this->GetWatchdogAddress()."/Watchdog/UpdateState";
                        $browser = MSPBrowserFactory::create($url);
                        $game = new Game();
                        $this->asyncDataTransferTo($game);
                        $postValues = [
                            'game_session_api' => $apiRoot,
                            'game_session_token' => $watchdogSessionUniqueToken,
                            'game_state' => $newWatchdogGameState,
                            'required_simulations' => $simulations,
                            'api_access_token' => $newAccessToken,
                            'api_access_renew_token' => $recoveryToken,
                            'month' => $game->GetCurrentMonthAsId()
                        ];
                        return $browser->post(
                            $url,
                            [
                                'Content-Type' => 'application/x-www-form-urlencoded'
                            ],
                            http_build_query($postValues)
                        );
                    });
            })
            ->then(function (ResponseInterface $response) {
                return $this->logWatchdogResponse("/Watchdog/UpdateState", $response);
            });
    }
}
