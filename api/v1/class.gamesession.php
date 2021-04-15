<?php

class GameSession extends Base
{
	protected $allowed = array(
		["CreateGameSession", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionAndSignal", Security::ACCESS_LEVEL_FLAG_NONE],
		["ArchiveGameSession", Security::ACCESS_LEVEL_FLAG_NONE],
		["ArchiveGameSessionInternal", Security::ACCESS_LEVEL_FLAG_NONE],
		["SaveSession", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionZip", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionLayersZip", Security::ACCESS_LEVEL_FLAG_NONE],
		["ResetWatchdogAddress", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
		["SetUserAccess", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER]
	);

	const INVALID_SESSION_ID = -1;
	const ARCHIVE_DIRECTORY = "session_archive/";
	const CONFIG_DIRECTORY = "running_session_config/";
	const SECONDS_PER_MINUTE = 60;
	const SECONDS_PER_HOUR = self::SECONDS_PER_MINUTE * 60;
	
	public function __construct($str=""){
		parent::__construct($str);
	}

	public static function GetGameSessionIdForCurrentRequest()
	{
		$sessionId = self::INVALID_SESSION_ID;
		if (isset($_GET['session'])) 
		{
			$sessionId = intval($_GET['session']);
			if ($sessionId <= 0)
			{
				$sessionId = self::INVALID_SESSION_ID;
			}
		}
		return $sessionId;
	}

	//returns the base API endpoint. e.g. http://localhost/dev/
	public static function GetRequestApiRoot()
	{
		if (isset($GLOBALS['RequestApiRoot'])) return $GLOBALS['RequestApiRoot'];
		
		$server_name = $_SERVER["SERVER_NAME"];
		$apiRoot = preg_replace('/(.*)\/api\/(.*)/', '$1/', $_SERVER["REQUEST_URI"]);
		$apiRoot = str_replace("//", "/", $apiRoot);
		$protocol = isset($_SERVER['HTTPS'])? "https://" : "http://";
	
		$dbConfig = Config::GetInstance()->DatabaseConfig();
		$temporaryConnection = Database::CreateTemporaryDBConnection($dbConfig["host"], $dbConfig["user"], $dbConfig["password"], $dbConfig["database"]);
		foreach ($temporaryConnection->query("SELECT address FROM game_servers LIMIT 1;") as $row) {
			$server_name = $row["address"];
			//if ($server_name == "localhost") $server_name = getHostByName(getHostName());
			$GLOBALS['RequestApiRoot'] = $protocol.$server_name.$apiRoot;
		}
		
		return $protocol.$server_name.$apiRoot;
	}

	private static function GetConfigFilePathForSession($sessionId)
	{
		$configFilePath = "session_config_".$sessionId.".json";
		return $configFilePath;
	}

	private function GetHostedSessionIds()
	{
		$dbConfig = Config::GetInstance()->DatabaseConfig();
		$escapedPrefix = str_replace("_", "\_", $dbConfig["multisession_database_prefix"]); //Escape so we don't match random characters but just the _
		$sessionDatabasePattern = $escapedPrefix."%";

		$result = [];

		$databaseList = Database::GetInstance()->query("SHOW DATABASES LIKE '".$sessionDatabasePattern."'");
		foreach($databaseList as $r)
		{
			$databaseName = reset($r); //Get the first entry from the array.
			$result[] = intval(substr($databaseName, strlen($dbConfig["multisession_database_prefix"])));
		}
		return $result;
	}

	/**
	 * @apiGroup GameSession
	 * @apiDescription Sets up a new game session with the supplied information.
	 * @api {POST} /GameSession/CreateGameSession Creates new game session
	 * @apiParam {int} game_id Session identifier for this game.
	 * @apiParam {string} config_file_content JSON Object of the config file.
	 * @apiParam {string} password_admin Plain-text admin password.
	 * @apiParam {string} password_player Plain-text player password.
	 * @apiParam {string} watchdog_address URL at which the watchdog resides for this session.
	 * @apiParam {string} response_address URL which we call when the setup is done.
	 * @apiParam {int} allow_recreate (0|1) Allow overwriting of an existing session?
	 * @ForceNoTransaction
	 */
	public function CreateGameSession(int $game_id, string $config_file_content, string $geoserver_url, string $geoserver_username, string $geoserver_password, string $password_admin, string $password_player, string $watchdog_address, string $response_address, bool $allow_recreate = false)
	{
		$sessionId = intval($game_id);
		
		if ($this->DoesSessionExist($sessionId)) 
		{
			if (empty($allow_recreate) || $allow_recreate == false)
			{
				throw new Exception("Session already exists.");
			}
			else 
			{
				$security = new Security();
				Database::GetInstance()->SwitchToSessionDatabase($sessionId);
				if (!$security->CheckAccess(Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER))
				{
					throw new Exception("Access denied for provided token");
				}
				else 
				{
					Database::GetInstance()->DropSessionDatabase(Database::GetInstance()->GetDatabaseName());
				}
			}
		}

		$configFilePath = self::GetConfigFilePathForSession($game_id);

		if (!is_dir(self::CONFIG_DIRECTORY))
		{
			mkdir(self::CONFIG_DIRECTORY);
		}
		file_put_contents(self::CONFIG_DIRECTORY.$configFilePath, $config_file_content);

		$postValues = array(
			"config_file_path" => $configFilePath,
			"geoserver_url" => $geoserver_url, 
			"geoserver_username" => base64_decode($geoserver_username), 
			"geoserver_password" => base64_decode($geoserver_password), 
			"password_admin" => $password_admin,
			"password_player" => $password_player,
			"watchdog_address" => $watchdog_address, 
			"response_address" => $response_address
		);
		self::SetGameSessionVersionInfo(json_decode($config_file_content, true), $postValues);

		// don't wait or feed back the return of the following new request - if things went well so far, then we can just feed back success
		// this is because any failures of the following new request are stored in the session log
		$this->LocalApiRequest("api/GameSession/CreateGameSessionAndSignal", $game_id, $postValues, true);
	}

	private static function SetGameSessionVersionInfo($decodedJsonConfig, &$targetRequestValues)
	{
		if (isset($decodedJsonConfig["metadata"]))
		{
			$metaData = $decodedJsonConfig["metadata"];
			if (!empty($metaData["use_server_api_version"]))
			{
				$targetRequestValues["use_server_api_version"] = $metaData["use_server_api_version"];
			}
		}
	}

	private function DoesSessionExist($gameSessionId)
	{
		$hostedIds = $this->GetHostedSessionIds();
		return in_array($gameSessionId, $hostedIds);
	}

	/**
	 * @apiGroup GameSession
	 * @apiDescription For internal use: creates a new game session with the given config file path.
	 * @api {POST} /GameSession/CreateGameSession Creates new game session
	 * @apiParam {string} config_file_path Local path to the config file.
	 * @apiParam {string} password_admin Admin password for this session
	 * @apiParam {string} password_player Player password for this session
	 * @apiParam {string} watchdog_address API Address to direct all Watchdog calls to.
	 * @apiParam {string} response_address URL which we call when the setup is done.
	 * @ForceNoTransaction
	 */
	public function CreateGameSessionAndSignal(string $config_file_path, string $geoserver_url, string $geoserver_username, string $geoserver_password, string $password_admin, string $password_player, string $watchdog_address, string $response_address)
	{
		// get the entire session database in order - bare minimum the database is created and config file is put on its designated spot
		$update = new Update();
		$result = $update->ReimportAdvanced($config_file_path, $geoserver_url, $geoserver_username, $geoserver_password);

		// get ready for an optional callback
		$postValues = (new Game())->GetGameDetails(); 
		$postValues["session_id"] = self::GetGameSessionIdForCurrentRequest();
		$postValues["access_token"] = (new Security())->GetServerManagerToken()["token"];

		if ($result !== true)
		{
			if (!empty($response_address)) 
			{
				$postValues["session_state"] = "Failed"; 
				$this->CallBack($response_address, $postValues);
			}
			throw new Exception("Recreate failed");
		}

		// get the watchdog and end-user log-on in order
		Database::GetInstance()->query("INSERT INTO game_session (game_session_watchdog_address, game_session_watchdog_token, game_session_password_admin, game_session_password_player) VALUES (?, UUID_SHORT(), ?, ?)",
				array($watchdog_address, $password_admin, $password_player)); 
				

		//Notify the simulation that the game has been setup so we start the simulations. 
		//This is needed because MEL needs to be run before the game to setup the initial fishing values.
		$game = new Game();
		$watchdogSuccess = $game->ChangeWatchdogState("SETUP");

		if (!empty($response_address)) 
		{
			$postValues["session_state"] = $watchdogSuccess == true ? "Healthy" : "Failed"; 
			$this->CallBack($response_address, $postValues);
		}
		return;
	}

	public function ResetWatchdogAddress(string $watchdog_address) 
	{
		Database::GetInstance()->query("UPDATE game_session SET game_session_watchdog_address = ?, game_session_watchdog_token = UUID_SHORT() 
							WHERE game_session_watchdog_address = (SELECT game_session_watchdog_address FROM game_session);", array($watchdog_address));
	}

	/**
	 * @apiGroup GameSession
	 * @apiDescription Archives a game session with a specified ID.
	 * @api {POST} /GameSession/ArchiveGameSession Archives game session
	 * @apiParam {string} response_url API call that we make with the zip encoded in the body upon completion.
	 * @ForceNoTransaction
	 */
	public function ArchiveGameSession(string $response_url)
	{
		$sessionId = self::GetGameSessionIdForCurrentRequest();

		if (!$this->DoesSessionExist($sessionId) || $sessionId == self::INVALID_SESSION_ID)
		{
			throw new Exception("Session ".$sessionId." does not exist.");
		}
	
		$this->LocalApiRequest("api/GameSession/ArchiveGameSessionInternal", $sessionId, array("response_url" => $response_url), true);
	}

	/**
	 * @apiGroup GameSession
	 * @apiDescription Archives a game session with a specified ID.
	 * @api {POST} /GameSession/ArchiveGameSessionInternal Archives game session, internal method
	 * @apiParam {string} response_url API call that we make with the zip path upon completion.
	 * @ForceNoTransaction
	 */
	public function ArchiveGameSessionInternal(string $response_url)
	{
		$game = new Game();
		$game->ChangeWatchdogState('end');
		
		$zippath = $this->CreateGameSessionZip();
		
		if (!empty($zippath)) {

			$configFilePath = null;
			$gameData = Database::GetInstance()->query("SELECT game_configfile FROM game");
			if (count($gameData) > 0)
			{
				$configFilePath = self::CONFIG_DIRECTORY.$gameData[0]['game_configfile'];
				unlink($configFilePath);
			}

			Database::GetInstance()->DropSessionDatabase(Database::GetInstance()->GetDatabaseName());
			
			self::RemoveDirectory(Store::GetRasterStoreFolder());

			if (!empty($response_url))
			{
				$postValues = array("zippath" => $zippath);
				$this->CallBack($response_url, $postValues);
			}
		}
	}

	public function SaveSession(string $type = "full", string $response_url, bool $nooverwrite = false, string $preferredfolder = self::ARCHIVE_DIRECTORY, string $preferredname = "session_archive_")
	{
		$sessionId = self::GetGameSessionIdForCurrentRequest();		
		$zipname = $preferredfolder.$preferredname.$sessionId.".zip";
		$zippath = Base::Dir().$zipname;
		
		if ($nooverwrite) {
			if (file_exists($zippath)) {
				throw new Exception("File ".$zippath." already exists, so not continuing.");
			}
		}

		if ($type == "full") {
			$this->LocalApiRequest("api/GameSession/CreateGameSessionZip", $sessionId, array("response_url" => $response_url, "preferredfolder" => $preferredfolder, "preferredname" => $preferredname), true);
		}
		elseif ($type == "layers") {
			$this->LocalApiRequest("api/GameSession/CreateGameSessionLayersZip", $sessionId, array("response_url" => $response_url, "preferredfolder" => $preferredfolder, "preferredname" => $preferredname), true);
		}
		else throw new Exception("Type ".$type." is not recognised.");
	}

	public function CreateGameSessionZip(string $response_url = "", string $preferredfolder = self::ARCHIVE_DIRECTORY, string $preferredname = "session_archive_") 
	{		
		$sessionId = self::GetGameSessionIdForCurrentRequest();		
		$zipname = $preferredfolder.$preferredname.$sessionId.".zip";
		$sqlDumpPath = Base::Dir()."/export/db_export_".$sessionId.".sql"; 
		$zippath = Base::Dir()."/".$zipname;

		Store::EnsureFolderExists($preferredfolder);
		
		Database::GetInstance()->CreateMspDatabaseDump($sqlDumpPath, true);
	
		$configFilePath = null;
		$gameData = Database::GetInstance()->query("SELECT game_configfile FROM game");
		if (count($gameData) > 0)
		{
			$configFilePath = self::CONFIG_DIRECTORY.$gameData[0]['game_configfile'];
		}

		$sessionFiles = array($sqlDumpPath, $configFilePath);

		$zip = new ZipArchive();
		$result = $zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if ($result === true) {
			foreach($sessionFiles as $layerFile) {
				if (is_readable($layerFile)) {
					$zip->addFile($layerFile, pathinfo($layerFile, PATHINFO_BASENAME));
				}
			}
			foreach (Store::GetRasterStoreFolderContents() as $rasterfile) {
				if (is_readable($rasterfile)) {
					$pathName = pathinfo($rasterfile, PATHINFO_DIRNAME);
					if (stripos($pathName, "archive") !== false) $zipFolder = "raster/archive/";
					else $zipFolder = "raster/";
					$zip->addFile($rasterfile, $zipFolder.pathinfo($rasterfile, PATHINFO_BASENAME));
				}
			}
			$zip->close();
			unlink(realpath($sqlDumpPath));
			// callback if requested
			if (!empty($response_url))
			{
				$postValues = array("session_id" => $sessionId, "zipname" => $zipname, "type" => "full");
				$this->CallBack($response_url, $postValues);
			}
		}
		return $zippath;
	}

	public function CreateGameSessionLayersZip(string $response_url, string $preferredfolder = self::ARCHIVE_DIRECTORY, string $preferredname = "temp_layers_") 
	{
		$sessionId = self::GetGameSessionIdForCurrentRequest();		
		$zipname = $preferredfolder.$preferredname.$sessionId.".zip";
		$zippath = Base::Dir()."/".$zipname;

		$layer = new Layer();
		$alllayers = $layer->List();
		if (empty($alllayers)) {
			throw new Exception("No layers, so cannot continue.");
		}
		else {
			$zip = new ZipArchive();
			$result = $zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
			if ($result === true) {
				// for each layer in the session, get the .json file with all its currently active geometry
				// and store it in the appropriate place
				foreach ($alllayers as $thislayer) {
					if ($thislayer["layer_geotype"] != "raster") {
						$layer_json = json_encode($layer->Export($thislayer["layer_id"]));
						$layer_filename = $thislayer["layer_name"].'.json';
						$zip->addFromString($layer_filename, $layer_json);
					}
					else {
						$layer_binary = $layer->ReturnRasterById($thislayer["layer_id"]);
						$layer_filename = $thislayer["layer_name"].'.tiff';
						$zip->addFromString($layer_filename, $layer_binary); // addFromString is binary-safe
					}
				}
				$zip->close();

				// callback if requested
				if (!empty($response_url))
				{
					$postValues = array("session_id" => $sessionId, "zipname" => $zipname, "type" => "layers");
					$this->CallBack($response_url, $postValues);
				}
			}
		}
		return $zipname;
	}

	public function SetUserAccess(string $password_admin, string $password_player)
	{
		Database::GetInstance()->query("UPDATE game_session SET game_session_password_admin = ?, game_session_password_player = ? 
							WHERE game_session_watchdog_address = (SELECT game_session_watchdog_address FROM game_session);", array($password_admin, $password_player));
	}

	public function CheckGameSessionPasswords()
	{
		$adminhaspassword = true;
		$playerhaspassword = true;
		$passwordData = Database::GetInstance()->query("SELECT game_session_password_admin, game_session_password_player FROM game_session");
		if (count($passwordData) > 0)
		{
			if (!parent::isNewPasswordFormat($passwordData[0]["game_session_password_admin"]) || !parent::isNewPasswordFormat($passwordData[0]["game_session_password_player"])) {
				$adminhaspassword = !empty($passwordData[0]["game_session_password_admin"]);
				$playerhaspassword = !empty($passwordData[0]["game_session_password_player"]);
			}
			else {
				$password_admin = json_decode(base64_decode($passwordData[0]["game_session_password_admin"]), true);
				$password_player = json_decode(base64_decode($passwordData[0]["game_session_password_player"]), true);
				if ($password_admin["admin"]["provider"] == "local") {
					$adminhaspassword = !empty($password_admin["admin"]["password"]);
				}
				if ($password_player["provider"] == "local") {
					foreach ($password_player as $team => $password) {
						if (!empty($password)) {
							$playerhaspassword = true;
							break;
						}
						else $playerhaspassword = false;
					}
				}
			}
		}
		return array("adminhaspassword" => $adminhaspassword, "playerhaspassword" => $playerhaspassword);
	}

	private static function RemoveDirectory($dir)
	{
		try {
			$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
			$files = new RecursiveIteratorIterator($it,
            	 RecursiveIteratorIterator::CHILD_FIRST);
		} catch (Exception $e) {
			$files = array();
		}
		foreach($files as $file) {
    		if ($file->isDir()){
        		rmdir($file->getRealPath());
    		} else {
        		unlink($file->getRealPath());
  			}
		}
		rmdir($dir);
	}

	private function LocalApiRequest($apiUrl, $sessionId, $postValues, $async = false)
	{
		if (self::GetGameSessionIdForCurrentRequest() != self::INVALID_SESSION_ID) 
		{
			$baseUrl = str_replace(self::GetGameSessionIdForCurrentRequest(), $sessionId, self::GetRequestApiRoot());
		}
		else 
		{
			$baseUrl = self::GetRequestApiRoot().$sessionId."/";
		}
		
		$requestHeader = apache_request_headers();
		$headers = array();
		if (isset($requestHeader["Authorization"])) {
			$headers[] = "Authorization: ".$requestHeader["Authorization"];
		}
		
		$result = $this->CallBack($baseUrl.$apiUrl, $postValues, $headers, $async);
		
		return $result;
	}
};
