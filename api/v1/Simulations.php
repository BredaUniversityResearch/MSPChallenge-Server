<?php

namespace App\Domain\API\v1;

use Exception;

class Simulations extends Base
{
    private const ALLOWED = array(
        "GetConfiguredSimulationTypes",
        ["GetWatchdogTokenForServer", Security::ACCESS_LEVEL_FLAG_NONE]);

    const POSSIBLE_SIMULATIONS = array("MEL", "CEL", "SEL", "REL");

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
        $game->setGameSessionId($this->getGameSessionId());
        $config = $game->GetGameConfigValues();
        foreach (self::POSSIBLE_SIMULATIONS as $possibleSim) {
            if (array_key_exists($possibleSim, $config) && is_array($config[$possibleSim])) {
                $versionString = "Latest";
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
        $data = Database::GetInstance()->query("SELECT game_session_watchdog_token FROM game_session LIMIT 0,1");
        if (count($data) > 0) {
            $token = $data[0]["game_session_watchdog_token"];
        }
        return array("watchdog_token" => $token);
    }
}
