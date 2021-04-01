<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

class ExistingSession
{
	public $response_array = array();
	private $db;
	private $now;

	public $post_ok;
	private $ID;
	private $Name;
	private $ConfigVersionID;
	private $ConfigFilePathAndName;
	private $GameServerID;
	private $GameServerAddress;
	private $GeoServerID;
	private $GeoServerAddress;
	private $GeoServerUsername;
	private $GeoServerPassword;
	private $WatchdogID;
	private $WatchdogAddress;
	private $AdminPassword;
	private $PlayerPassword;
	private $Visibility;
	private $Token;

	public function __construct() {
		header('Content-type: application/json');
		$this->now = time();
		$this->response_array['status'] = 'error';
		$this->response_array['message'] = 'Something went wrong.';
		
		if(!empty($_POST['session_id']) && !empty($_POST['Token'])) {
			$this->ID = (int) $_POST['session_id'];
			$this->Token = $_POST['Token'];
			$this->post_ok = true;
		}
		else {
			$this->post_ok = false;
		}
		$this->db = DB::getInstance();
	}

	private function setGameServerAddressById() {
		// get the server ID
		$this->db->get("game_servers", ["id","=",$this->GameServerID]);
		$this->GameServerAddress = $this->db->results()[0]->address;
	}

	private function setGeoServerDetailsById() {
		$this->db->get("game_geoservers", ["id","=",$this->GeoServerID]);
		$this->GeoServerAddress = $this->db->results()[0]->address;
		$this->GeoServerUsername = $this->db->results()[0]->username;
		$this->GeoServerPassword = $this->db->results()[0]->password;
	}

	private function setWatchdogAddressById() {
		// get the watchdog address
		$this->db->get("game_watchdog_servers", ["id","=",$this->WatchdogID]);
		$this->WatchdogAddress = $this->db->results()[0]->address;
	}

	private function setConfigFilePathAndName() {
		if ($this->db->query("SELECT game_config_version.file_path FROM game_config_version WHERE game_config_version.id = ?", array($this->ConfigVersionID))) {
			$configFilePath = $this->db->results()[0]->file_path;
			$this->ConfigFilePathAndName = ServerManager::getInstance()->GetConfigBaseDirectory().$configFilePath;
		} else {
			$this->ConfigFilePathAndName = null;
		}
	}

	public function getSessionandCall() {
		$this->db->findById($this->ID, "game_list");
		$thissession = $this->db->results(true);
		if (isset($thissession[0])) {
			$this->Name = $thissession[0]["name"];
			$this->ConfigVersionID = $thissession[0]["game_config_version_id"];
			$this->GameServerID = $thissession[0]["game_server_id"];
			$this->GeoServerID = $thissession[0]["game_geoserver_id"];
			$this->WatchdogID = $thissession[0]["watchdog_server_id"];
			$this->AdminPassword = $thissession[0]["password_admin"];
			$this->PlayerPassword = $thissession[0]["password_player"];
			$this->setGameServerAddressById();
			$this->setGeoServerDetailsById();
			$this->setWatchdogAddressById();
			$this->setConfigFilePathAndName();
			$this->buildAndSendRequest();
			// change the state locally to request to indicate things gettin' started
			$now = time();
			$this->db->query("UPDATE game_list SET session_state = ?, game_creation_time = ?, game_running_til_time = ? WHERE id = ?", array("request", $now, $now, $this->ID));
		}
	}

	public function buildAndSendRequest() {
		$this->response_array = RemoteSessionCreationHandler::SendCreateSessionRequest(
			$this->ConfigFilePathAndName, 
			$this->ID, 
			$this->AdminPassword, 
			$this->PlayerPassword, 
			$this->GeoServerID, 
			$this->GeoServerAddress, 
			$this->GeoServerUsername, 
			$this->GeoServerPassword, 
			$this->WatchdogAddress, 
			$this->GameServerAddress,
			true, // recreate
			$this->Token
		);
	}
}

$existingSession = new ExistingSession();
if ($existingSession->post_ok) {
	$existingSession->getSessionandCall();
}
echo json_encode($existingSession->response_array);


// next API
// session_id
// game_start_year
// game_end_month
// game_current_month
// game_state
// session_state

?>
