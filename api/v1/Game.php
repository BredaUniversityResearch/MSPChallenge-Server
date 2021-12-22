<?php

namespace App\Domain\API\v1;

use Exception;

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
        ["State", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        "TestWatchdogAlive"
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
        Database::GetInstance()->CreateMspDatabaseDump($outputFile, false);
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
        Database::GetInstance()->query("UPDATE game SET game_currentmonth=game_currentmonth+1");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function LoadConfigFile(string $filename = ''): string
    {
        if ($filename == "") {    //if there's no file given, use the one in the database
            $data = Database::GetInstance()->query("SELECT game_configfile FROM game");

            $path = GameSession::CONFIG_DIRECTORY . $data[0]['game_configfile'];
        } else {
            $path = GameSession::CONFIG_DIRECTORY . $filename;
        }

        return file_get_contents($path);
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
     * Tick the game server, updating the plans if required
     *
     * @param bool $showDebug
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Tick(bool $showDebug = false): void
    {
        $plan = new Plan();
        $plan->Tick();

        //Update server time and month
        $tick = Database::GetInstance()->query("SELECT game_lastupdate as lastupdate,
				game_currentmonth as month,
				game_planning_gametime as era_gametime,
				game_planning_realtime as era_realtime,
				game_mel_lastmonth as mel_lastmonth,
				game_cel_lastmonth as cel_lastmonth,
				game_sel_lastmonth as sel_lastmonth,
				game_state as state
			FROM game")[0];

        $state = $tick["state"];

        if ($state != "END" && $state != "PAUSE" && $state != "SETUP") {
            $currenttime = microtime(true);
            $lastupdate = $tick['lastupdate'];
            $diff = $currenttime - $lastupdate;

            $secondspermonth = $tick['era_realtime'] / $tick['era_gametime'];
            if ($state == "SIMULATION" || $state == "FASTFORWARD") {
                $secondspermonth = 0.2;
            }

            if ($diff > $secondspermonth) {
                if ($showDebug) {
                    self::Debug("Trying to tick the server");
                }

                $this->TryTickServer($tick, $showDebug);
            } else {
                if ($showDebug) {
                    self::Debug("Waiting for update time ".($secondspermonth - $diff). " seconds remaining");
                }
            }
        }

        // only activate this after the Tick call has moved out of the client and into the Watchdog
        $this->UpdateGameDetailsAtServerManager();
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function UpdateGameDetailsAtServerManager()
    {
        $postValues = $this->GetGameDetails();
        $token = (new Security())->GetServerManagerToken()["token"];
        $postValues["token"] = $token;
        $postValues["session_id"] = GameSession::GetGameSessionIdForCurrentRequest();
        $postValues["action"] = "demoCheck";
        $url = GameSession::GetServerManagerApiRoot()."editGameSession.php";
        $this->CallBack($url, $postValues);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function AreSimulationsUpToDate(array $tickData): bool
    {
        $config = $this->GetGameConfigValues();
        if ((isset($config["MEL"]) && $tickData['month'] > $tickData['mel_lastmonth']) ||
            (isset($config["CEL"]) && $tickData['month'] > $tickData['cel_lastmonth']) ||
            (isset($config["SEL"]) && $tickData['month'] > $tickData['sel_lastmonth'])) {
            return false;
        }
        return true;
    }

    /**
     * @throws Exception
     * @noinspection PhpSameParameterValueInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CalculateUpdatedTime(bool $showDebug = false): array
    {
        $tick = Database::GetInstance()->query(
            "
            SELECT game_state as state, game_lastupdate as lastupdate, game_currentmonth as month,
				game_start as start, game_planning_gametime as era_gametime, game_planning_realtime as era_realtime,
				game_planning_era_realtime as planning_era_realtime, game_planning_monthsdone as era_monthsdone,
				game_mel_lastmonth as mel_lastmonth, game_cel_lastmonth as cel_lastmonth,
                game_sel_lastmonth as sel_lastmonth, game_eratime as era_time
			FROM game
			"
        )[0];

        $state = $tick["state"];
        $secondsPerMonth = $tick['era_realtime'] / $tick['era_gametime'];

        //only update if the game is playing
        if ($state != "END" && $state != "PAUSE" && $state != "SETUP") {
            $currentTime = microtime(true);
            $lastUpdate = $tick['lastupdate'];

            //if the last update was at time 0, this is the very first tick happening for this game
            if ($lastUpdate == 0) {
                Database::GetInstance()->query("UPDATE game SET game_lastupdate=?", array(microtime(true)));
                $lastUpdate = microtime(true);
                $currentTime = $lastUpdate;
            }

            $diff = $currentTime - $lastUpdate;
            $secondsPerMonth = $tick['era_realtime'] / $tick['era_gametime'];

            if ($diff < $secondsPerMonth) {
                $tick['era_timeleft'] = $tick['era_realtime'] - $diff - ($tick['era_monthsdone'] * $secondsPerMonth);
            } else {
                $tick['era_timeleft'] = -1;
            }

            if ($showDebug) {
                self::Debug("diff: " . $diff);
            }

            if ($showDebug) {
                self::Debug("timeleft: " . $tick['era_timeleft']);
            }
        } elseif ($state == "PAUSE" || $state == "SETUP") {
            //[MSP-1116] Seems sensible?
            $tick['era_timeleft'] = $tick['era_realtime'] - ($tick['era_monthsdone'] * $secondsPerMonth);
            if ($showDebug) {
                echo "GAME PAUSED";
            }
        } else {
            if ($showDebug) {
                echo "GAME ENDED";
            }
        }

        if ($showDebug) {
            self::Debug($tick);
        }

        return $tick;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function TryTickServer($tickData, $showDebug): void
    {
        if (!strstr($_SERVER['REQUEST_URI'], 'dev') || Config::GetInstance()->ShouldWaitForSimulationsInDev()) {
            if (!$this->AreSimulationsUpToDate($tickData)) {
                if ($showDebug) {
                    self::Debug("Waiting for simulations to update.");
                }
                return;
            }
        }

        $result = Database::GetInstance()->queryReturnAffectedRowCount(
            "
            UPDATE game SET game_is_running_update = 1, game_lastupdate = ?
            WHERE game_is_running_update = 0 AND game_lastupdate = ?
            ",
            array(microtime(true), $tickData["lastupdate"])
        );
        if ($result == 1) {
            //Spawn thread eventually.
            if ($showDebug) {
                self::Debug("Ticking server.");
            }
            $this->ServerTickInternal();
        } elseif ($showDebug) {
            self::Debug("Update already in progress.");
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function ServerTickInternal(): void
    {
        //Updates time to the next month.
        $tick = Database::GetInstance()->query("SELECT
				game_state as state,
				game_currentmonth as month,
				game_planning_gametime as era_gametime,
				game_planning_realtime as era_realtime,
				game_planning_era_realtime as planning_era_realtime,
				game_planning_monthsdone as era_monthsdone,
				game_eratime as era_time,
				game_autosave_month_interval as autosave_interval_months
			FROM game")[0];

        $state = $tick['state'];

        $monthsdone = $tick['era_monthsdone'] + 1;
        $currentmonth = $tick['month'] + 1;

        //update all the plans which ticks the server.
        $plan = new Plan();
        $plan->UpdateLayerState($currentmonth);

        if ($currentmonth >= ($tick['era_time'] * 4)) { //Hardcoded to 4 eras as designed.
            //Entire game is done.
            Database::GetInstance()->query(
                "UPDATE game SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?, game_state=?",
                array(microtime(true), $currentmonth, $monthsdone, "END")
            );
            $this->OnGameStateUpdated("END");
        } elseif (($state == "PLAY" || $state == "FASTFORWARD") && $monthsdone >= $tick['era_gametime'] &&
            $tick['era_gametime'] < $tick['era_time']) {
            //planning phase is complete, move to the simulation phase
            Database::GetInstance()->query(
                "UPDATE game SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?, game_state=?",
                array(microtime(true), $currentmonth, 0, "SIMULATION")
            );
            $this->OnGameStateUpdated("SIMULATION");
        } elseif (($state == "SIMULATION" && $monthsdone >= $tick['era_time'] - $tick['era_gametime']) ||
            $monthsdone >= $tick['era_time']) {
            //simulation is done, reset everything to start a new play phase
            $era = floor($currentmonth / $tick['era_time']);
            $era_realtime = explode(",", $tick['planning_era_realtime']);
            Database::GetInstance()->query(
                "
                UPDATE game
                SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?, game_state=?,
                    game_planning_realtime=?
                ",
                array(microtime(true), $currentmonth, 0, "PLAY", $era_realtime[$era])
            );
            $this->OnGameStateUpdated("PLAY");
        } else {
            Database::GetInstance()->query(
                "UPDATE game SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?",
                array(microtime(true), $currentmonth, $monthsdone)
            );
        }

        if (($tick['month'] % $tick['autosave_interval_months']) == 0) {
            $this->AutoSaveDatabase();
        }

        Database::GetInstance()->query("UPDATE game SET game_is_running_update = 0");
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
        Database::GetInstance()->query("UPDATE game SET game_planning_realtime=?", array($realtime));
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function SetStartDate(int $a_startYear): void
    {
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
            $plan->UpdateLayerState(0);
        }

        Database::GetInstance()->query(
            "UPDATE game SET game_lastupdate = ?, game_state=?",
            array(microtime(true), $state)
        );
        $this->OnGameStateUpdated($state);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function OnGameStateUpdated(string $newGameState): void
    {
        $this->ChangeWatchdogState($newGameState);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetWatchdogAddress(bool $withPort = false): string
    {
        if (!empty($this->watchdog_address)) {
            if ($withPort) {
                return $this->watchdog_address.':'.self::WATCHDOG_PORT;
            }
            return $this->watchdog_address;
        }

        $result = Database::GetInstance()->query(
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
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetWatchdogSessionUniqueToken(): string
    {
        $result = Database::GetInstance()->query("SELECT game_session_watchdog_token FROM game_session LIMIT 0,1");
        if (count($result) > 0) {
            return $result[0]["game_session_watchdog_token"];
        }
        return "0";
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function TestWatchdogAlive(): bool
    {
        try {
            $this->CallBack(
                $this->GetWatchdogAddress(true),
                array(),
                array(),
                false,
                false,
                array(CURLOPT_CONNECTTIMEOUT => 1)
            );
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @ForceNoTransaction
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function StartWatchdog(): void
    {
        self::StartSimulationExe(array("exe" => "MSW.exe", "working_directory" => "simulations/MSW/"));
    }

    /** @noinspection PhpSameParameterValueInspection */
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
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ChangeWatchdogState(string $newWatchdogGameState): bool
    {
        if (empty($this->GetWatchdogAddress(true))) {
            return false;
        }

        // we want to change the watchdog state, but first we check if it is running
        if (!$this->TestWatchdogAlive()) {
            // so the Watchdog is off, and now it should be switched on
            $requestHeader = apache_request_headers();
            $headers = array();
            if (isset($requestHeader["MSPAPIToken"])) {
                $headers[] = "MSPAPIToken: ".$requestHeader["MSPAPIToken"];
            }
            $this->CallBack(
                $this->GetWatchdogAddress()."/api/Game/StartWatchdog",
                array(),
                $headers,
                true
            ); //curl_exec($ch);
            sleep(3); //not sure if this is necessary
        }

        $apiRoot = GameSession::GetRequestApiRoot();

        $simulationsHelper = new Simulations();
        $simulations = json_encode($simulationsHelper->GetConfiguredSimulationTypes(), JSON_FORCE_OBJECT);
        $security = new Security();
        $newAccessToken = json_encode($security->GenerateToken());
        $recoveryToken = json_encode($security->GetRecoveryToken());

        // If we post this as an array it will come out as a multipart/form-data and it's easier for MSW to manually
        //   create the string here.
        $postValues = "game_session_api=".urlencode($apiRoot).
            "&game_session_token=".urlencode($this->GetWatchdogSessionUniqueToken()).
            "&game_state=".urlencode($newWatchdogGameState).
            "&required_simulations=".urlencode($simulations).
            "&api_access_token=".urlencode($newAccessToken).
            "&api_access_renew_token=".urlencode($recoveryToken);

        $response = $this->CallBack(
            $this->GetWatchdogAddress(true)."/Watchdog/UpdateState",
            $postValues,
            array()
        ); //curl_exec($ch);

        $log = new Log();

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log->PostEvent(
                "Watchdog",
                Log::ERROR,
                "Received invalid response from watchdog. Response: \"".$response."\"",
                "ChangeWatchdogState()"
            );
            return false;
        }

        if ($decodedResponse["success"] != 1) {
            $log->PostEvent(
                "Watchdog",
                Log::ERROR,
                "Watchdog responded with failure to change game state request. Response: \"".
                $decodedResponse["message"]."\"",
                "ChangeWatchdogState()"
            );
            return false;
        }

        return true;
    }

    /**
     * Gets the latest plans & messages from the server
     *
     * @param int $team_id
     * @param float $last_update_time
     * @param int $user
     * @return array|string
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Latest(int $team_id, float $last_update_time, int $user)/*: array|string */ // <-- for php 8
    {
        // define('DEBUG_PREF_TIMING', true);

        $newTime = microtime(true);

        //returns all updated data since the last updated time
        $plan = new Plan("");
        $layer = new Layer("");
        $energy = new Energy("");
        $kpi = new Kpi("");
        $warning = new Warning("");
        $objective = new Objective("");
        $data = array();
        $data['prev_update_time'] = $last_update_time;

        $data['tick'] = $this->CalculateUpdatedTime();
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after tick<br />";
        }

        $data['plan'] = $plan->Latest($last_update_time);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after plan<br />";
        }

        foreach ($data['plan'] as &$p) {
            //only send the geometry when it's required
            $p['layers'] = $layer->Latest($p['layers'], $last_update_time, $p['id']);
            if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
                echo (microtime(true) - $newTime) . " elapsed after layers<br />";
            }

            if (($p['state'] == "DESIGN" && $p['previousstate'] == "CONSULTATION" && $p['country'] != $team_id)) {
                $p['active'] = 0;
            }
        }

        $data['planmessages'] = $plan->GetMessages($last_update_time);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after plan messages<br />";
        }

        //return any raster layers that need to be updated
        $data['raster'] = $layer->LatestRaster($last_update_time);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after raster<br />";
        }

        $data['energy'] = $energy->Latest($last_update_time);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after energy<br />";
        }

        $data['kpi'] = $kpi->Latest($last_update_time, $team_id);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after kpi<br />";
        }

        $data['warning'] = $warning->Latest($last_update_time);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after warning<br />";
        }

        $data['objectives'] = $objective->Latest($last_update_time);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            echo (microtime(true) - $newTime) . " elapsed after objective<br />";
        }

        //Add a slight fudge of 1ms to the update times to avoid rounding issues.
        $data['update_time'] = $newTime - 0.001;

        // send an empty string if nothing was updated
        if (empty($data['energy']['connections']) &&
            empty($data['energy']['output']) &&
            empty($data['geometry']) &&
            empty($data['plan']) &&
            empty($data['messages']) &&
            empty($data['planmessages']) &&
            empty($data['kpi']) &&
            empty($data['warning']) &&
            empty($data['raster']) &&
            empty($data['objectives'])) {
            return "";
        }
        Database::GetInstance()->query(
            "UPDATE user SET user_lastupdate=? WHERE user_id=?",
            array($newTime, $user)
        );
        return $data;
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
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetGameDetails(): array
    {
        $databaseState = Database::GetInstance()->query(
            "
            SELECT g.game_start, g.game_eratime, g.game_currentmonth, g.game_state, g.game_planning_realtime,
                   g.game_planning_era_realtime, g.game_planning_gametime, g.game_planning_monthsdone,
                   COUNT(u.user_id) total,
                   sum(
                       IF(UNIX_TIMESTAMP() - u.user_lastupdate < 3600 and u.user_loggedoff = 0, 1, 0)
                   ) active_last_hour,
                   sum(
                       IF(UNIX_TIMESTAMP() - u.user_lastupdate < 60 and u.user_loggedoff = 0, 1, 0)
                   ) active_last_minute
            FROM game g, user u;
            "
        );
            
        $result = array();
        if (count($databaseState) > 0) {
            $state = $databaseState[0];

            $realtime_per_era = explode(",", $state["game_planning_era_realtime"]);
            $current_era = intval(floor($state["game_currentmonth"] / $state["game_planning_gametime"]));
            $realtime_per_era[$current_era] = $state["game_planning_realtime"];
            $seconds_per_month_current_era = round($state["game_planning_realtime"] / $state["game_eratime"]);
            $months_remaining_current_era = $state["game_eratime"] - $state["game_planning_monthsdone"];
            $total_remaining_time = $months_remaining_current_era * $seconds_per_month_current_era;
            $nextera = $current_era + 1;
            while (isset($realtime_per_era[$nextera])) {
                $total_remaining_time += $realtime_per_era[$nextera];
                $nextera++;
            }
            $running_til_time = time() + $total_remaining_time;

            $result = ["game_start_year" => (int) $state["game_start"],
                "game_end_month" => $state["game_eratime"] * 4,
                "game_current_month" => (int) $state["game_currentmonth"],
                "game_state" => strtolower($state["game_state"]),
                "players_past_hour" => (int) $state["active_last_hour"],
                "players_active" => (int) $state["active_last_minute"],
                "game_running_til_time" => $running_til_time
            ];
        }
        return $result;
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
}
