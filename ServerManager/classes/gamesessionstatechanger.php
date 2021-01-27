<?php

//require_once "../scripts/class.database.php";
//require_once "../init.php";

class GameSessionStateChanger
{
	const STATE_PLAY = "play";
	const STATE_PAUSE = "pause";

	public static function ChangeSessionState($a_SessionId, $a_RequestedState)
	{
		$db = DB::getInstance();

		$response_array["status"] = "error";
		$response_array["message"] = "Could not find server data with specified ID";

		$can_change = false;
		$serverData = $db->query("SELECT game_state, session_state, game_server_id FROM game_list WHERE id = ?", array($a_SessionId));
		if($serverData->count()) {
			$current_game_state = $serverData->first()->game_state;
			$current_session_state = $serverData->first()->session_state;
			$game_server_id = $serverData->first()->game_server_id;
			if($current_session_state == 'healthy') {
				// only work with healthy session
				if (strcasecmp($a_RequestedState, $current_game_state) == 0)
				{
					$response_array['status'] = 'error';
					$response_array['message'] = 'The session with the ID "'.$a_SessionId.'" is already in the requested state! ('.$a_RequestedState.' vs '.$current_game_state.')';
				}
				else
				{
					switch ($current_game_state) {
						case 'play':
							$can_change = true;
							break;
						case 'pause':
							$can_change = true;
							break;
						case 'fastforward':
							$can_change = true;
							break;
						case 'end':
							$response_array['status'] = 'error';
							$response_array['message'] = 'The session with the ID "'.$a_SessionId.'" has already ended!';
							break;
						case 'setup':
							if ($a_RequestedState == self::STATE_PLAY)
							{
								$can_change = true;
							}
							else
							{
								$response_array['status'] = 'error';
								$response_array['message'] = 'The session with the ID "'.$a_SessionId.'" is being set up!';
							}
							break;
						case 'simulation':
							$response_array['status'] = 'error';
							$response_array['message'] = 'The session with the ID "'.$a_SessionId.'" is in simulation state!';
							break;
						default:
							$response_array['status'] = 'error';
							$response_array['message'] = 'Unknown session status found ("'.htmlentities($current_game_state, ENT_QUOTES).'")!';
							break;
					}
				}
			}
		}

		if($can_change) {
			$request_array = array();
			$request_array['state'] = $a_RequestedState;
			$servermanager = ServerManager::getInstance();
			
			$additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($a_SessionId));

			$ApiCallPath = '/api/game/State';
			$api_url = $servermanager->GetServerURLBySessionId($a_SessionId) . $ApiCallPath;

			/*$curl_handler = curl_init();
			curl_setopt($curl_handler, CURLOPT_URL, $api_url);
			curl_setopt($curl_handler, CURLOPT_POSTFIELDS, http_build_query($request_array));
			curl_setopt($curl_handler, CURLOPT_HTTPHEADER, $additionalHeaders);
			// Receive server response ...
			curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, true);*/
			$server_output = CallAPI("POST", $api_url, $request_array, $additionalHeaders, false); //curl_exec($curl_handler);
			$decoded_output = json_decode($server_output, true);
			//curl_close ($curl_handler);

			// Further processing ...
			if (json_last_error() === JSON_ERROR_NONE && $decoded_output['success'] == 1) {
				$db->query("UPDATE game_list SET game_state = ? WHERE id = ?", array($a_RequestedState, $a_SessionId));

				$response_array['status'] = 'success';
				$response_array['message'] = 'Successfully changed session state to "'.$a_RequestedState.'" for session id: '.$a_SessionId;
			} else {
				$response_array['status'] = 'error';
				$response_array['message'] = 'GameServer responded: "'.$server_output.'" with url request: '.$api_url;
			}
		}

		return $response_array;
	}
}
?>
