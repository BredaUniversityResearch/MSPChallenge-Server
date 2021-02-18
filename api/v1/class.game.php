<?php
	class Game extends Base{
		public static $CountryIndexOffset = 3; //Countries have an offset, so this indicates the first country ID that the game will use. (Admin and Region Master are inserted before the countries, thats why this exists)
		
		private $watchdog_address = '';
		private $watchdog_port = 45000;

		protected $allowed = array(
			"AutoSaveDatabase", 
			["Config", Security::ACCESS_LEVEL_FLAG_NONE], //Required for login
			"FutureRealtime", 
			"GetActualDateForSimulatedMonth",
			"GetCurrentMonth",
			["GetGameDetails", Security::ACCESS_LEVEL_FLAG_NONE],
			"GetWatchdogAddress", 
			"IsOnline", 
			"Latest",
			"Meta", 
			"NextMonth", 
			"Planning",
			"Realtime", 
			["Setupfilename", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER], 
			"Speed", 
			["StartWatchdog", Security::ACCESS_LEVEL_FLAG_NONE], 
			["State", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER], 
			"TestWatchdogAlive",
			["Tick", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER] // Required for serverlistupdater.php to work in case of demo server
		);

		public function __construct($str=""){
			parent::__construct($str);
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/AutoSaveDatabase AutoSaveDatabase
		 * @apiDescription Creates a session database dump with the naming convention AutoDump_YYY-mm-dd_hh-mm.sql
		 */
		public function AutoSaveDatabase()
		{
			if (strstr($_SERVER['REQUEST_URI'], 'dev')) {
				return; //Don't create database dumps on dev.
			}

			$outputDirectory = "export/DatabaseDumps/";
			if (!is_dir($outputDirectory)) {
				mkdir($outputDirectory);
			}

			$outputFile = $outputDirectory."AutoDump_".date("Y-m-d_H-i").".sql";
			Database::GetInstance()->CreateMspDatabaseDump($outputFile, false);
		}
		
		/**
		 * @apiGroup Game
		 * @api {POST} /game/Config Config
		 * @apiDescription Obtains the sessions' game configuration 
		 */
		public function Config(){
			$data = $this->GetGameConfigValues();

			$configuredSimulations = array();
			if (isset($data['MEL'])) {
				$configuredSimulations[] = "MEL";
			}
			if (isset($data['SEL'])) {
				$configuredSimulations[] = "SEL";
			}
			if (isset($data['CEL'])) {
				$configuredSimulations[] = "CEL";
			}

			foreach($data as $key => $d){
				if((is_object($data[$key]) || is_array($data[$key])) && $key != "expertise_definitions"){
					unset($data[$key]);
				}
			}

			$data['configured_simulations'] = $configuredSimulations;
			$data['wiki_base_url'] = Config::GetInstance()->WikiConfig()['game_base_url'];

			$passwordData = Database::GetInstance()->query("SELECT game_session_password_admin, game_session_password_player FROM game_session");
			if (count($passwordData) > 0)
			{
				$data["user_admin_has_password"] = !empty($passwordData[0]["game_session_password_admin"]);
				$data["user_common_has_password"] = !empty($passwordData[0]["game_session_password_player"]);
			}

			return $data;
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/NextMonth NextMonth
		 * @apiDescription Updates session database to indicate start of next simulated month
		 */
		public function NextMonth(){
			Database::GetInstance()->query("UPDATE game SET game_currentmonth=game_currentmonth+1");
		}

		public function LoadConfigFile($filename=""){
			if($filename == ""){	//if there's no file given, use the one in the database
				$data = Database::GetInstance()->query("SELECT game_configfile FROM game");

				$path = GameSession::CONFIG_DIRECTORY . $data[0]['game_configfile'];
			}
			else{
				$path = GameSession::CONFIG_DIRECTORY . $filename;
			}

			return file_get_contents($path);
		}

		public function GetAllConfigValues()
		{
			$data = json_decode($this->LoadConfigFile(), true);
			return $data;
		}

		public function GetGameConfigValues($overrideFileName = "")
		{
			$data = json_decode($this->LoadConfigFile($overrideFileName), true);
			if (isset($data["datamodel"]))
			{
				return $data["datamodel"];
			}
			return $data;
		}

		public function WriteToConfig($filename, $data){
			$data = json_encode($data, JSON_FORCE_OBJECT);
			$file = fopen(GameSession::CONFIG_DIRECTORY . $filename, "w");

			fwrite($file, $data);

			fclose($file);
		}

		/**
		* @apiGroup Game
		* @api {POST} /game/GetCurrentMonth GetCurrentMonth
		* @apiDescription Gets the current month of the active game.
		*/
		public function GetCurrentMonth() {
			$result = array("game_currentmonth" => $this->GetCurrentMonthAsId());
			return $result; //Base::JSON($result);
		}

		public function GetCurrentMonthAsId() {
			$currentMonth = Database::GetInstance()->query("SELECT game_currentmonth, game_state FROM game")[0];
			if ($currentMonth["game_state"] == "SETUP") {
				$currentMonth["game_currentmonth"] = -1;
			}
			return $currentMonth["game_currentmonth"];
		}

		public function Setupfilename(string $configFilename){
			Database::GetInstance()->query("UPDATE game SET game_configfile=?", array($configFilename));
		}

		public function SetupCountries($configData) {
			$adminColor = "#FF00FFFF";
			if (array_key_exists("user_admin_color", $configData))
			{
				$adminColor = $configData["user_admin_color"];
			}
			$regionManagerColor = "#00FFFFFF";
			if (array_key_exists("user_region_manager_color", $configData))
			{
				$regionManagerColor = $configData["user_region_manager_color"];
			}

			//Admin country.
			Database::GetInstance()->query("INSERT INTO country (country_id, country_colour, country_is_manager) VALUES (?, ?, ?)", array(1, $adminColor, 1));
			//Region manager country.
			Database::GetInstance()->query("INSERT INTO country (country_id, country_colour, country_is_manager) VALUES (?, ?, ?)", array(2, $regionManagerColor, 1));

			foreach($configData['meta'] as $layerMeta) 
			{
				if ($layerMeta['layer_name'] == $configData['countries'])
				{
					foreach($layerMeta['layer_type'] as $country)
					{
						$countryId = $country['value'];
						Database::GetInstance()->query("INSERT INTO country (country_id, country_colour, country_is_manager) VALUES (?, ?, ?)", array($countryId, $country['polygonColor'], 0 ));
					}
				}
			}
			//Setup Admin Test User so we have a default session we can use for testing.
			Database::GetInstance()->query("INSERT INTO user (user_lastupdate, user_country_id) VALUES(0, 1)");
		}

		public function SetupGametime($data){
			$_POST['user'] = 1; // should this go at some point?
			$this->SetStartDate($data['start']);

			//$_POST['months'] = $data['era_planning_months']; // this should definitely go at some point
			$this->Planning($data['era_planning_months']);

			//$_POST['realtime'] = $data['era_planning_realtime'];
			$this->Realtime($data['era_planning_realtime']);

			$str = "";

			$totaleras = 4;
			for($i = 0; $i < $totaleras; $i++){
				$str .= $data['era_planning_realtime'] . ",";
			}

			$str = substr($str, 0, -1);

			Database::GetInstance()->query("UPDATE game SET game_planning_era_realtime=?, game_eratime=?", array($str, $data['era_total_months']));
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/IsOnline Is Online
		 * @apiDescription Check if the server is online
		 * @apiSuccess {string} online
		 */
		public function IsOnline(){
			return "online";
		}

		//this should probably be moved to Layer instead
		/**
		 * @apiGroup Game
		 * @api {POST} /game/meta Meta
		 * @apiDescription Get all layer meta data required for a game
		 * @apiSuccess {string} JSON object
		 */
		public function Meta(bool $sort = false, bool $onlyActiveLayers = false, int $user){
			Database::GetInstance()->query("UPDATE user SET user_lastupdate=? WHERE user_id=?", array(0, $user));

			$activeQueryPart = "";
			if ($onlyActiveLayers) {
				$activeQueryPart = " AND layer_active = 1 ";
			}

			if($sort){
				$data = Database::GetInstance()->query("SELECT * FROM layer WHERE layer_original_id IS NULL ".$activeQueryPart." ORDER BY layer_name ASC", array());
			}
			else{
				$data = Database::GetInstance()->query("SELECT * FROM layer WHERE layer_original_id IS NULL ".$activeQueryPart."", array());
			}

			for($i = 0; $i < sizeof($data); $i++){
				Layer::FixupLayerMetaData($data[$i]);
			}

			return $data;
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/tick Tick
		 * @apiDescription Tick the game server, updating the plans if required
		 * @apiSuccess {string} JSON object
		 * @ForceNoTransaction
		 */
		public function Tick($showDebug=false) {

			$plan = new Plan();
			$plan->Tick();

			//Update server time and month
			$tick = Database::GetInstance()->query("SELECT game_lastupdate as lastupdate,
				game_currentmonth as month,
				game_planning_gametime as era_gametime,
				game_planning_realtime as era_realtime,
				game_mel_lastmonth as mel_lastmonth,
				game_cel_lastmonth as cel_lastmonth,
				game_sel_lastmonth as sel_lastmonth,
				game_state as state
			FROM game")[0];

			$state = $tick["state"];

			if($state != "END" && $state != "PAUSE" && $state != "SETUP"){

				$currenttime = microtime(true);
				$lastupdate = $tick['lastupdate'];
				$diff = $currenttime - $lastupdate;

				$secondspermonth = $tick['era_realtime'] / $tick['era_gametime'];
				if ($state == "SIMULATION" || $state == "FASTFORWARD")
				{
					$secondspermonth = 0.2;
				}

				if ($diff > $secondspermonth) {
					if ($showDebug) {
						Base::Debug("Trying to tick the server");
					}

					$this->TryTickServer($tick, $showDebug);
				}
				else {
					if ($showDebug) {
						Base::Debug("Waiting for update time ".($secondspermonth - $diff). " seconds remaining");
					}
				}
			}
		}

		private function AreSimulationsUpToDate($tickData){
			$config = $this->GetGameConfigValues();
			if ((isset($config["MEL"]) && $tickData['month'] > $tickData['mel_lastmonth']) ||
				(isset($config["CEL"]) && $tickData['month'] > $tickData['cel_lastmonth']) ||
				(isset($config["SEL"]) && $tickData['month'] > $tickData['sel_lastmonth']))
			{
				return false;
			}
			return true;
		}

		private function CalculateUpdatedTime($showdebug = false){

			$tick = Database::GetInstance()->query("SELECT
				game_state as state,
				game_lastupdate as lastupdate,
				game_currentmonth as month,
				game_start as start,
				game_planning_gametime as era_gametime,
				game_planning_realtime as era_realtime,
				game_planning_era_realtime as planning_era_realtime,
				game_planning_monthsdone as era_monthsdone,
				game_mel_lastmonth as mel_lastmonth,
				game_cel_lastmonth as cel_lastmonth,
				game_sel_lastmonth as sel_lastmonth,
				game_eratime as era_time

			FROM game")[0];

			$state = $tick["state"];
			$secondspermonth = $tick['era_realtime'] / $tick['era_gametime'];

			//only update if the game is playing
			if($state != "END" && $state != "PAUSE" && $state != "SETUP"){
				$currenttime = microtime(true);
				$lastupdate = $tick['lastupdate'];

				//if the last update was at time 0, this is the very first tick happening for this game
				if($lastupdate == 0){
					Database::GetInstance()->query("UPDATE game SET game_lastupdate=?", array(microtime(true)) );
					$lastupdate = microtime(true);
					$currenttime = $lastupdate;
				}

				$diff = $currenttime - $lastupdate;
				$secondspermonth = $tick['era_realtime'] / $tick['era_gametime'];

				if ($diff < $secondspermonth)
				{
					$tick['era_timeleft'] = $tick['era_realtime'] - $diff - ($tick['era_monthsdone'] * $secondspermonth);
				}
				else
				{
					$tick['era_timeleft'] = -1;
				}

				if($showdebug) Base::Debug("diff: " . $diff);

				if($showdebug) Base::Debug("timeleft: " . $tick['era_timeleft']);
			}
			else if($state == "PAUSE" || $state == "SETUP"){
				//[MSP-1116] Seems sensible?
				$tick['era_timeleft'] = $tick['era_realtime'] - ($tick['era_monthsdone'] * $secondspermonth);
				if($showdebug) echo "GAME PAUSED";
			}
			else{
				if($showdebug) echo "GAME ENDED";
			}

			if($showdebug) Base::Debug($tick);

			return $tick;
		}

		private function TryTickServer($tickData, $showDebug) {
			if (!strstr($_SERVER['REQUEST_URI'], 'dev') || Config::GetInstance()->ShouldWaitForSimulationsInDev())
			{
				if(!$this->AreSimulationsUpToDate($tickData)){
					if ($showDebug) {
						Base::Debug("Waiting for simulations to update.");
					}

					return;
				}
			}

			$result = Database::GetInstance()->queryReturnAffectedRowCount("UPDATE game SET game_is_running_update = 1, game_lastupdate = ? WHERE game_is_running_update = 0 AND game_lastupdate = ?", array(microtime(true), $tickData["lastupdate"]));
			if ($result == 1) {
				//Spawn thread eventually.
				if ($showDebug) {
					Base::Debug("Ticking server.");
				}
				$this->ServerTickInternal();
			}
			else if ($showDebug) {
				Base::Debug("Update already in progress.");
			}
		}

		private function ServerTickInternal()
		{
			//Updates time to the next month.
			$tick = Database::GetInstance()->query("SELECT
				game_state as state,
				game_currentmonth as month,
				game_planning_gametime as era_gametime,
				game_planning_realtime as era_realtime,
				game_planning_era_realtime as planning_era_realtime,
				game_planning_monthsdone as era_monthsdone,
				game_eratime as era_time,
				game_autosave_month_interval as autosave_interval_months
			FROM game")[0];

			$state = $tick['state'];

			$monthsdone = $tick['era_monthsdone'] + 1;
			$currentmonth = $tick['month'] + 1;

			//update all the plans which ticks the server.
			$plan = new Plan();
			$plan->UpdateLayerState($currentmonth);

			if($currentmonth >= ($tick['era_time'] * 4)){ //Hardcoded to 4 eras as designed.
				//Entire game is done.
				Database::GetInstance()->query("UPDATE game SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?, game_state=?", array(microtime(true), $currentmonth, $monthsdone, "END"));
				$this->OnGameStateUpdated("END");
			}
			else if(($state == "PLAY" || $state == "FASTFORWARD") && $monthsdone >= $tick['era_gametime'] && $tick['era_gametime'] < $tick['era_time']){
				//planning phase is complete, move to the simulation phase
				Database::GetInstance()->query("UPDATE game SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?, game_state=?", array(microtime(true), $currentmonth, 0, "SIMULATION"));
				$this->OnGameStateUpdated("SIMULATION");
			}
			else if(($state == "SIMULATION" && $monthsdone >= $tick['era_time'] - $tick['era_gametime']) || $monthsdone >= $tick['era_time']){
				//simulation is done, reset everything to start a new play phase
				$era = floor($currentmonth / $tick['era_time']);
				$era_realtime = explode(",", $tick['planning_era_realtime']);
				Database::GetInstance()->query("UPDATE game SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?, game_state=?, game_planning_realtime=?", array(microtime(true), $currentmonth, 0, "PLAY", $era_realtime[$era]));
				$this->OnGameStateUpdated("PLAY");
			}
			else{
				Database::GetInstance()->query("UPDATE game SET game_lastupdate=?, game_currentmonth=?, game_planning_monthsdone=?", array(microtime(true), $currentmonth, $monthsdone));
			}

			if (($tick['month'] % $tick['autosave_interval_months']) == 0) {
				$this->AutoSaveDatabase();
			}

			Database::GetInstance()->query("UPDATE game SET game_is_running_update = 0");
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/planning Planning
		 * @apiParam {int} months the amount of months the planning phase takes
		 * @apiDescription set the amount of months the planning phase takes, should not be done during the simulation phase
		 */
		public function Planning(int $months){
			Database::GetInstance()->query("UPDATE game SET game_planning_gametime=?", array($months));
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/realtime Realtime
		 * @apiParam {int} realtime length of planning phase (in seconds)
		 * @apiDescription Set the duration of the planning phase in seconds
		 */
		public function Realtime(int $realtime){
			Database::GetInstance()->query("UPDATE game SET game_planning_realtime=?", array($realtime));
		}

		private function SetStartDate(int $a_startYear){
			Database::GetInstance()->query("UPDATE game SET game_start=?", array($a_startYear));
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/realtime FutureRealtime
		 * @apiParam {string} realtime comma separated string of all the era times
		 * @apiDescription Set the duration of future eras
		 */
		public function FutureRealtime(string $realtime){
			Database::GetInstance()->query("UPDATE game SET game_planning_era_realtime=?", array($realtime));
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/state State
		 * @apiParam {string} state new state of the game
		 * @apiDescription Set the current game state
		 */
		public function State(string $state){
			$currentState = Database::GetInstance()->query("SELECT game_state FROM game")[0];
			if ($currentState["game_state"] == "END" || $currentState["game_state"] == "SIMULATION") {
				throw new Exception("Invalid current state of ".$currentState["game_state"]); 
			}

			if ($currentState["game_state"] == "SETUP") {
				//Starting plans should be implemented when we any state "PLAY"
				$plan = new Plan();
				$plan->UpdateLayerState(0);
			}

			Database::GetInstance()->query("UPDATE game SET game_lastupdate = ?, game_state=?", array(microtime(true), $state));
			$this->OnGameStateUpdated($state);
		}

		private function OnGameStateUpdated($newGameState) {
			$this->ChangeWatchdogState($newGameState);
		}

		private function GetWatchdogAddress($withport=false) {
			if (!empty($this->watchdog_address)) {
				if ($withport) return $this->watchdog_address.':'.$this->watchdog_port;
				else return $this->watchdog_address;
			}
			else {
				$result = Database::GetInstance()->query("SELECT game_session_watchdog_address FROM game_session LIMIT 0,1");
				if (count($result) > 0)
				{
					$this->watchdog_address = 'http://'.$result[0]['game_session_watchdog_address'];
					if ($withport) return $this->watchdog_address.':'.$this->watchdog_port;
					else return $this->watchdog_address;
				}
				return '';
			}
		}

		private function GetWatchdogSessionUniqueToken() {
			$result = Database::GetInstance()->query("SELECT game_session_watchdog_token FROM game_session LIMIT 0,1");
			if (count($result) > 0) {
				return $result[0]["game_session_watchdog_token"];
			}
			return "0";
		}

		private function TestWatchdogAlive() {
			try {
				$this->CallBack($this->GetWatchdogAddress(true), array(), array(), false, false, array(CURLOPT_CONNECTTIMEOUT => 1));
			}
			catch (Exception $e) {
				return false;
			}
			return true;
		}

		/**
		 * @ForceNoTransaction
		 */
		public function StartWatchdog() {
			self::StartSimulationExe(array("exe" => "MSW.exe", "working_directory" => "simulations/MSW/"));
		}

		private static function StartSimulationExe($params) {
			$apiEndpoint = GameSession::GetRequestApiRoot(); 
			$args = isset($params["args"])? $params["args"]." " : "";
			$args = $args."APIEndpoint ".$apiEndpoint;

			$workingDirectory = "";
			if (isset($params["working_directory"])) {
				$workingDirectory = "cd ".$params["working_directory"]." & ";
			}

			Database::execInBackground('start cmd.exe @cmd /c "'.$workingDirectory.'start '.$params["exe"].' '.$args.'"');
		}

		public function ChangeWatchdogState($newWatchdogGameState) {
				// we want to change the watchdog state, but first we check if it is running
				if (!$this->TestWatchdogAlive()) {
					// so the Watchdog is off, and now it should be switched on
					$requestHeader = apache_request_headers();
					$headers = array();
					if (isset($requestHeader["MSPAPIToken"])) {
						$headers[] = "MSPAPIToken: ".$requestHeader["MSPAPIToken"];
					}
					$success = $this->CallBack($this->GetWatchdogAddress(false).Config::GetInstance()->GetCodeBranch()."/api/Game/StartWatchdog", array(), $headers, true); //curl_exec($ch);
					sleep(3); //not sure if this is necessary
				}

				$apiRoot = GameSession::GetRequestApiRoot();

				$simulationsHelper = new Simulations();
				$simulations = json_encode($simulationsHelper->GetConfiguredSimulationTypes(), JSON_FORCE_OBJECT);
				$security = new Security();
				$newAccessToken = json_encode($security->GenerateToken());
				$recoveryToken = json_encode($security->GetRecoveryToken());

				//If we post this as an array it will come out as a multipart/form-data and it's easier for MSW to manually create the string here.
				$postValues = "game_session_api=".urlencode($apiRoot).
					"&game_session_token=".urlencode($this->GetWatchdogSessionUniqueToken()).
					"&game_state=".urlencode($newWatchdogGameState).
					"&required_simulations=".urlencode($simulations).
					"&api_access_token=".urlencode($newAccessToken).
					"&api_access_renew_token=".urlencode($recoveryToken);

				$response = $this->CallBack($this->GetWatchdogAddress(true)."/Watchdog/UpdateState", $postValues, array()); //curl_exec($ch);
				
				$log = new Log();

				$decodedResponse = json_decode($response, true);
				if (json_last_error() !== JSON_ERROR_NONE)
				{
					$log->PostEvent("Watchdog", Log::Error, "Received invalid response from watchdog. Response: \"".$response."\"", "ChangeWatchdogState()");
					return false;
				}
				else
				{
					if ($decodedResponse["success"] == 1)
					{
						return true;
					}
					else
					{
						$log->PostEvent("Watchdog", Log::Error, "Watchdog responded with failure to change game state request. Response: \"".$decodedResponse["message"]."\"", "ChangeWatchdogState()");
						return false;
					}
				}
		}

		protected function GetUpdateTime($id){
			return Database::GetInstance()->query("SELECT user_lastupdate FROM user WHERE user_id=?", array($id))[0]['user_lastupdate'];
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/latest Latest game data
		 * @apiParam team_id The team_id (country_id) that we want to get the latest data for.
		 * @apiParam last_update_time The last time the client has received an update tick.
		 * @apiParam user The id of the user logged on to the client requesting the update.
		 * @apiDescription Gets the latest plans & messages from the server
		 */
		public function Latest(int $team_id, float $last_update_time, int $user){
			$debugPrefTimings = false;
			$newtime = microtime(true);

			//returns all updated data since the last updated time
			$plan = new Plan("");
			$layer = new Layer("");
			$energy = new Energy("");
			$kpi = new Kpi("");
			$warning = new Warning("");
			$objective = new Objective("");
			$data = array();

			// Because we have varying precision across the database for the last updated times we substract 1 here to make sure we have all the data.
			$time = floatval($last_update_time);

			$data['tick'] = $this->CalculateUpdatedTime(false);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after tick<br />"; }

			$data['plan'] = $plan->Latest($time);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after plan<br />"; }

			foreach($data['plan'] as &$p){
				//only send the geometry when it's required
				$p['layers'] = $layer->Latest($p['layers'], $time, $p['id']);
				if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after layers<br />"; }

				if( ($p['state'] == "DESIGN" && $p['previousstate'] == "CONSULTATION" && $p['country'] != $team_id)){
					$p['active'] = 0;
				}
			}

			$data['planmessages'] = $plan->GetMessages($time);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after plan messages<br />"; }

			//return any raster layers that need to be updated
			$data['raster'] = $layer->LatestRaster($time);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after raster<br />"; }

			$data['energy'] = $energy->Latest($time);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after energy<br />"; }

			$data['kpi'] = $kpi->Latest($time, $team_id);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after kpi<br />"; }

			$data['warning'] = $warning->Latest($time);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after warning<br />"; }

			$data['objectives'] = $objective->Latest($time);
			if ($debugPrefTimings) { echo (microtime(true) - $newtime) . " elapsed after objective<br />"; }

			$data['update_time'] = $newtime - 0.001; //Add a slight fudge of 1ms to the update times to avoid rounding issues.

			// send an empty string if nothing was updated
			if(	empty($data['energy']['connections']) &&
				empty($data['energy']['output']) &&
				empty($data['geometry']) &&
				empty($data['plan']) &&
				empty($data['messages']) &&
				empty($data['planmessages']) &&
				empty($data['kpi']) &&
				empty($data['warning']) &&
				empty($data['raster']) &&
				empty($data['objectives'])){
				return "";
			}
			else{
				Database::GetInstance()->query("UPDATE user SET user_lastupdate=? WHERE user_id=?", array(
						$newtime, $user));
			}
			return $data;
		}

		/**
		 * @apiGroup Game
		 * @api {POST} /game/GetActualDateForSimulatedMonth Set Start
		 * @apiParam {int} simulated_month simulated month ranging from 0..game_end_month
		 * @apiDescription Returns year and month ([1..12]) of the current requested simulated month identifier. Or -1 on both fields for error.
		 */
		public function GetActualDateForSimulatedMonth(int $simulated_month)
		{
			$result = array("year" => -1, "month_of_year" => -1);
			$simulatedMonth = intval($simulated_month);
			$startYear = Database::GetInstance()->query("SELECT game_start FROM game LIMIT 0,1");
				
			if (count($startYear) == 1)
			{
				$result["year"] = floor($simulatedMonth / 12) + $startYear[0]["game_start"];
				$result["month_of_year"] = ($simulatedMonth % 12) + 1;
			}
			return $result;
		}

		

		public function GetGameDetails()
		{
			$databaseState = Database::GetInstance()->query("SELECT g.game_start, g.game_eratime, g.game_currentmonth, g.game_state, g.game_planning_realtime, COUNT(u.user_id) total,
													sum(case when u.user_lastupdate > 3600 then 1 else 0 end) active_last_hour,
													sum(case when u.user_lastupdate > 60 then 1 else 0 end) active_last_minute FROM game g, user u;");
			$result = array();
			if (count($databaseState) > 0)
			{
				$state = $databaseState[0];
				$result = ["game_start_year" => (int) $state["game_start"], 
					"game_end_month" => $state["game_eratime"] * 4,
					"game_current_month" => (int) $state["game_currentmonth"],
					"game_state" => $state["game_state"],
					"users_active_last_hour" => (int) $state["active_last_hour"],
					"users_active_last_minute" => (int) $state["active_last_minute"],
					"game_planning_realtime" => $state["game_planning_realtime"] * 4
				];
			}
			return $result;//json_encode($result);
		}
	}
?>
