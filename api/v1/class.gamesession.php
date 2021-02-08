<?php

class GameSession extends Base
{
	protected $allowed = array(
		["CreateGameSession", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionAndSignal", Security::ACCESS_LEVEL_FLAG_NONE],
		["ArchiveGameSession", Security::ACCESS_LEVEL_FLAG_NONE],
		["ArchiveGameSessionInternal", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionZip", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionZipInternal", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionLayersZip", Security::ACCESS_LEVEL_FLAG_NONE],
		["CreateGameSessionLayersZipInternal", Security::ACCESS_LEVEL_FLAG_NONE],
		["ResetWatchdogAddress", Security::ACCESS_LEVEL_FLAG_NONE]
	);

	const INVALID_SESSION_ID = -1;
	const ARCHIVE_DIRECTORY = "session_archive/";
	const CONFIG_DIRECTORY = "running_session_config/";
	const SECONDS_PER_MINUTE = 60;
	const SECONDS_PER_HOUR = self::SECONDS_PER_MINUTE * 60;

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
	 * @api {GET} /GameSession/CreateGameSession Creates new game session
	 * @apiParam {int} game_id Session identifier for this game.
	 * @apiParam {string} config_file_content JSON Object of the config file.
	 * @apiParam {string} password_admin Plain-text admin password.
	 * @apiParam {string} password_player Plain-text player password.
	 * @apiParam {string} watchdog_address URL at which the watchdog resides for this session.
	 * @apiParam {string] response_address URL which we call when the setup is done.
	 * @apiParam {int} allow_recreate (0|1) Allow overwriting of an existing session?
	 * @ForceNoTransaction
	 */
	public function CreateGameSession(int $game_id, string $config_file_content, string $password_admin, string $password_player, string $watchdog_address, string $response_address, string $jwt, bool $allow_recreate = false)
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

		$geoserver_credentials = $this->GetGeoserverCredentials($config_file_content);

		$postValues = array(
			"config_file_path" => $configFilePath,
			"geoserver_url" => $geoserver_credentials["geoserver_url"],
			"geoserver_username" => $geoserver_credentials["geoserver_username"],
			"geoserver_password" => $geoserver_credentials["geoserver_password"],
			"password_admin" => $password_admin,
			"password_player" => $password_player,
			"watchdog_address" => $watchdog_address, 
			"response_address" => $response_address,
			"jwt" => $jwt
		);
		self::SetGameSessionVersionInfo(json_decode($config_file_content, true), $postValues);

		// don't wait or feed back the return of the following new request - if things went well so far, then we can just feed back success
		// this is because any failures of the following new request are stored in the session log
		$this->LocalApiRequest("api/GameSession/CreateGameSessionAndSignal", $game_id, $postValues, true);
	}

	private function GetGeoserverCredentials($config_json)
	{
		$return_array = array("geoserver_url" => "", "geoserver_username" => "", "geoserver_password" => "");

		$config_contents = json_decode($config_json, true);

		$_POST["jwt"] = $_POST["jwt"] ?? "";

		if (empty($config_contents["datamodel"]["geoserver_url"])) {
			// go get everything from the Authoriser
			$endpoint =  Config::GetInstance()->GetGeoserverCredentialsEndpoint();
			$parsedurl = parse_url($_POST['response_address']);
			$postvars = array("jwt" => $_POST["jwt"],
							  "audience" => $parsedurl["scheme"]."://".$parsedurl["host"]);
			$json_response = $this->CallBack($endpoint, $postvars, array(), false, true);
			$response = json_decode($json_response, true);
			//die(var_dump($response));
			if ($response["success"]) {
				$return_array["geoserver_url"] = $response["credentials"]["baseurl"];
				$return_array["geoserver_username"] = base64_decode($response["credentials"]["username"]);
				$return_array["geoserver_password"] = base64_decode($response["credentials"]["password"]);	
			}
		}
		else {
			$return_array["geoserver_url"] = $config_contents["datamodel"]["geoserver_url"];
			$return_array["geoserver_username"] = $config_contents["datamodel"]["geoserver_username"] ?? '';
			$return_array["geoserver_password"] = $config_contents["datamodel"]["geoserver_password"] ?? '';
		}
		return $return_array;
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
	 * @api {GET} /GameSession/CreateGameSession Creates new game session
	 * @apiParam {string} config_file_path Local path to the config file.
	 * @apiParam {string} password_admin Admin password for this session
	 * @apiParam {string} password_player Player password for this session
	 * @apiParam {string} watchdog_address API Address to direct all Watchdog calls to.
	 * @apiParam {string} response_address URL which we call when the setup is done.
	 * @ForceNoTransaction
	 */
	public function CreateGameSessionAndSignal(string $config_file_path, string $geoserver_url, string $geoserver_username, string $geoserver_password, string $password_admin, string $password_player, string $watchdog_address, string $response_address, string $jwt)
	{
		$update = new Update();
		$result = $update->ReimportAdvanced($config_file_path, $geoserver_url, $geoserver_username, $geoserver_password);
		if (!$result)
		{
			throw new Exception("Recreate failed");
		}

		Database::GetInstance()->query("INSERT INTO game_session (game_session_watchdog_address, game_session_watchdog_token, game_session_password_admin, game_session_password_player) VALUES (?, UUID_SHORT(), ?, ?)",
				array($watchdog_address, $password_admin, $password_player)); 

		//Notify the simulation that the game has been setup so we start the simulations. 
		//This is needed because MEL needs to be run before the game to setup the initial fishing values.
		$game = new Game();
		$watchdogSuccess = $game->ChangeWatchdogState("SETUP");

		if (!empty($response_address)) 
		{
			$security = new Security();

			$sessionId = self::GetGameSessionIdForCurrentRequest();
			$postValues = (new Game())->GetGameDetails(); 
			$postValues["session_id"] = $sessionId;
			$postValues["session_state"] = $watchdogSuccess == true ? "Healthy" : "Failed"; 
			$postValues["access_token"] = $security->GetServerManagerToken()["token"];
			$postValues["Token"] = $jwt;

			$result = $this->CallBack($response_address, $postValues);
			return $result;
		}
		return;
	}

	public function ResetWatchdogAddress(string $watchdog_address) 
	{
		if (!empty($watchdog_address)) {
			Database::GetInstance()->query("UPDATE game_session SET game_session_watchdog_address = ?, game_session_watchdog_token = UUID_SHORT() 
							WHERE game_session_watchdog_address = (SELECT game_session_watchdog_address FROM game_session);", array($_POST['watchdog_address']));
		}
		else throw new Exception("Empty watchdog address not allowed.");
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
	 * @apiDescription Internal method that actually archives a game session.
 	 * @api {POST} /GameSession/ArchiveGameSession Archives game session
	 * @ForceNoTransaction
	 */
	public function ArchiveGameSessionInternal(string $response_url)
	{
		$game = new Game();
		$game->ChangeWatchdogState('end');
		
		$createzipreturn = $this->CreateGameSessionZip($response_url);
		if (!empty($createzipreturn['zipname'])) {

			$zipname = $createzipreturn['zipname'];
			
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
				$postValues = array("session_id" => self::GetGameSessionIdForCurrentRequest(),
									"archive" => new CurlFile($zipname, 'application/zip'),
									"oldlocation" => $zipname);
				$this->CallBack($response_url, $postValues);
			}
		}
	}
	
	public function CreateGameSessionZip(string $response_url, bool $nooverwrite = false, string $preferredfolder = self::ARCHIVE_DIRECTORY, string $preferredname = "session_archive_") 
	{
		$sessionId = self::GetGameSessionIdForCurrentRequest();		
		$zipname = $preferredfolder.$preferredname.$sessionId.".zip";
		$zippath = Base::Dir()."/".$zipname;
		
		if ($nooverwrite) {
			if (file_exists($zippath)) {
				throw new Exception("File ".$zippath." already exists, so not continuing.");
			}
		}

		Store::EnsureFolderExists($preferredfolder);

		$this->LocalApiRequest("api/GameSession/CreateGameSessionZipInternal", $sessionId, array("response_url" => $response_url, "zippath" => $zippath, "zipname" => $zipname), true);		
	}

	public function CreateGameSessionZipInternal(string $response_url, string $zippath, string $zipname) 
	{
		$return_array = array();
		$sessionId = self::GetGameSessionIdForCurrentRequest();		
		
		$sqlDumpPath = Base::Dir()."/export/db_export_".$sessionId.".sql"; 
		Database::GetInstance()->CreateMspDatabaseDump($sqlDumpPath, true);
	
		$configFilePath = null;
		$gameData = Database::GetInstance()->query("SELECT game_configfile FROM game");
		if (count($gameData) > 0)
		{
			$configFilePath = self::CONFIG_DIRECTORY.$gameData[0]['game_configfile'];
		}

		$sessionFiles = array($sqlDumpPath, $configFilePath);
		$dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Store::GetRasterStoreFolder()));
		foreach($dirIterator as $file) {
			if ($file->getFilename() != "." && $file->getFilename() != "..") { 
				$sessionFiles[] = $file->getPathName();
			}
		}

		$zip = new ZipArchive();
		$result = $zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if ($result === true) {
			foreach($sessionFiles as $layerFile) {
				if (is_readable($layerFile)) {
					$zipFolder = "";
					$pathName = pathinfo($layerFile, PATHINFO_DIRNAME);
					if (stripos($pathName, "raster") !== false) {
						if (stripos($pathName, "archive") !== false) {
							$zipFolder = "raster/archive/";
						}
						else {
							$zipFolder = "raster/";
						}
					}
					$fileName = pathinfo($layerFile, PATHINFO_BASENAME);
					$zip->addFile($layerFile, $zipFolder.$fileName);
				}
				else {
					$return_array[] .= "Skipped file: ".$layerFile." can't be read. ";
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
		return $return_array;
	}

	public function CreateGameSessionLayersZip(string $response_url, bool $nooverwrite = false, string $preferredfolder = self::ARCHIVE_DIRECTORY, string $preferredname = "temp_layers_") 
	{
		$sessionId = self::GetGameSessionIdForCurrentRequest();		
		$zipname = $preferredfolder.$preferredname.$sessionId.".zip";
		$zippath = Base::Dir()."/".$zipname;
		
		if ($nooverwrite) {
			if (file_exists($zippath)) {
				throw new Exception("File ".$zippath." already exists, so not continuing.");
			}
		}

		$layer = new Layer();
		$alllayers = $layer->List();
		if (empty($alllayers)) {
			throw new Exception("No layers, so cannot continue.");
		}
		
		$this->LocalApiRequest("api/GameSession/CreateGameSessionLayersZipInternal", $sessionId, array("response_url" => $response_url, "zippath" => $zippath, "zipname" => $zipname), true);
	}

	public function CreateGameSessionLayersZipInternal(string $response_url, string $zippath, string $zipname) 
	{
		$sessionId = self::GetGameSessionIdForCurrentRequest();		
		
		$layer = new Layer();
		$alllayers = $layer->List();

		$zip = new ZipArchive();
		$result = $zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if ($result === true) {
			// for each layer in the session, get the .json file with all its currently active geometry
			// and store it in the appropriate place
			foreach ($alllayers as $thislayer) {
				if ($thislayer["layer_geotype"] != "raster") {
					$layer_json = Base::JSON($layer->Export($thislayer["layer_id"]));
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

	private static function RemoveDirectory($dir)
	{
		$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it,
             RecursiveIteratorIterator::CHILD_FIRST);
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
