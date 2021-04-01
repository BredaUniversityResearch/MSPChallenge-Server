<?php

class RemoteSessionCreationHandler
{
	public static function FinaliseCreateSessionRequest($session_id) {
		if (!empty($_POST['game_start_year']) && !empty($_POST['game_end_month']) && isset($_POST['game_current_month']) && !empty($_POST['game_state']) && !empty($_POST['session_state']) && !empty($_POST['game_planning_realtime']) && isset($_POST['access_token']) && !empty($session_id)) {
			$db = DB::getInstance();
			$query_string = " UPDATE `game_list`" .
					" SET game_start_year = ?, game_end_month = ?, game_current_month = ?, game_state = ?, session_state = ?, game_running_til_time = game_running_til_time + ?, api_access_token = ?" .
					" WHERE id = ?";
			$where_array = [$_POST['game_start_year'], $_POST['game_end_month'], $_POST['game_current_month'], $_POST['game_state'], $_POST['session_state'], $_POST['game_planning_realtime'], $_POST['access_token'], $session_id];
			return $db->query($query_string, $where_array);
		}
		return false;
	}

	public static function SendCreateSessionRequest($configFilePath, $sessionId, $adminPassword, $playerPassword, $geoserverID, $geoserverAddress, $geoserverUsername, $geoserverPassword, $watchdogAddress, $remoteGameServerAddress, $allowRecreate, $jwt) {
		$servermanager = ServerManager::getInstance();

		$response = array('status' => "error", "message" => "Unknown", "api" => "");

		$pathToApi = $servermanager->GetFullSelfAddress()."api/sessionsetupcompleted.php";

	
		if(file_exists($configFilePath)) 
		{
			$configContents = file_get_contents($configFilePath);

			$servermanager = ServerManager::getInstance();
			
			if ($geoserverID == 1) 
			{
				// call the Authoriser to get the public MSP Challenge GeoServer details
				$params = array("jwt" => $jwt,
								"audience" => $servermanager->GetBareHost());
				$json_response = CallAPI("POST", $servermanager->GetMSPAuthAPI()."geocredjwt.php", $params);
				$return = json_decode($json_response, true);
				//die(var_dump($response));
				if ($return["success"]) {
					$geoserverAddress = $return["credentials"]["baseurl"];
					$geoserverUsername = $return["credentials"]["username"];
					$geoserverPassword = $return["credentials"]["password"];	
				}
				else {
					$response['message'] = "Could not obtain the public MSP Challenge GeoServer credentials, so cannot continue";
					return $response;
				}
			} else {
				// final check on validity of the GeoServer URL at least
				// needs to have a protocol in it, e.g. https://
				$geoserverAddress = filter_var($geoserverAddress, FILTER_VALIDATE_URL);
				if ($geoserverAddress === false)
				{
					$response['message'] = "The GeoServer address is not a fully-qualified URL.";
					return $response;
				}
				// needs to end with a slash /
				if (substr($geoserverAddress, -1) != "/") $geoserverAddress .= "/";
			}

			// log the request that's about to be made
			$configArray = json_decode($configContents, true);
			if (!empty($configArray["datamodel"]["region"])) 
			{
				$params = array("region" => $configArray["datamodel"]["region"],
								"jwt" => $jwt,
								"audience" => $servermanager->GetBareHost(),
								"session_id" => $sessionId,
								"server_id" => $servermanager->GetServerID());
				CallAPI("POST", $servermanager->GetMSPAuthAPI().'logcreatejwt.php', $params);	
			}

			$request_array = array();
			$request_array['game_id'] = $sessionId;
			$request_array['config_file_content'] = $configContents;
			$request_array['geoserver_url'] = $geoserverAddress;
			$request_array['geoserver_username'] = $geoserverUsername;
			$request_array['geoserver_password'] = $geoserverPassword;
			$request_array['password_admin'] = $adminPassword;
			$request_array['password_player'] = $playerPassword;
			$request_array['watchdog_address'] = $watchdogAddress;
			$request_array['response_address'] = $pathToApi;

			if ($allowRecreate == true)
			{
				$request_array['allow_recreate'] = 1;
			}

			$additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($sessionId));

			$api_url = $servermanager->GetServerURLBySessionId()."/api/GameSession/CreateGameSession";
			
			$server_output = CallAPI("POST", $api_url, $request_array, $additionalHeaders, false);
			$decoded_output = json_decode($server_output, true);

			if (json_last_error() != JSON_ERROR_NONE)
			{
				$response['message'] = 'JSON deserialization error: '.json_last_error_msg().' Input response: '.$server_output;
				$response['api'] = $pathToApi;
			}
			// Further processing ...
			else if ($decoded_output['success']) {
				$response['status'] = 'success';
				$response['message'] = ' New session requested with the server ID '.$sessionId.'. Please be patient while the session is being finalised. This typically takes several minutes. ';
				$response['api'] = $pathToApi;
			} else {
				$response['message'] = 'Server responded: '.$decoded_output["message"];
				$response['api'] = $pathToApi;
			}
		} 
		return $response;
	}
};

?>
