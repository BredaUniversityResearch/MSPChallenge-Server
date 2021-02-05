<?php
	class Update extends Base {

		protected $allowed = array(
			"Latest",
			"Meta",
			"Reimport",
			"ImportMeta",
			"Newfiles",
			"Clear",
			"ImportRestrictions",
			"EmptyDatabase",
			"ImportLayerMeta",
			"RebuildDatabase",
			"ClearRasterStorage",
			"ClearEnergy",
			"ClearPlans",
			"SetupSimulations",
			"ImportScenario",
			"ManualExportDatabase"
		);
		public const LOG_ERROR = (1 << 0);
		public const LOG_WARNING = (1 << 1);
		public const LOG_INFO = (1 << 2);
		public const LOG_DEBUG = (1 << 3);

		private static $logFilter = ~0;

		public function __construct($str="")
		{
			parent::__construct($str);
		}

		public function ImportLayerMeta(string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			Update::LogInfo("ImportLayerMeta -> Starting import meta for all layers...");
			$layer = new Layer("");
			$layer->ImportMeta($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
			Update::LogInfo("ImportLayerMeta -> Done.");
		}

		public function SetupSimulations(string $configFileName)
		{
			Update::LogInfo("SetupSimulations -> Starting Setup Simulations ...");

			$update = new Update();
			$game = new Game();
			$data = $game->GetGameConfigValues($configFileName);

			$game->SetupGametime($data);
			$sims = new Simulations();
			$configuredSimulations = $sims->GetConfiguredSimulationTypes();
			if(array_key_exists("MEL", $configuredSimulations)){
				Update::LogInfo("SetupSimulations -> Setting up MEL tables...");
				$mel = new MEL();
				$mel->OnReimport($data['MEL']);
				Update::LogInfo("SetupSimulations -> Done setting up MEL...");
			}

			if(array_key_exists("CEL", $configuredSimulations)){
			}

			if(array_key_exists("SEL", $configuredSimulations)){
				Update::LogInfo("SetupSimulations -> Setting up SEL tables...");
				$sel = new SEL();
				$sel->ReimportShippingLayers();
			}
		
			if(array_key_exists("REL", $configuredSimulations)){
				Update::LogInfo("SetupSimulations -> Setting up REL tables...");
				$rel = new REL();
				$rel->OnReimport();
			}
			Update::LogInfo("SetupSimulations -> Simulation(s) ".implode(" ", array_keys($configuredSimulations))." & test data is set up");
		}

		/**
		 * @apiGroup Update
		 * @api {GET} /update/Reimport Reimport
		 * @apiDescription Performs a full reimport of the database with the set filename in $configFilename.
		 */
		public function Reimport(string $configFilename, string $geoserver_url="", string $geoserver_username="", string $geoserver_password)
		{
			Update::flushLog();
			
			ob_start("Update::RecreateLoggingHandler", 16);
			ob_implicit_flush(1);
			Update::LogInfo("Reimport -> Starting game session creation process...");

			try {
				$this->EmptyDatabase();
				$this->ClearRasterStorage();
				$this->RebuildDatabase($configFilename);
				$this->ImportLayerGeometry($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
				$this->ClearEnergy();
				$this->ImportLayerMeta($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
				$this->ImportRestrictions();
				$this->SetupSimulations($configFilename);	
				$this->ImportScenario();
				$this->SetupSecurityTokens();

				Update::LogInfo("Reimport -> Created session.");
			} catch (Throwable $e) {
				Update::LogError("Reimport -> Something went wrong.");
				Update::LogError($e->getMessage()." on line ".$e->getLine()." of file ".$e->getFile());
				throw $e;
			}
			finally
			{
				$phpOutput = ob_get_flush();
				if (!empty($phpOutput)) {
					Update::LogInfo("Additionally the page generated the following output: ".$phpOutput);
					return false;
				}
			}
			return true;
		}


		public function ReimportAdvanced(string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password) 
		{
			set_time_limit(Config::GetInstance()->GetLongRequestTimeout());

			$success =  $this->Reimport($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);

			return $success;
		}

		public function ImportLayerGeometry(string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			Update::LogInfo("ImportLayerGeometry -> Starting Import Layer Meta...");

			$store = new Store();
			$store->geoserver->baseurl = $geoserver_url;
			$store->geoserver->username = $geoserver_username;
			$store->geoserver->password = $geoserver_password;
			$game = new Game();

			$config = $game->GetGameConfigValues($configFilename);
			$game->SetupCountries($config);

			foreach($config['meta'] as $layerMeta)
			{
				$startTime = microtime(true);
				$store->CreateLayer($layerMeta, $config['region']);
				Update::LogDebug("Imported layer geometry for ".$layerMeta["layer_name"]." in ".(microtime(true) - $startTime)." seconds");
			}

			Update::LogInfo("ImportLayerGeometry -> Imported geometry.");
		}

		public function ClearEnergy()
		{
			Update::LogInfo("ClearEnergy -> Starting Clear Energy ...");

			$energy = new Energy("");
			$energy->Clear();

			Update::LogInfo("ClearEnergy -> Energy data cleared.");
		}

		public function ImportRestrictions() 
		{
			Update::LogInfo("ImportRestrictions -> Starting Import Restrictions ...");

			$plan = new Plan("");

			$plan->ImportRestrictions();

			Update::LogInfo("ImportRestrictions -> Restrictions imported.");
		}

		public function Clear()
		{
			$this->EmptyDatabase();
		}

		public function EmptyDatabase()
		{
			Update::LogInfo("EmptyDatabase -> Starting empty database...");
			Database::GetInstance()->DropSessionDatabase(Database::GetInstance()->GetDatabaseName());
			
			Update::LogInfo("EmptyDatabase -> Deleted database.");
		}

		public function ClearRasterStorage() 
		{
			Update::LogInfo("ClearRasterStorage -> Starting clear raster storage...");

			Store::ClearRasterStoreFolder();

			Update::LogInfo("ClearRasterStorage -> Cleared raster storage.");
		}

		public function RebuildDatabase(string $filename, bool $silent = false)
		{
			Update::LogInfo("RebuildDatabase -> Starting Rebuild Database ...");

			$defaultDatabaseName = "msp";

			//Ensure database exists
			$db = Database::GetInstance();
			$db->CreateDatabaseAndSelect();

			$query = file_get_contents(APIHelper::GetCurrentSessionServerApiFolder()."mysql_structure.sql");	//import the db structure
			$query = str_replace("`".$defaultDatabaseName."`", "`".$db->GetDatabaseName()."`", $query); //Replace the default database name with the desired one required for the session.
			$db->query($query);
			//file_put_contents(APIHelper::GetCurrentSessionServerApiFolder()."mysql_structure_edit.sql", $query);

			$query = file_get_contents(APIHelper::GetCurrentSessionServerApiFolder()."mysql_inserts.sql");	//run the inserts
			$query = str_replace("`".$defaultDatabaseName."`", "`".$db->GetDatabaseName()."`", $query);
			$db->query($query);
			$db->query("INSERT INTO game_session_api_version (game_session_api_version_server) VALUES (?)", array(APIHelper::GetCurrentSessionServerApiVersion()));

			$game = new Game();
			$game->SetupFilename($filename);
			$this->ApplyGameConfig();

			Update::LogInfo("RebuildDatabase -> Database rebuilt.");
		}

		private function ApplyGameConfig()
		{
			Database::GetInstance()->query("UPDATE game SET game_autosave_month_interval = ?", array(Config::GetInstance()->GetGameAutosaveInterval()));
		}

		public function ClearPlans()
		{
			Update::LogInfo("ClearPlans -> Cleaning plans ...");

			Database::GetInstance()->query("SET FOREIGN_KEY_CHECKS=0");
			$todelete = Database::GetInstance()->query("SELECT geometry_id FROM plan_layer
				LEFT JOIN geometry ON geometry.geometry_layer_id=plan_layer.plan_layer_layer_id");

			foreach($todelete as $del){
				Database::GetInstance()->query("DELETE FROM geometry WHERE geometry_id=?", array($del['geometry_id']));
			}

			Database::GetInstance()->query("TRUNCATE plan_delete");
			Database::GetInstance()->query("TRUNCATE plan_message");
			Database::GetInstance()->query("TRUNCATE plan_layer");
			Database::GetInstance()->query("DELETE FROM layer WHERE layer_original_id IS NOT NULL");
			Database::GetInstance()->query("TRUNCATE plan");

			Database::GetInstance()->query("SET FOREIGN_KEY_CHECKS=1");

			Update::LogInfo("ClearPlans -> All plans have been deleted.");
		}

		public function ImportScenario()
		{
			Update::LogInfo("ImportScenario -> Starting Import Scenario ...");
			$plan = new Plan();
			$plan->Import();

			$objective = new Objective();
			$objective->Import();

			Update::LogInfo("ImportScenario -> Imported Scenario.");
		}

		public function SetupSecurityTokens()
		{
			Update::LogInfo("SetupSecurityToken -> Generating new access tokens");
			$security = new Security();
			$security->GenerateToken(Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN, Security::TOKEN_LIFETIME_INFINITE);
			$security->GenerateToken(Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER, Security::TOKEN_LIFETIME_INFINITE);
			Update::LogInfo("SetupSecurityToken -> Done");
		}

		private static function getRecreateLogPath()
		{
			$rootPath = getcwd();
			$logPrefix = 'log_session_';
			$sessionId = $_REQUEST['session'];

			$log_dir = $rootPath."/ServerManager"."/log";
			if (!file_exists($log_dir)) {
				mkdir($log_dir, 0777, true);
			}

			$log_file_data = $log_dir.'/'. $logPrefix . $sessionId . '.log';
			return $log_file_data;
		}

		private static function flushLog() 
		{
			file_put_contents(Update::getRecreateLogPath(), "");
		}

		private static function LogError(string $message)
		{
			self::PrintToRecreateLog($message, self::LOG_ERROR);
		}
		
		private static function LogWarning(string $message)
		{
			self::PrintToRecreateLog($message, self::LOG_WARNING);
		}
		
		private static function LogInfo(string $message)
		{
			self::PrintToRecreateLog($message, self::LOG_INFO);
		}

		private static function LogDebug(string $message)
		{
			self::PrintToRecreateLog($message, self::LOG_DEBUG);
		}

		private static function PrintToRecreateLog(string $message, int $logLevel) 
		{
			if ((self::$logFilter & $logLevel) == 0)
			{
				return;
			}

			$dateNow = '[' . date("Y-m-d H:i:s") . ']';
			$logCategory = "";
			if (($logLevel & self::LOG_ERROR) == self::LOG_ERROR)
			{
				$logCategory = "ERROR";
			}
			else if (($logLevel & self::LOG_WARNING) == self::LOG_WARNING)
			{
				$logCategory = "WARN";
			}
			else if (($logLevel & self::LOG_INFO) == self::LOG_INFO)
			{
				$logCategory = "INFO";
			}
			else if (($logLevel & self::LOG_DEBUG) == self::LOG_DEBUG)
			{
				$logCategory = "DEBUG";
			}
			else 
			{
				$logCategory = "UNKNOWN";
			}

			print($dateNow . " [ ". $logCategory . ' ] - ' . $message);
		}

		private static function RecreateLoggingHandler(string $message, int $phase)
		{
			file_put_contents(Update::getRecreateLogPath(), $message . PHP_EOL, FILE_APPEND);
			return ""; //Swallow all logging after this has been written to the log file.
		}

	}


?>
