<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

function SendSaveRequest($gameSessionId, $preferredname, $preferredfolder, $save_type)
{
	global $us_url_root;
	$servermanager = ServerManager::getInstance();
	
	if ($save_type == "full") {
		$remoteApiCallPath = '/api/gamesession/CreateGameSessionZip';
	}
	elseif ($save_type == "layers") {
		$remoteApiCallPath = '/api/gamesession/CreateGameSessionLayersZip';
	}
	else return false;

	$additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($gameSessionId));

	$postValues = array("preferredname" => $preferredname, 
						"preferredfolder" => $preferredfolder, 
						"nooverwrite" => true, 
						"response_url" => $servermanager->GetFullSelfAddress()."api/savesessioncompleted.php");

	$api_url = $servermanager->GetServerURLBySessionId($gameSessionId) . $remoteApiCallPath;
	
	$return_json = CallAPI("POST", $api_url, $postValues, $additionalHeaders, false); 
	return true;
}

$session_id = $_POST['session_id'] ?? '';
$save_type = $_POST['save_type'] ?? 'full';
if ($save_type != 'full' && $save_type != 'layers') $save_type = 'full';

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No server ID given.';

if(!empty($session_id) && !empty($_POST['Token'])){
	$db = DB::getInstance();
	$db->query("SELECT game_state, session_state, game_current_month FROM game_list WHERE id = ?", array($session_id));
	$sessionslist = $db->results(true);

	if (empty($sessionslist)) {
		$response_array['message'] = 'Unknown session ID given.';
		echo json_encode($response_array);
		return;
	}

	$sessionData = $sessionslist[0];

	if ($sessionData['session_state'] == 'archived') {
		$response_array['status'] = 'error';
		$response_array['message'] = 'Cannot save server "'.$session_id.'" as it has already been archived.';
		echo json_encode($response_array);
		return;
	}
	if ($sessionData['session_state'] == 'request') {
		$response_array['message'] = 'The session with the ID "'.$session_id.'" is still being setup';
		echo json_encode($response_array);
		return;
	}

	switch ($sessionData['game_state']) {
		case 'setup':
		case 'pause':
		case 'play':
		case 'end':
			if ($save_type == "full") {
				$preferredname = "save_".$sessionData['game_current_month']."_";
				$preferredname2send = $preferredname;
				$servermanager_folder = "saves/";
				$preferredfolder = "/ServerManager/".$servermanager_folder;
				$save_path = $servermanager_folder.$preferredname.$session_id.".zip";
			}
			elseif ($save_type == "layers") {
				$preferredname = "layers_".$sessionData['game_current_month']."_";
				$preferredname2send = "temp_".$preferredname;
				$servermanager_folder = "saves/";
				$preferredfolder = "/ServerManager/".$servermanager_folder;
				$save_path = $servermanager_folder.$preferredname.$session_id.".zip";
			}
			
			// first update the ServerManager database
			$updater = new ServerListUpdater();
			$updater->UpdateList();

			$result = SendSaveRequest($session_id, $preferredname2send, $preferredfolder, $save_type);
			if ($result) {
				// add record to database
				$response_array['status'] = 'success';
				$response_array['message'] = 'Saving server with the following ID: '.$session_id.'. This process typically takes a little while to fully complete. After that, your save will be available under the Saves tab. Please be patient.';
			}
			else {
				$response_array['status'] = 'error';
				$response_array['message'] = 'The external API returned failure for saving of server "'.$session_id.'".';
			}

			break;
		case 'simulation':
			$response_array['status'] = 'error';
			$response_array['message'] = 'The server with the ID "'.$session_id.'" cannot be saved now because of its state ('.$sessionData['game_state'].')!';
			break;
		default:
			$response_array['status'] = 'error';
			$response_array['message'] = 'Unknown server status found ("'.htmlentities($sessionData['game_state'], ENT_QUOTES).'")!';
			break;
	}
}

echo json_encode($response_array);
?>
