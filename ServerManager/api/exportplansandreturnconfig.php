<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

function SendRequestPlanExportRequest($gameSessionId)
{
	global $us_url_root;
	$servermanager = ServerManager::getInstance();

	$remoteApiCallPath = '/api/plan/ExportPlansToJson';

	$additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($gameSessionId));
	$api_url = $servermanager->GetServerURLBySessionId($gameSessionId).$remoteApiCallPath;
	/*$curl_handler = curl_init($api_url);
	curl_setopt($curl_handler, CURLOPT_HTTPHEADER, $additionalHeaders);
	curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handler, CURLOPT_POST, 1); //number of parameters sent*/
  //  curl_setopt($curl_handler, CURLOPT_POSTFIELDS, "session=". $gameSessionId); //parameters data

	$server_output = CallAPI("POST", $api_url, array(), $additionalHeaders, false); //curl_exec($curl_handler);
	//curl_close ($curl_handler);

	return $server_output;
}

function GetConfigFileContentsForSessionId($gameSessionId, &$configFileName)
{
	$configFileId = intval($gameSessionId);
	$db = DB::getInstance();
	$db->query("SELECT game_config_version.file_path
		FROM game_config_version
			INNER JOIN game_list ON game_list.game_config_version_id = game_config_version.id
		WHERE game_list.id = ?",
		array($configFileId));
	$results = $db->results(true);
	if (count($results) == 0)
	{
		$response_array['message'] = "Unknown config version id";
		die(json_encode($response_array));
	}
	$storeFilePath = ServerManager::getInstance()->GetConfigBaseDirectory().$results[0]['file_path'];

	$configFileName = basename($results[0]['file_path'], ".json");

	$rawJson = file_get_contents($storeFilePath);
	$decodedJson = json_decode($rawJson);
	return $decodedJson;
}

$session_id = intval($_GET['session_id']);

$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';

if(!empty($session_id)){
	$db = DB::getInstance();
	$db->query("SELECT game_state, session_state FROM game_list WHERE id = ?", array($session_id));
	$sessionslist = $db->results(true);

	if (empty($sessionslist)) {
		$response_array['message'] = 'Unknown session ID given.';
		echo json_encode($response_array);
		return;
	}

	$sessionData = $sessionslist[0];

	if ($sessionData['session_state'] == 'archived') {
		$response_array['status'] = 'error';
		$response_array['message'] = 'The session with the ID "'.$session_id.'" is already archived!';
		echo json_encode($response_array);
		return;
	}
	if ($sessionData['session_state'] == 'request') {
		$response_array['message'] = 'The session with the ID "'.$session_id.'" is still being setup';
		echo json_encode($response_array);
		return;
	}


	$resultText = SendRequestPlanExportRequest($session_id);
	$result = json_decode($resultText, true);

	if ($result["success"] == 1)
	{
		$configFileName = "";
		$configFileDecoded = GetConfigFileContentsForSessionId($session_id, $configFileName);

		if (isset($configFileDecoded->datamodel))
		{
			$configFileDecoded->datamodel->plans = $result["payload"];
		}
		else
		{
			$configFileDecoded->plans = $result["payload"];
		}

		$resultDownload = json_encode($configFileDecoded, JSON_PRETTY_PRINT);

		header('Content-Type: application/x-download');
		header("Content-Disposition: attachment; filename=".$configFileName."_With_Exported_Plans.json;");
		header('Content-Length: ' . strlen($resultDownload));
		print($resultDownload);
		return;
	}
	else
	{
		$response_array["message"] = "Failure in export: <br />".$result["message"];
	}
}

header('Content-type: application/json');
echo json_encode($response_array);

?>
