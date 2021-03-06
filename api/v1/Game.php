<?php

namespace App\Domain\API\v1;

use App\Domain\Common\MSPBrowser;
use App\Domain\Services\SymfonyToLegacyHelper;
use Drift\DBAL\Result;
use Exception;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\resolveOnFutureTick;
use function App\await;

class Game extends Base
{
    private string $watchdog_address = '';
    const WATCHDOG_PORT = 45000;

    private const ALLOWED = array(
        "AutoSaveDatabase",
        ["Config", Security::ACCESS_LEVEL_FLAG_NONE], //Required for login
        "FutureRealtime",
        "GetActualDateForSimulatedMonth",
        "GetCountries",
        "GetCurrentMonth",
        ["GetGameDetails", Security::ACCESS_LEVEL_FLAG_NONE], // nominated for full security
        "GetWatchdogAddress",
        "IsOnline",
        "Meta",
        "NextMonth",
        "Planning",
        "Realtime",
        ["Setupfilename", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER], // nominated for full security
        "Speed",
        ["StartWatchdog", Security::ACCESS_LEVEL_FLAG_NONE], // nominated for full security
        ["State", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER]
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/AutoSaveDatabase AutoSaveDatabase
     * @apiDescription Creates a session database dump with the naming convention AutoDump_YYY-mm-dd_hh-mm.sql
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AutoSaveDatabase(): void
    {
        if (strstr($_SERVER['REQUEST_URI'], 'dev')) {
            return; //Don't create database dumps on dev.
        }

        $outputDirectory = "export/DatabaseDumps/";
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory);
        }

        $outputFile = $outputDirectory."AutoDump_".date("Y-m-d_H-i").".sql";
        Database::GetInstance($this->getGameSessionId())->createMspDatabaseDump($outputFile, false);
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/Config Config
     * @apiDescription Obtains the sessions' game configuration
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Config(): array
    {
        $data = $this->GetGameConfigValues();

        $configuredSimulations = array();
        if (isset($data['MEL'])) {
            $configuredSimulations[] = "MEL";
        }
        if (isset($data['SEL'])) {
            $configuredSimulations[] = "SEL";
        }
        if (isset($data['CEL'])) {
            $configuredSimulations[] = "CEL";
        }

        foreach ($data as $key => $d) {
            if ((is_object($d) || is_array($d)) && $key != "expertise_definitions" &&
                $key != "dependencies"
            ) {
                unset($data[$key]);
            }
        }

        $data['configured_simulations'] = $configuredSimulations;
        if (!isset($data['wiki_base_url'])) {
            $data['wiki_base_url'] = Config::GetInstance()->WikiConfig()['game_base_url'];
        }

        $passwordchecks = (new GameSession)->CheckGameSessionPasswords();
        $data["user_admin_has_password"] = $passwordchecks["adminhaspassword"];
        $data["user_common_has_password"] = $passwordchecks["playerhaspassword"];

        return $data;
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/NextMonth NextMonth
     * @apiDescription Updates session database to indicate start of next simulated month
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function NextMonth(): void
    {
        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query("UPDATE game SET game_currentmonth=game_currentmonth+1");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function LoadConfigFile(string $filename = ''): string
    {
        if ($filename == "") {    //if there's no file given, use the one in the database
            $data = Database::GetInstance($this->getGameSessionId())->query("SELECT game_configfile FROM game");

            $path = GameSession::getConfigDirectory() . $data[0]['game_configfile'];
        } else {
            $path = GameSession::getConfigDirectory() . $filename;
        }

        // 5 min cache. why 5min? Such that the websocket server will refresh the config once in a while
        static $cacheTime = null;
        static $cache = [];
        if (array_key_exists($path, $cache) &&
            ($cacheTime === null || time() - $cacheTime < 300)) {
            return $cache[$path];
        }
        $cacheTime = time();
        $cache[$path] = file_get_contents($path);
        return $cache[$path];
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetGameConfigValues(string $overrideFileName = ''): array
    {
        $data = json_decode($this->LoadConfigFile($overrideFileName), true);
        if (isset($data["datamodel"])) {
            return $data["datamodel"];
        }
        return $data ?? [];
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/GetCurrentMonth GetCurrentMonth
     * @apiDescription Gets the current month of the active game.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetCurrentMonth(): array
    {
        return array("game_currentmonth" => $this->GetCurrentMonthAsId());
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetCurrentMonthAsId(): int
    {
        $currentMonth = Database::GetInstance()->query("SELECT game_currentmonth, game_state FROM game")[0];
        if ($currentMonth["game_state"] == "SETUP") {
            $currentMonth["game_currentmonth"] = -1;
        }
        return $currentMonth["game_currentmonth"];
    }

    /**
     * @throws Exception
     * @noinspection SpellCheckingInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Setupfilename(string $configFilename): void
    {
        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query("UPDATE game SET game_configfile=?", array($configFilename));
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetupCountries(array $configData): void
    {
        $adminColor = "#FF00FFFF";
        if (array_key_exists("user_admin_color", $configData)) {
            $adminColor = $configData["user_admin_color"];
        }
        $regionManagerColor = "#00FFFFFF";
        if (array_key_exists("user_region_manager_color", $configData)) {
            $regionManagerColor = $configData["user_region_manager_color"];
        }

        //Admin country.
        Database::GetInstance()->query(
            "INSERT INTO country (country_id, country_colour, country_is_manager) VALUES (?, ?, ?)",
            array(1, $adminColor, 1)
        );
        //Region manager country.
        Database::GetInstance()->query(
            "INSERT INTO country (country_id, country_colour, country_is_manager) VALUES (?, ?, ?)",
            array(2, $regionManagerColor, 1)
        );

        foreach ($configData['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $configData['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $countryId = $country['value'];
                    Database::GetInstance()->query(
                        "
                        INSERT INTO country (country_id, country_name, country_colour, country_is_manager)
                        VALUES (?, ?, ?, ?)
                        ",
                        array($countryId, $country['displayName'], $country['polygonColor'], 0 )
                    );
                }
            }
        }
        //Setup Admin Test User so we have a default session we can use for testing.
        Database::GetInstance()->query("INSERT INTO user (user_lastupdate, user_country_id) VALUES(0, 1)");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetupGameTime(array $data): void
    {
        $_POST['user'] = 1; // should this go at some point?
        $this->SetStartDate($data['start']);

        //$_POST['months'] = $data['era_planning_months']; // this should definitely go at some point
        $this->Planning($data['era_planning_months']);

        //$_POST['realtime'] = $data['era_planning_realtime'];
        $this->Realtime($data['era_planning_realtime']);

        $str = "";

        $totalEras = 4;
        $str .= str_repeat($data['era_planning_realtime'] . ",", $totalEras);

        $str = substr($str, 0, -1);

        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query(
            "UPDATE game SET game_planning_era_realtime=?, game_eratime=?",
            array($str, $data['era_total_months'])
        );
    }

    /**
     * @apiGroup Game
     * @api {POST} /game/IsOnline Is Online
     * @apiDescription Check if the server is online
     * @apiSuccess {string} online
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function IsOnline(): string
    {
        return "online";
    }

    //this should probably be moved to Layer instead
    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/meta Meta
     * @apiDescription Get all layer meta data required for a game
     * @apiSuccess {string} JSON object
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Meta(int $user, bool $sort = false, bool $onlyActiveLayers = false)
    {
        Database::GetInstance()->query("UPDATE user SET user_lastupdate=? WHERE user_id=?", array(0, $user));

        $activeQueryPart = "";
        if ($onlyActiveLayers) {
            $activeQueryPart = " AND layer_active = 1 ";
        }

        if ($sort) {
            $data = Database::GetInstance()->query(
                "SELECT * FROM layer WHERE layer_original_id IS NULL ".$activeQueryPart." ORDER BY layer_name ASC",
                array()
            );
        } else {
            $data = Database::GetInstance()->query(
                "SELECT * FROM layer WHERE layer_original_id IS NULL ".$activeQueryPart,
                array()
            );
        }

        for ($i = 0; $i < sizeof($data); $i++) {
            Layer::FixupLayerMetaData($data[$i]);
        }

        return $data;
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/planning Planning
     * @apiParam {int} months the amount of months the planning phase takes
     * @apiDescription set the amount of months the planning phase takes, should not be done during the simulation phase
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Planning(int $months): void
    {
        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query("UPDATE game SET game_planning_gametime=?", array($months));
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/realtime Realtime
     * @apiParam {int} realtime length of planning phase (in seconds)
     * @apiDescription Set the duration of the planning phase in seconds
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Realtime(int $realtime): void
    {
        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query("UPDATE game SET game_planning_realtime=?", array($realtime));
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function SetStartDate(int $a_startYear): void
    {
        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query("UPDATE game SET game_start=?", array($a_startYear));
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/realtime FutureRealtime
     * @apiParam {string} realtime comma separated string of all the era times
     * @apiDescription Set the duration of future eras
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function FutureRealtime(string $realtime): void
    {
        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query("UPDATE game SET game_planning_era_realtime=?", array($realtime));
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/state State
     * @apiParam {string} state new state of the game
     * @apiDescription Set the current game state
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function State(string $state): void
    {
        $currentState = Database::GetInstance()->query("SELECT game_state FROM game")[0];
        if ($currentState["game_state"] == "END" || $currentState["game_state"] == "SIMULATION") {
            throw new Exception("Invalid current state of ".$currentState["game_state"]);
        }

        if ($currentState["game_state"] == "SETUP") {
            //Starting plans should be implemented when we any state "PLAY"
            $plan = new Plan();
            await($plan->updateLayerState(0));
        }

        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query(
            "UPDATE game SET game_lastupdate = ?, game_state=?",
            array(microtime(true), $state)
        );
        await($this->onGameStateUpdated($state));
    }

    /**
     * @throws Exception
     */
    private function onGameStateUpdated(string $newGameState): PromiseInterface
    {
        return $this->changeWatchdogState($newGameState);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetWatchdogAddress(bool $withPort = false): string
    {
        if (!empty($this->watchdog_address)) {
            if ($withPort) {
                return $this->watchdog_address.':'.self::WATCHDOG_PORT;
            }
            return $this->watchdog_address;
        }

        $result = Database::GetInstance($this->getGameSessionId())->query(
            "SELECT game_session_watchdog_address FROM game_session LIMIT 0,1"
        );
        if (count($result) > 0) {
            /** @noinspection HttpUrlsUsage */
            $this->watchdog_address = 'http://'.$result[0]['game_session_watchdog_address'];
            if ($withPort) {
                return $this->watchdog_address.':'.self::WATCHDOG_PORT;
            }
            return $this->watchdog_address;
        }
        return '';
    }

    /**
     * @throws Exception
     */
    private function getWatchdogSessionUniqueToken(): PromiseInterface
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
     * @ForceNoTransaction
     * @noinspection PhpUnused
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function StartWatchdog(): void
    {
        self::StartSimulationExe([
            'exe' => 'MSW.exe',
            'working_directory' => SymfonyToLegacyHelper::getInstance()->getProjectDir() . '/simulations/MSW/'
        ]);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function StartSimulationExe(array $params): void
    {
        $apiEndpoint = GameSession::GetRequestApiRoot();
        $args = isset($params["args"])? $params["args"]." " : "";
        $args = $args."APIEndpoint ".$apiEndpoint;

        $workingDirectory = "";
        if (isset($params["working_directory"])) {
            $workingDirectory = "cd ".$params["working_directory"]." & ";
        }

        Database::execInBackground('start cmd.exe @cmd /c "'.$workingDirectory.'start '.$params["exe"].' '.$args.'"');
    }

    /**
     * @throws Exception
     */
    private function assureWatchdogAlive(): ?PromiseInterface
    {
        // note(MH): GetWatchdogAddress is not async, but it is cached once it has been retrieved once, so that's "fine"
        $url = $this->GetWatchdogAddress(true);
        if (empty($url)) {
            return null;
        }

        // we want to use the watchdog, but first we check if it is running
        $browser = new MSPBrowser($url);
        $deferred = new Deferred();
        $browser
            // any response is acceptable, even 4xx or 5xx status codes
            ->withRejectErrorResponse(false)
            ->withTimeout(1)
            ->request('GET', $url)
            ->done(
                // watchdog is running
                function (/*ResponseInterface $response*/) use ($deferred) {
                    $deferred->resolve();
                },
                // so the Watchdog is off, and now it should be switched on
                function (/*Exception $e*/) use ($deferred) {
                    self::StartWatchdog();
                    $deferred->resolve();
                }
            );
        return $deferred->promise();
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
                return GameSession::getRequestApiRootAsync();
            })
            ->then(function (string $apiRoot) use ($newWatchdogGameState) {
                $simulationsHelper = new Simulations();
                $this->asyncDataTransferTo($simulationsHelper);
                $simulations = json_encode($simulationsHelper->GetConfiguredSimulationTypes(), JSON_FORCE_OBJECT);
                $security = new Security();
                $this->asyncDataTransferTo($security);
                $security->setAsync(true); // force async
                return $security->generateToken()
                    ->then(function (array $result) use (
                        $security,
                        $simulations,
                        $apiRoot,
                        $newWatchdogGameState
                    ) {
                        $newAccessToken = json_encode($result);
                        return $security->getSpecialToken(Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN)
                            ->then(function (string $token) use (
                                $security,
                                $simulations,
                                $apiRoot,
                                $newWatchdogGameState,
                                $newAccessToken
                            ) {
                                $recoveryToken = json_encode(['token' => $token]);
                                return $this->getWatchdogSessionUniqueToken()
                                    ->then(function (string $watchdogSessionUniqueToken) use (
                                        $security,
                                        $simulations,
                                        $apiRoot,
                                        $newWatchdogGameState,
                                        $newAccessToken,
                                        $recoveryToken
                                    ) {
                                        // note(MH): GetWatchdogAddress is not async, but it is cached once it
                                        //   has been retrieved once, so that's "fine"
                                        $url = $this->GetWatchdogAddress(true)."/Watchdog/UpdateState";
                                        $browser = new MSPBrowser($url);
                                        $postValues = [
                                            'game_session_api' => $apiRoot,
                                            'game_session_token' => $watchdogSessionUniqueToken,
                                            'game_state' => $newWatchdogGameState,
                                            'required_simulations' => $simulations,
                                            'api_access_token' => $newAccessToken,
                                            'api_access_renew_token' => $recoveryToken
                                        ];
                                        return $browser->post(
                                            $url,
                                            [
                                                'Content-Type' => 'application/x-www-form-urlencoded'
                                            ],
                                            http_build_query($postValues)
                                        );
                                    });
                            });
                    });
            })
            ->then(function (ResponseInterface $response) {
                $log = new Log();
                $this->asyncDataTransferTo($log);

                $responseContent = $response->getBody()->getContents();
                $decodedResponse = json_decode($responseContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $log->postEvent(
                        "Watchdog",
                        Log::ERROR,
                        "Received invalid response from watchdog. Response: \"".$responseContent."\"",
                        "changeWatchdogState()"
                    );
                }

                if ($decodedResponse["success"] != 1) {
                    return $log->postEvent(
                        "Watchdog",
                        Log::ERROR,
                        "Watchdog responded with failure to change game state request. Response: \"".
                        $decodedResponse["message"]."\"",
                        "changeWatchdogState()"
                    );
                }
                return resolveOnFutureTick(new Deferred(), $decodedResponse)->promise();
            });
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/GetActualDateForSimulatedMonth Set Start
     * @apiParam {int} simulated_month simulated month ranging from 0..game_end_month
     * @apiDescription Returns year and month ([1..12]) of the current requested simulated month identifier.
     *   Or -1 on both fields for error.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetActualDateForSimulatedMonth(int $simulated_month): array
    {
        $result = array("year" => -1, "month_of_year" => -1);
        $startYear = Database::GetInstance()->query("SELECT game_start FROM game LIMIT 0,1");
                
        if (count($startYear) == 1) {
            $result["year"] = floor($simulated_month / 12) + $startYear[0]["game_start"];
            $result["month_of_year"] = ($simulated_month % 12) + 1;
        }
        return $result;
    }

    /**
     * @throws Exception
     * @return array|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetGameDetails()/*: array|PromiseInterface // <-- php 8 */
    {
        $promise = $this->getAsyncDatabase()->query(
            $this->getAsyncDatabase()->createQueryBuilder()
                ->select(
                    'g.game_start',
                    'g.game_eratime',
                    'g.game_currentmonth',
                    'g.game_state',
                    'g.game_planning_realtime',
                    'g.game_planning_era_realtime',
                    'g.game_planning_gametime',
                    'g.game_planning_monthsdone',
                    'COUNT(u.user_id) total',
                    '
                    sum(
                        IF(UNIX_TIMESTAMP() - u.user_lastupdate < 3600 and u.user_loggedoff = 0, 1, 0)
                    ) active_last_hour',
                    '
                    sum(
                        IF(UNIX_TIMESTAMP() - u.user_lastupdate < 60 and u.user_loggedoff = 0, 1, 0)
                    ) active_last_minute'
                )
                ->from('game', 'g')
                ->join('g', 'user', 'u')
        )
        ->then(function (Result $result) {
            if (null === $state = $result->fetchFirstRow()) {
                return [];
            }

            $realtimePerEra = explode(",", $state["game_planning_era_realtime"]);
            // todo: division by zero.
            $currentEra = intval(floor($state["game_currentmonth"] / $state["game_planning_gametime"]));
            $realtimePerEra[$currentEra] = $state["game_planning_realtime"];
            $secondsPerMonthCurrentEra = round($state["game_planning_realtime"] / $state["game_eratime"]);
            $monthsRemainingCurrentEra = $state["game_eratime"] - $state["game_planning_monthsdone"];
            $totalRemainingTime = $monthsRemainingCurrentEra * $secondsPerMonthCurrentEra;
            $nextEra = $currentEra + 1;
            while (isset($realtimePerEra[$nextEra])) {
                $totalRemainingTime += $realtimePerEra[$nextEra];
                $nextEra++;
            }
            $runningTilTime = time() + $totalRemainingTime;
            return [
                "game_start_year" => (int) $state["game_start"],
                "game_end_month" => $state["game_eratime"] * 4,
                "game_current_month" => (int) $state["game_currentmonth"],
                "game_state" => strtolower($state["game_state"]),
                "players_past_hour" => (int) $state["active_last_hour"],
                "players_active" => (int) $state["active_last_minute"],
                "game_running_til_time" => $runningTilTime
            ];
        });
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetCountries(): array
    {
        return Database::GetInstance()->query("SELECT * FROM country WHERE country_name IS NOT NULL");
    }

    /**
     * @throws Exception
     */
    public function areSimulationsUpToDate(array $tickData): bool
    {
        $config = $this->GetGameConfigValues();
        if ((isset($config["MEL"]) && $tickData['month'] > $tickData['mel_lastmonth']) ||
            (isset($config["CEL"]) && $tickData['month'] > $tickData['cel_lastmonth']) ||
            (isset($config["SEL"]) && $tickData['month'] > $tickData['sel_lastmonth'])) {
            return false;
        }
        return true;
    }
}
