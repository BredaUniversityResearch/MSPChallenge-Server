<?php
require_once '../init.php'; 

//$user->hastobeLoggedIn();

class NewSession
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
		$this->post_ok = $this->getPostValues();
		$this->now = time();
		$this->response_array['status'] = 'error';
		$this->response_array['message'] = 'Something went wrong.';

		if($this->post_ok) {
			$this->db = DB::getInstance();
			$this->setGameServerAddressById();
			$this->setGeoServerDetailsById();
			$this->setWatchdogAddressById();
			$this->setConfigFilePathAndName();
		}
	}

	private function getPostValues () {
		// Required field names
		$required = array('name', 'configVersion', 'gameServer', 'GeoServer', 'watchdog', 'adminPassword', 'visibility', 'Token');

		// Loop over field names, make sure each one exists and is not empty
		$error = false;
		foreach($required as $field) {
			if (empty($_POST[$field])) {
				$error = true;
			}
		}

		if ($error) {
			$this->response_array['status'] = 'error';
			$this->response_array['message'] = 'No values in POST.';
			return false;
		} else {
			$this->Name = $_POST['name'];
			$this->ConfigVersionID = $_POST['configVersion'];
			$this->GameServerID = $_POST['gameServer'];
			$this->GeoServerID = $_POST['GeoServer'];
			$this->WatchdogID = $_POST['watchdog'];
			$this->AdminPassword = $_POST['adminPassword'];
			$this->PlayerPassword = !empty($_POST['playerPassword']) ? $_POST['playerPassword'] : '';
			$this->Visibility = $_POST['visibility'];
			$this->Token = $_POST['Token'];

			return true;
		}
	}

	private function setNewlyCreatedSessionId() {
		// get the new session ID
		//$this->db->get("game_list",["game_creation_time","=",$this->now]);
		//$this->ID = $this->db->results()[0]->id;
		$this->ID = $this->db->lastId();
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

	public function createNewSessionLocalDB () {
		$where_array = array();
		$query_string = " INSERT INTO `game_list`" .
						" (name, game_config_version_id, game_server_id, game_geoserver_id, watchdog_server_id," .
						"  game_creation_time, game_start_year, game_end_month, game_current_month, game_running_til_time," .
						"  password_admin, password_player, session_state, game_state, game_visibility, players_active, players_past_hour, server_version)" .
						" VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

		if(!empty($_POST)){
			$where_array = [$this->Name,
							$this->ConfigVersionID,
							$this->GameServerID,
							$this->GeoServerID,
							$this->WatchdogID,
							$this->now,
							0,
							0,
							0,
							$this->now,
							$this->AdminPassword,
							$this->PlayerPassword,
							'request',
							'setup',
							$this->Visibility,
							0,
							0,
							ServerManager::getInstance()->GetCurrentVersion()];
			if ($this->db->query($query_string, $where_array)) {
				$this->setNewlyCreatedSessionId();
				$this->setLastPlayedTime();
			}
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
			false, //no recreate
			$this->Token);
	}

	public function setLastPlayedTime() {
		$query_string = "UPDATE game_config_version SET last_played_time = UNIX_TIMESTAMP() WHERE id = ?";
		$where_array = [$this->ConfigVersionID];
		$this->db->query($query_string, $where_array);
	}
}

$newSession = new NewSession();
if ($newSession->post_ok) {
	$newSession->createNewSessionLocalDB();
	$newSession->buildAndSendRequest();
}
echo json_encode($newSession->response_array);


// next API
// session_id
// game_start_year
// game_end_month
// game_current_month
// game_state
// session_state

?>
