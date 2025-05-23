<?php

namespace App\Domain\API\v1;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\Watchdog;
use Drift\DBAL\Result;
use Exception;

use React\Promise\PromiseInterface;
use function App\await;

class Game extends Base
{
    const MIN_GAME_ERATIME = 12;

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/AutoSaveDatabase AutoSaveDatabase
     * @apiDescription Creates a session database dump with the naming convention AutoDump_YYY-mm-dd_hh-mm.sql
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AutoSaveDatabase(): void
    {
        if (str_contains($_SERVER['REQUEST_URI'], 'dev')) {
            return; //Don't create database dumps on dev.
        }

        $outputDirectory = "export/DatabaseDumps/";
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory);
        }

        $outputFile = $outputDirectory."AutoDump_".date("Y-m-d_H-i").".sql";
        $this->getDatabase()->createMspDatabaseDump($outputFile, false);
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

        foreach ($data as $key => $d) {
            if ((is_array($d)) && $key != "expertise_definitions" && $key != "dependencies"
            ) {
                unset($data[$key]);
            }
        }
        if (!isset($data['wiki_base_url'])) {
            $data['wiki_base_url'] = $_ENV['DEFAULT_WIKI_BASE_URL'];
        }
        if (!isset($data['team_info_base_url'])) {
            $data['team_info_base_url'] = $_ENV['DEFAULT_WIKI_BASE_URL'];
        }

        if (!isset($data['edition_name'])) {
            $data['edition_name'] = $_ENV['DEFAULT_EDITION_NAME'];
        }
        if (!isset($data['edition_colour'])) {
            $data['edition_colour'] = $_ENV['DEFAULT_EDITION_COLOUR'];
        }
        if (!isset($data['edition_letter'])) {
            $data['edition_letter'] = $_ENV['DEFAULT_EDITION_LETTER'];
        }

        $passwordchecks = (new GameSession())->CheckGameSessionPasswords();
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
        $this->getDatabase()->query("UPDATE game SET game_currentmonth=game_currentmonth+1");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function LoadConfigFile(string $filename = ''): string
    {
        $path = SymfonyToLegacyHelper::getInstance()->getProjectDir().$_ENV['SESSION_CONFIG_PATH'];
        if ($filename == "") {    //if there's no file given, use the one in the database
            $path .= sprintf($_ENV['SESSION_CONFIG_FILE'], $this->getGameSessionId());
        } else {
            $path .= $filename;
        }

        // 5 min cache. why 5min? Such that the websocket server will refresh the config once in a while
        static $cacheTime = null;
        static $cache = [];
        if (array_key_exists($path, $cache) &&
            ($cacheTime === null || time() - $cacheTime < 300)) {
            return $cache[$path];
        }
        $cacheTime = time();
        if (!file_exists($path)) {
            return '';
        }
        $cache[$path] = file_get_contents($path);
        return $cache[$path];
    }

    /**
     * @throws Exception
     * @todo: use https://github.com/karriereat/json-decoder "to convert your JSON data into an actual php class"
     * phpcs:ignore Generic.Files.LineLength.TooLong
     * @return array{restrictions: array, plans: array, policy_settings: array, stakeholder_pressure_settings: array, dependencies: array, CEL: ?array, REL: ?array, SEL: ?array{heatmap_settings: array, shipping_lane_point_merge_distance: int, shipping_lane_subdivide_distance: int, shipping_lane_implicit_distance_limit: int, maintenance_destinations: array, output_configuration: array}, MEL: ?array{x_min?: int, x_max?: int, y_min?: int, y_max?: int, cellsize: int, columns: int, rows: int, pressures: array{name: string, layers: array, policy_filters?: array{fleets: array}}, fishing: array}, meta: array{array{layer_name: string, layer_type: array{array{availability?: int, displayName: string, value: int}}, layer_info_properties: ?array{array{property_name: string, policy_type?: string}}}}, expertise_definitions: array, oceanview: array, objectives: array, region: string, projection: string, edition_name: string, edition_colour: string, edition_letter: string, start: int, end: int, era_total_months: int, era_planning_months: int, era_planning_realtime: int, countries: string, minzoom: int, maxzoom: int, user_admin_name: string, user_region_manager_name: string, user_admin_color: string, user_region_manager_color: string, team_info_base_url: string, region_base_url: string, restriction_point_size: int, wiki_base_url: string, windfarm_data_api_url: ?string}|array{application_versions: array{client_build_date_min: string, client_build_date_max: string}, restriction_point_size?: float, wiki_base_url: string}
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
     * @throws Exception
     */
    public function getFleetIndexByCountryAndGearType(int $countryId, int $gearTypeIndex): ?int
    {
        $gameConfigValues = $this->GetGameConfigValues();
        if (false === $result = collect($gameConfigValues['policy_settings']['fishing']['fleet_info']['fleets'] ?? [])
            ->search(
                fn($f) => (
                    // -1 is a wildcard for any country
                    ($f['country_id'] == -1) || ($f['country_id'] == $countryId)
                ) && $f['gear_type'] == $gearTypeIndex
            )) {
            return null;
        }
        return $result;
    }

    /**
     * @return ?array{gear_type: int, country_id: int}
     * @throws Exception
     */
    public function getFleetFromIndex(int $fleetIndex): ?array
    {
        $gameConfigValues = $this->GetGameConfigValues();
        if (null === $fleet =
            $gameConfigValues['policy_settings']['fishing']['fleet_info']['fleets'][$fleetIndex] ?? null) {
            return null;
        }
        if (!array_key_exists('gear_type', $fleet) || !array_key_exists('country_id', $fleet)) {
            return null;
        }
        return [
            'gear_type' => (int) $fleet['gear_type'],
            'country_id' => (int) $fleet['country_id']
        ];
    }

    /**
     * Returns an array of all layer properties that have a policy_type field.
     *
     * @return array{array{property_name: string, policy_type?: string}}
     * @throws Exception
     */
    public function getPolicyLayerPropertiesFromConfig(?string $layerNameFilter = null): array
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }
        $result = [];
        $dataModel = $this->GetGameConfigValues();
        foreach ($dataModel['meta'] ?? [] as $layer) {
            if ($layerNameFilter !== null && $layer['layer_name'] !== $layerNameFilter) {
                continue;
            }
            foreach ($layer['layer_info_properties'] ?? [] as $layerProp) {
                if (array_key_exists('policy_type', $layerProp)) {
                    $result[] = $layerProp;
                }
            }
        }
        return $result;
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
        $currentMonth = $this->getDatabase()->query("SELECT game_currentmonth, game_state FROM game")[0];
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
        $this->getDatabase()->query("UPDATE game SET game_configfile=?", array($configFilename));
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
        $this->getDatabase()->query(
            "INSERT INTO country (country_id, country_colour, country_is_manager) VALUES (?, ?, ?)",
            array(1, $adminColor, 1)
        );
        //Region manager country.
        $this->getDatabase()->query(
            "INSERT INTO country (country_id, country_colour, country_is_manager) VALUES (?, ?, ?)",
            array(2, $regionManagerColor, 1)
        );

        foreach ($configData['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $configData['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $countryId = $country['value'];
                    $this->getDatabase()->query(
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
        $this->getDatabase()->query("INSERT INTO user (user_lastupdate, user_country_id) VALUES(0, 1)");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetupGameTime(array $data): void
    {
        $this->SetStartDate($data['start']);

        $this->Planning($data['era_planning_months']);

        $this->Realtime($data['era_planning_realtime']);
        $str = "";
        $totalEras = 4;
        $str .= str_repeat($data['era_planning_realtime'] . ",", $totalEras);
        $str = substr($str, 0, -1);

        /** @noinspection SqlWithoutWhere */
        $this->getDatabase()->query(
            "UPDATE game SET game_planning_era_realtime=?, game_eratime=?",
            array(
                $str,
                max($data['era_total_months'], self::MIN_GAME_ERATIME)
            )
        );
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
        $this->getDatabase()->query("UPDATE user SET user_lastupdate=? WHERE user_id=?", array(0, $user));

        $activeQueryPart = "";
        if ($onlyActiveLayers) {
            $activeQueryPart = " AND layer_active = 1 ";
        }

        if ($sort) {
            $data = $this->getDatabase()->query(
                "SELECT * FROM layer WHERE layer_original_id IS NULL ".$activeQueryPart." ORDER BY layer_name ASC",
                array()
            );
        } else {
            $data = $this->getDatabase()->query(
                "SELECT * FROM layer WHERE layer_original_id IS NULL ".$activeQueryPart,
                array()
            );
        }

        for ($i = 0; $i < sizeof($data); $i++) {
            Layer::FixupLayerMetaData($data[$i]);
        }
        $this->addLayerDependenciesToMetaData($data);

        return $data;
    }

    /**
     * This method adds "layer_dependencies" int[] field to a layer which holds all layer ids the layer depends on for
     *   editing.
     * Currently, that is, all the grey energy layers depend on the grey cable layer and all the green energy layers
     *   on the green cable layer.
     *
     * @param array $meta input meta data from Meta()
     * @return void
     */
    private function addLayerDependenciesToMetaData(array &$meta): void
    {
        // find cable layers
        $cableLayers = collect($meta)->filter(fn($l) => $l['layer_editing_type'] === 'cable')->keyBy('layer_id');
        if ($cableLayers->isEmpty()) {
            return;
        }
        // if cable layers exists, go through all layers
        foreach ($meta as &$layer) {
            $layer['layer_dependencies'] = [];

            // has to be a non-cable
            if ($cableLayers->has($layer['layer_id'])) {
                continue;
            }
            // if they have the required energy editing type: "transformer", "socket", "sourcepoint" or "sourcepolygon"
            if (!in_array(
                $layer['layer_editing_type'],
                ['transformer', 'socket', 'sourcepoint', 'sourcepolygon']
            )) {
                continue;
            }

            // find the corresponding green or grey cable layer
            if (null === $cableLayer = $cableLayers->firstWhere('layer_green', $layer['layer_green'])) {
                continue;
            }

            // add the associated cable layer id to their dependency
            $layer['layer_dependencies'][] = $cableLayer['layer_id'];
        }
        unset($layer);
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
        $this->getDatabase()->query("UPDATE game SET game_planning_gametime=?", array($months));
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
        $this->getDatabase()->query("UPDATE game SET game_planning_realtime=?", array($realtime));
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function SetStartDate(int $a_startYear): void
    {
        /** @noinspection SqlWithoutWhere */
        $this->getDatabase()->query("UPDATE game SET game_start=?", array($a_startYear));
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
        $this->getDatabase()->query("UPDATE game SET game_planning_era_realtime=?", array($realtime));
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
        $state = strtoupper($state);
        $currentState = $this->getDatabase()->query("SELECT game_state FROM game")[0];
        if ($currentState["game_state"] == "END" || $currentState["game_state"] == "SIMULATION") {
            throw new Exception("Invalid current state of ".$currentState["game_state"]);
        }

        // prepare update query using builder
        $qb = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($this->getGameSessionId())
            ->createQueryBuilder();
        $qb
            ->update('game')
            ->set('game_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
            ->set('game_state', $qb->createPositionalParameter($state));
        if ($currentState["game_state"] == "SETUP") {
            //Starting plans should be implemented when we any state "PLAY"
            $plan = new Plan();
            await($plan->updateLayerState(0));

            if ($state == "PAUSE") {
                $qb->set('game_currentmonth', $qb->createPositionalParameter(0));
            }
        }
        $qb->executeQuery();

        await($this->onGameStateUpdated($state));
    }

    /**
     * @apiGroup Game
     * @throws Exception
     * @api {POST} /game/PolicySimSettings PolicySimSettings
     * @apiDescription Get policy and simulation settings
     * @apiSuccess {string} JSON object
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function PolicySimSettings(): array
    {
        $gameConfigValues = (new Game())->GetGameConfigValues();
        $policySettings = array_values(collect($gameConfigValues['policy_settings'] ?? [])
            ->filter(fn($ps) => $ps['enabled'] == true)->all());
        array_walk($policySettings, function (&$ps) {
            unset($ps['enabled']);
        });
        $simulationSettings = [];

        $data = $this->GetGameConfigValues();
        if (isset($data['MEL'])) {
            $mel = new MEL();
            $simulationSettings[] = [
                'simulation_type' => 'MEL',
                'content' => $mel->Config()
            ];
        }
        if (isset($data['SEL'])) {
            $sel = new SEL();
            $selGameClientConfig = $sel->GetSELGameClientConfig();
            if (is_object($selGameClientConfig)) {
                $selGameClientConfig = get_object_vars($selGameClientConfig);
            }
            $simulationSettings[] = array_merge(
                [
                    'simulation_type' => 'SEL',
                    'kpis' => $sel->GetKPIDefinition()
                ],
                // E.g. returns key directionality_icon_color
                $selGameClientConfig
            );
        }
        if (isset($data['CEL'])) {
            $cel = new CEL();
            $simulationSettings[] = array_merge([
                'simulation_type' => 'CEL'
            ], $cel->GetCELConfig());
        }
        $em = ConnectionManager::getInstance()->getServerManagerEntityManager();
        $serverWatchdogs = $em->getRepository(GameWatchdogServer::class)->findAll();
        foreach ($serverWatchdogs as $watchdog) {
            if ($watchdog->getServerId() == Watchdog::getInternalServerId()) {
                continue;
            }
            $watchdogSimSettings = $watchdog->getSimulationSettings();
            if (empty($watchdogSimSettings)) {
                continue;
            }
            $simulationSettings[] = json_decode($watchdogSimSettings, true);
        }
        return [
            'policy_settings' => $policySettings,
            'simulation_settings' => $simulationSettings
        ];
    }

    /**
     * @throws Exception
     */
    private function onGameStateUpdated(string $newGameState): PromiseInterface
    {
        $simulation = new Simulation();
        $this->asyncDataTransferTo($simulation);
        return $simulation->changeWatchdogState($newGameState);
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
        $startYear = $this->getDatabase()->query("SELECT game_start FROM game LIMIT 0,1");
                
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
    public function GetGameDetails(): array|PromiseInterface
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
                ->leftJoin('g', 'user', 'u', '1=1')
        )
        ->then(function (Result $result) {
            if (null === $state = $result->fetchFirstRow()) {
                return [];
            }

            $realtimePerEra = filter_var_array(
                explode(',', $state["game_planning_era_realtime"]),
                FILTER_VALIDATE_INT
            );
            $totalRemainingTime = $state["game_planning_realtime"]; // remaining time current era
            $currentEra = intval($state["game_currentmonth"] / $state["game_eratime"]);
            $nextEra = $currentEra + 1;
            while (isset($realtimePerEra[$nextEra])) {
                $totalRemainingTime += $realtimePerEra[$nextEra]; // add set remaining time next eras
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
        return $this->getDatabase()->query("SELECT * FROM country WHERE country_name IS NOT NULL");
    }
}
