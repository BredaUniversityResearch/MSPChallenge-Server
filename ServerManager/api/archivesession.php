<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

function SendArchiveRequest($gameSessionId)
{
	global $us_url_root;
	$servermanager = ServerManager::getInstance();

	$remoteApiCallPath = '/api/gamesession/ArchiveGameSession';
	$api_url = $servermanager->GetServerURLBySessionId($gameSessionId) . $remoteApiCallPath;

	$additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($gameSessionId));

	$responseUrl = $servermanager->GetFullSelfAddress().'api/archivesessioncompleted.php';
	$postValues = array("response_url" => $responseUrl);
	
	$server_output = CallAPI("POST", $api_url, $postValues, $additionalHeaders, false);

	return $server_output;
}

$session_id = $_POST['session_id'];

header('Content-type: application/json');
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

	switch ($sessionData['game_state']) {
		case 'setup':
		case 'pause':
		case 'play':
		case 'end':
			$resultText = SendArchiveRequest($session_id);
			$result = json_decode($resultText, true);
			if ($result['success']) {
				$db->query("UPDATE game_list SET session_state = \"archived\" WHERE id = ?", array($session_id));
				$response_array['status'] = 'success';
				$response_array['message'] = 'Archiving session with the following ID: '.$session_id.'. This process typically takes one or two minutes to fully complete.';
			}
			else {
				$response_array['status'] = 'error';
				$response_array['message'] = 'The external API returned failure for archiving of session "'.$session_id.'". External API returned: '.$result['message'].'.';
			}
			break;
		case 'simulation':
			$response_array['status'] = 'error';
			$response_array['message'] = 'The session with the ID "'.$session_id.'" can	not be archived now because of its state ('.$sessionData['game_state'].')!';
			break;
		default:
			$response_array['status'] = 'error';
			$response_array['message'] = 'Unknown session status found ("'.htmlentities($sessionData['game_state'], ENT_QUOTES).'")!';
			break;
	}
}

echo json_encode($response_array);
?>
