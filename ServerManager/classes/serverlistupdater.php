<?php

class ServerListUpdater
{
	private static $LIST_SESSION_DETAILS_ENDPOINT = "/api/Game/GetGameDetails";

	private $database;

	public function FinishRecreate()
	{
		$this->database = DB::getInstance();
		RemoteSessionCreationHandler::FinaliseCreateSessionRequest($_POST['session_id']);
	}

	public function UpdateList($checkdemoservers = false)
	{
		$this->database = DB::getInstance();
		

		/*$result = $this->database->query("SELECT address FROM game_servers");
		foreach($result->results() as $server)
		{
			$fulladdr = Config::get("msp_server_protocol").$server->address.Config::get("code_branch")."/";
			$GLOBALS['config']['self_address'] = $fulladdr."ServerManager/";
			Logging::Verbose("Requesting data from ".$fulladdr);
			$serverGames = $this->UpdateListFromServerUrl($fulladdr, $checkdemoservers);
		}*/

		//$serverGames = $this->UpdateListFromServerUrl($servermanager->GetServerURLBySessionId(0), $checkdemoservers);
		foreach ($this->database->get("game_list", array("session_state", "=", "healthy"))->results() as $row => $data) {
			$this->UpdateListFromServerUrl($data->id, $checkdemoservers);
		}
	}

	private function UpdateListFromServerUrl($gameId, $checkdemoservers)
	{
		$servermanager = ServerManager::getInstance();
		$targetServerUrl = $servermanager->GetServerURLBySessionId($gameId);
		Logging::Verbose("About to make the call to ".$targetServerUrl.self::$LIST_SESSION_DETAILS_ENDPOINT);
		$decodedResult = json_decode(CallAPI("POST", $targetServerUrl.self::$LIST_SESSION_DETAILS_ENDPOINT), true);
		Logging::Verbose("Got result ".var_export($decodedResult, true));
		if ($decodedResult["success"])
		{
			Logging::Verbose("Updating server_manager game_list table accordingly.");
			$serverData = $decodedResult["payload"];
			
			if (isset($serverData["game_start_year"]) && 
			isset($serverData["game_end_month"]) && 
			isset($serverData["game_current_month"]) && 
			isset($serverData["game_state"]) && 
			isset($serverData["users_active_last_minute"]) && 
			isset($serverData["users_active_last_hour"])) {
				$args = array(
					$serverData["game_start_year"],
					$serverData["game_end_month"],
					$serverData["game_current_month"],
					$serverData["game_state"],
					$serverData["users_active_last_minute"],
					$serverData["users_active_last_hour"],
					$gameId);
				$this->database->Query("UPDATE game_list
					SET game_start_year = ?,
						game_end_month = ?,
						game_current_month = ?,
						game_state = ?,
						players_active = ?,
						players_past_hour = ?
					WHERE id = ? AND session_state != \"failed\" AND session_state != \"archived\"",
					$args
				);
				Logging::Verbose("Update done.");
			}

			if (isset($gameId) && $checkdemoservers) {
				$demoSession = $this->database->cell("game_list.demo_session", array("id", "=", $gameId));
				if ($demoSession == 1)
				{
					// if it's a demo, then it should continue to tick and even restart at the end
					Logging::Verbose("Treating session ID ".$gameId." as a demo server.");
					Logging::Verbose("Currently in state ".$serverData["game_state"]."");
					if (strcasecmp($serverData["game_state"], "end") == 0)
					{
						Logging::Verbose("Restarting session ID ".$gameId." for demo server");
						$this->RestartGameSession($gameId);
					}
					else if (strcasecmp($serverData["game_state"], "setup") == 0 || strcasecmp($serverData["game_state"], "pause") == 0)
					{
						Logging::Verbose("Force setting session ID ".$gameId." to state PLAY for demo server");
						$result = GameSessionStateChanger::ChangeSessionState($gameId, GameSessionStateChanger::STATE_PLAY);
						Logging::Verbose($result);
					}
					else
					{
						$this->TickGameSession($gameId);
					}
				}
				else 
				{
					// it's not a demo, but still, if the state is play, then we should try to tick the simulation (if the time is right)
					if (strcasecmp($serverData["game_state"], "play") == 0 || strcasecmp($serverData["game_state"], "fastforward") == 0)
					{
						Logging::Verbose("Session ID ".$gameId." is set to PLAY or FASTFORWARD, so going to attempt ticking the simulations.");
						$this->TickGameSession($gameId);
					}
				}					
			}
			
		}
		return true;
	}

	private function RestartGameSession($sessionId)
	{
		$servermanager = ServerManager::getInstance();
		$existingSessionData = $this->database->query(
			"SELECT cv.file_path AS game_config_version_file_path, gl.password_admin AS password_admin, gl.password_player AS password_player, gs.address AS game_server_address, gw.address AS game_watchdog_server_address 
			FROM game_list gl 
				JOIN game_config_version cv ON gl.game_config_version_id = cv.id 
				JOIN game_servers gs ON gl.game_server_id = gs.id 
				JOIN game_watchdog_servers gw ON gl.watchdog_server_id = gw.id
			WHERE gl.id = ?", array($sessionId));
		if ($existingSessionData->count())
		{
			Logging::Verbose("Found existing data");
			$existingData = $existingSessionData->first();
			$configFilePath = $existingData->game_config_version_file_path; //$result->first()->file_path;
			$fullConfigFilePath = GetConfigBaseDirectory().$configFilePath;
			//Logging::Verbose("Here it is: ".var_export($existingData, true));
			Logging::Verbose("Found game server ".$existingData->game_server_address." and watchdog address ".$existingData->game_watchdog_server_address." and config path ".$fullConfigFilePath.". Requesting restart.");
			
			$response = RemoteSessionCreationHandler::SendCreateSessionRequest(
					$fullConfigFilePath,
					$sessionId,
					$existingData->password_admin,
					$existingData->password_player,
					$existingData->game_watchdog_server_address,
					$existingData->game_server_address,
					true,
					'UpdateServerList_'.$servermanager->GetServerID()
			);
			
			Logging::Verbose($response);

			if ($response['status'] == "success")
			{
				$now = time();
				$this->database->query("UPDATE game_list SET session_state = ?, game_creation_time = ?, game_running_til_time = ? WHERE id = ?", array("request", $now, $now, $sessionId));
			}
		}
	}

	private function TickGameSession($sessionId)
	{
		Logging::Verbose("Attempting to tick server session ".$sessionId);
		/*$serverGameAddress = $this->database->query("SELECT game_servers.address
			FROM game_list
				INNER JOIN game_servers ON game_list.game_server_id = game_servers.id
			WHERE game_list.id = ?", array($sessionId));
		if ($serverGameAddress->count() == 1)
		{*/
			$servermanager = ServerManager::getInstance();

			$targetApiAddress = $servermanager->GetServerURLBySessionId($sessionId)."/api/game/tick/";
			Logging::Verbose("Requesting ".$targetApiAddress);

			$additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($sessionId));
			/*$curl_handler = curl_init();
			curl_setopt($curl_handler, CURLOPT_URL, $targetApiAddress);
			curl_setopt($curl_handler, CURLOPT_HTTPHEADER, $additionalHeaders);
			// Receive server response ...
			curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, true);*/
			$server_output = CallAPI("POST", $targetApiAddress, array(), $additionalHeaders, false); //curl_exec($curl_handler);
			//curl_close ($curl_handler);

			Logging::Verbose("Server response from tick request: ".$server_output);
		//}
	}
}

?>