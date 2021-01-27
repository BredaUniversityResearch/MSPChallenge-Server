<?php

class Simulations extends Base
{
	protected $allowed = array(
		"GetConfiguredSimulationTypes",
		"GetSimulationRequestedState",
		"WatchdogStartSimulations",
		"WatchdogStopSimulations",
		["GetWatchdogTokenForServer", Security::ACCESS_LEVEL_FLAG_NONE]);


	const POSSIBLE_SIMULATIONS = array("MEL", "CEL", "SEL", "REL");

	public function __construct($method = "")
	{
		parent::__construct($method);
	}

	/**
	 * @apiGroup Simulations
	 * @api {GET} /Simulations/GetConfiguredSimulationTypes Get Configured Simulation Types (e.g. ["MEL", "SEL", "CEL"])
	 * @apiDescription Returns the type name of the simulations present in the current configuration.
	 * @apiSuccess {string} JSON object
	 */
	public function GetConfiguredSimulationTypes() 
	{
		$result = array();
		$game = new Game();
		$config = $game->GetGameConfigValues();			
		foreach(self::POSSIBLE_SIMULATIONS as $possibleSim)
		{
			if (array_key_exists($possibleSim, $config)) {
				$versionString = "Latest";
				if (array_key_exists("force_version", $config[$possibleSim]))
				{
					$versionString = $config[$possibleSim]["force_version"];	
				}
				$result[$possibleSim] = $versionString;
			}
		}
		return $result;
	}

	/*public function GetConfiguredSimulationTypesAsArray() // since GetConfiguredSimulationTypes() now simply returns the array rather than json
	{
		$result = array();
		$game = new Game();
		$config = $game->GetGameConfigValues();			
		foreach(self::POSSIBLE_SIMULATIONS as $possibleSim)
		{
			if (array_key_exists($possibleSim, $config)) {
				$versionString = "Latest";
				if (array_key_exists("force_version", $config[$possibleSim]))
				{
					$versionString = $config[$possibleSim]["force_version"];	
				}
				$result[$possibleSim] = $versionString;
			}
		}
		return $result;
	}*/

	/**
	 * @apiGroup Simulations
	 * @api {GET} /Simulations/GetSimulationRequestedState Get requested running state of the simulation.
	 * @apiDescription Returns whether or not the simulation should be running.
	 * @apiSuccess {string} Currently requested state for simulations. [Started, Stopped]
	 */
	public function GetSimulationRequestedState() 
	{
		try
		{
			$result = $this->query("SELECT watchdog_simulation_desired_state FROM watchdog");
			if (count($result) > 0)
			{
				return $result[0]["watchdog_simulation_desired_state"];
			}
			return "Stopped";
		}
		catch(Exception $ex)
		{
			//I know it's horrible practice to swallow exceptions, but in this case it might be an exception (get it? haha)
			//If the query fails we can assume the database isn't present, or is in a rebuild. In this case we don't want the 
			//simulation to run at all, so we kill the simulations right there.
			return "Stopped";
		}
	}

	private function SetSimulationRequestedState($state)
	{
		$this->query("UPDATE watchdog SET watchdog_simulation_desired_state = ?", array($state));
	}

	/**
	 * @apiGroup Simulations
	 * @api {GET} /Simulations/WatchdogStartSimulations Set the requested simulation state for watchdog.
	 * @apiDescription Set the state so the watchdog will keep simulations running.
	 */
	public function WatchdogStartSimulations()
	{
		$this->SetSimulationRequestedState("Started");
	}

	/**
	 * @apiGroup Simulations
	 * @api {GET} /Simulations/WatchdogStopSimulations Watchdog stop simulations.
	 * @apiDescription Stop all simulations maintained by watchdog..
	 */
	public function WatchdogStopSimulations()
	{
		$this->SetSimulationRequestedState("Stopped");
	}

	/**
	 * @apiGroup Simulations
	 * @api {GET} /Simulations/GetWatchdogTokenForServer Watchdog stop simulations.
	 * @apiDescription Returns json object containing the watchdog token for the current server. Used for setting up debug bridge in simulations.
	 */
	public function GetWatchdogTokenForServer()
	{
		$token = null;
		$data = $this->query("SELECT game_session_watchdog_token FROM game_session LIMIT 0,1");
		if (count($data) > 0)
		{
			$token = $data[0]["game_session_watchdog_token"];
		}
		return array("watchdog_token" => $token);
	}
}