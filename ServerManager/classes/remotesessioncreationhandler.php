<?php

class RemoteSessionCreationHandler
{
	public static function FinaliseCreateSessionRequest($session_id) {
		if (!empty($_POST['game_start_year']) && !empty($_POST['game_end_month']) && isset($_POST['game_current_month']) && !empty($_POST['game_state']) && !empty($_POST['session_state']) && !empty($_POST['game_planning_realtime']) && !empty($_POST['access_token']) && !empty($session_id)) {
			$db = DB::getInstance();
			$query_string = " UPDATE `game_list`" .
					" SET game_start_year = ?, game_end_month = ?, game_current_month = ?, game_state = ?, session_state = ?, game_running_til_time = game_running_til_time + ?, api_access_token = ?" .
					" WHERE id = ?";
			$where_array = [$_POST['game_start_year'], $_POST['game_end_month'], $_POST['game_current_month'], $_POST['game_state'], $_POST['session_state'], $_POST['game_planning_realtime'], $_POST['access_token'], $session_id];
			return $db->query($query_string, $where_array);
		}
		return false;
	}

	public static function SendCreateSessionRequest($configFilePath, $sessionId, $adminPassword, $playerPassword, $watchdogAddress, $remoteGameServerAddress, $allowRecreate, $jwt) {
		$servermanager = ServerManager::getInstance();

		$response = array('status' => "error", "message" => "Unknown", "api" => "");

		$pathToApi = $servermanager->GetFullSelfAddress()."api/sessionsetupcompleted.php";

		if(file_exists($configFilePath)) {
			$configContents = file_get_contents($configFilePath);

			$configArray = json_decode($configContents, true);
			if (!empty($configArray["datamodel"]["region"])) {
				$params = array("region" => $configArray["datamodel"]["region"],
								"jwt" => $jwt,
								"audience" => $servermanager->GetBareHost(),
								"session_id" => $sessionId,
								"server_id" => $servermanager->GetServerID());
				CallAPI("POST", Config::get('msp_auth/api_endpoint').'logcreatejwt.php', $params);	
			}

			$request_array = array();
			$request_array['game_id'] = $sessionId;
			$request_array['config_file_content'] = $configContents;
			$request_array['password_admin'] = $adminPassword;
			$request_array['password_player'] = $playerPassword;
			$request_array['watchdog_address'] = $watchdogAddress;
			$request_array['response_address'] = $pathToApi;
			$request_array['jwt'] = $jwt;

			if ($allowRecreate == true)
			{
				$request_array['allow_recreate'] = 1;
			}

			$additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($sessionId));

			$api_url = Config::get('msp_server_protocol').$remoteGameServerAddress.Config::get('code_branch')."/api/GameSession/CreateGameSession";
			
			//$response['message'] = $pathToApi;
			/*$curl_handler = curl_init();
			curl_setopt($curl_handler, CURLOPT_URL, $api_url);
			curl_setopt($curl_handler, CURLOPT_POSTFIELDS, http_build_query($request_array));
			curl_setopt($curl_handler, CURLOPT_HTTPHEADER, $additionalHeaders);
			// Receive server response ...
			curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, true);*/
			$server_output = CallAPI("POST", $api_url, $request_array, $additionalHeaders, false); //curl_exec($curl_handler);
			$decoded_output = json_decode($server_output, true);
			//curl_close ($curl_handler);

			if (json_last_error() != JSON_ERROR_NONE)
			{
				$response['status'] = 'error';
				$response['message'] = 'JSON deserialization error: '.json_last_error_msg().' Input response: '.$server_output;
				$response['api'] = $pathToApi;
			}
			// Further processing ...
			else if ($decoded_output['success']) {
				$response['status'] = 'success';
				$response['message'] = ' New server requested with the server ID '.$sessionId.'. Please be patient while the server is being finalised. This typically takes several minutes. ';//.var_export($api_url, true).var_export($request_array, true).var_export($additionalHeaders, true);
				$response['api'] = $pathToApi;
			} else {
				$response['status'] = 'error';
				$response['message'] = 'Server responded: '.$decoded_output["message"];
				$response['api'] = $pathToApi;
			}
		} else {
				$response['status'] = 'error';
				$response['message'] = 'Cannot find the Config file ('.$configFilePath.') !';
		}

		return $response;
	}
};

?>
