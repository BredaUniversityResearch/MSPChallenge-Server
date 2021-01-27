<?php
//require_once '../init.php'; 
// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';

$db = DB::getInstance();

//file_put_contents($abs_app_root . $url_app_root . "log/sessionsetupcompleted.txt", var_export($_POST, true));

//header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';

$required = array('session_id', 'game_start_year', 'game_end_month', 'game_current_month', 'game_state', 'session_state', 'game_planning_realtime', 'access_token');

// Forms posted
if(!empty($_POST['session_id'])){
	// Loop over field names, make sure each one exists and is not empty
	$missing = '';
	$error = false;
	foreach($required as $field) {
		if (!isset($_POST[$field])) {
			$missing .= $field;
			$error = true;
		}
	}
	if ($error) {
		$response_array['status'] = 'error';
		$response_array['message'] = 'Missing values in POST.';
		$response_array['missing'] = $missing;
	} else {
		// session ID given??
		$session_id = $_POST['session_id'];
		if($db->get("game_list",["id","=",$session_id])) {
			$current_session_state = $db->results()[0]->session_state;
			if($current_session_state == 'request') {
				// only go on if session is in request state
				if (RemoteSessionCreationHandler::FinaliseCreateSessionRequest($session_id)) {
					$response_array['status'] = 'success';
					$response_array['message'] = 'Session information updated in the database.';
				} else {
					$response_array['status'] = 'error';
					$response_array['message'] = $db->errorInfo();
				}
			} else {
				$response_array['status'] = 'error';
				$response_array['message'] ='Session is not in request state. Aborting.';
			}
		} else {
			$response_array['status'] = 'error';
			$response_array['message'] = $db->errorInfo();
		}
	}
}
echo json_encode($response_array);
?>
