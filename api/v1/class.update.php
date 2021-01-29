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
			"ImportGeometry",
			"RebuildDatabase",
			"ClearRasterStorage",
			"ClearEnergy",
			"ClearPlans",
			"SetupSimulations",
			"ImportScenario",
			"ManualExportDatabase"
		);

		public function __construct($str="")
		{
			parent::__construct($str);
		}

		public function ImportGeometry(bool $full=false, string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			//imports export/meta_export.csv and overrides the current 'layer' table with its contents
			Update::printLog("ImportGeometry -> Starting import Geometry ...");
			$layer = new Layer("");
			$layer->ImportMeta($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
			Update::printLog("ImportGeometry -> Imported Geometry.");
		}

		public function SetupSimulations(string $configFileName)
		{
			Update::printLog("SetupSimulations -> Starting Setup Simulations ...");

			$update = new Update();
			$game = new Game();
			$data = $game->GetGameConfigValues($configFileName);

			$game->SetupGametime($data);
			$sims = new Simulations();
			$configuredSimulations = $sims->GetConfiguredSimulationTypes();
			if(array_key_exists("MEL", $configuredSimulations)){
				Update::printLog("SetupSimulations -> Setting up MEL tables...");
				$mel = new MEL();
				$mel->OnReimport($data['MEL']);
				Update::printLog("SetupSimulations -> Done setting up MEL...");
			}

			if(array_key_exists("CEL", $configuredSimulations)){
			}

			if(array_key_exists("SEL", $configuredSimulations)){
				Update::printLog("SetupSimulations -> Setting up SEL tables...");
				$sel = new SEL();
				$sel->ReimportShippingLayers();
			}
		
			if(array_key_exists("REL", $configuredSimulations)){
				Update::printLog("SetupSimulations -> Setting up REL tables...");
				$rel = new REL();
				$rel->OnReimport();
			}
			Update::printLog("SetupSimulations -> Simulation(s) ".implode(" ", array_keys($configuredSimulations))." & test data is set up");
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
			Update::printLog("Reimport -> Starting game session creation process...");

			try {
				$this->EmptyDatabase();
				$this->ClearRasterStorage();
				$this->RebuildDatabase($configFilename);
				$this->ImportLayerMeta($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
				$this->ClearEnergy();
				$this->ImportGeometry(true, $configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
				$this->ImportRestrictions();
				$this->SetupSimulations($configFilename);
				$this->ImportScenario();
				$this->SetupSecurityTokens();

				Update::printLog("Reimport -> Created session.");
			} catch (Throwable $e) {
				Update::printLog("Reimport -> Something went wrong.", true);
				Update::printLog($e->getMessage()." on line ".$e->getLine()." of file ".$e->getFile(), true);
				throw $e;
			}
			finally
			{
				$phpOutput = ob_get_flush();
				if (!empty($phpOutput)) {
					Update::printLog("Additionally the page generated the following output: ".$phpOutput);
					return false;
				}
			}
			return true;
		}


		public function ReimportAdvanced(string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password) 
		{
			set_time_limit(300); //5 minute time-out for rebuilding.

			$success =  true; //$this->Reimport($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);

			return $success;
		}

		public function ImportLayerMeta(string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			Update::printLog("ImportLayerMeta -> Starting Import Layer Meta...");

			$store = new Store();
			$store->geoserver->baseurl = $geoserver_url;
			$store->geoserver->username = $geoserver_username;
			$store->geoserver->password = $geoserver_password;
			$game = new Game();

			$config = $game->GetGameConfigValues($configFilename);
			$game->SetupCountries($config);

			foreach($config['meta'] as $layerMeta)
			{
				$store->CreateLayer($layerMeta, $config['region']);
			}

			Update::printLog("ImportGeometry -> Imported geometry.");
		}

		public function ClearEnergy()
		{
			Update::printLog("ClearEnergy -> Starting Clear Energy ...");

			$energy = new Energy("");
			$energy->Clear();

			Update::printLog("ClearEnergy -> Energy data cleared.");
		}

		public function ImportRestrictions() 
		{
			Update::printLog("ImportRestrictions -> Starting Import Restrictions ...");

			$plan = new Plan("");

			$plan->ImportRestrictions();

			Update::printLog("ImportRestrictions -> Restrictions imported.");
		}

		public function Clear()
		{
			$this->EmptyDatabase();
		}

		public function EmptyDatabase()
		{
			Update::printLog("EmptyDatabase -> Starting empty database...");
			$this->DropSessionDatabase($this->GetDatabaseName());
			
			Update::printLog("EmptyDatabase -> Deleted database.");
		}

		public function ClearRasterStorage() 
		{
			Update::printLog("ClearRasterStorage -> Starting clear raster storage...");

			Store::ClearRasterStoreFolder();

			Update::printLog("ClearRasterStorage -> Cleared raster storage.");
		}

		public function RebuildDatabase(string $filename, bool $silent = false)
		{
			Update::printLog("RebuildDatabase -> Starting Rebuild Database ...");

			$defaultDatabaseName = "msp";

			//Ensure database exists
			$this->CreateDatabaseAndSelect();

			$query = file_get_contents(APIHelper::GetCurrentSessionServerApiFolder()."mysql_structure.sql");	//import the db structure
			$query = str_replace("`".$defaultDatabaseName."`", "`".$this->GetDatabaseName()."`", $query); //Replace the default database name with the desired one required for the session.
			$this->query($query);
			//file_put_contents(APIHelper::GetCurrentSessionServerApiFolder()."mysql_structure_edit.sql", $query);

			$query = file_get_contents(APIHelper::GetCurrentSessionServerApiFolder()."mysql_inserts.sql");	//run the inserts
			$query = str_replace("`".$defaultDatabaseName."`", "`".$this->GetDatabaseName()."`", $query);
			$this->query($query);
			$this->query("INSERT INTO game_session_api_version (game_session_api_version_server) VALUES (?)", array(APIHelper::GetCurrentSessionServerApiVersion()));

			$game = new Game();
			$game->SetupFilename($filename);
			$this->ApplyGameConfig();

			Update::printLog("RebuildDatabase -> Database rebuilt.");
		}

		private function ApplyGameConfig()
		{
			$this->query("UPDATE game SET game_autosave_month_interval = ?", array(Config::GetInstance()->GetGameAutosaveInterval()));
		}

		public function ClearPlans()
		{
			Update::printLog("ClearPlans -> Cleaning plans ...");

			$this->query("SET FOREIGN_KEY_CHECKS=0");
			$todelete = $this->query("SELECT geometry_id FROM plan_layer
				LEFT JOIN geometry ON geometry.geometry_layer_id=plan_layer.plan_layer_layer_id");

			foreach($todelete as $del){
				$this->query("DELETE FROM geometry WHERE geometry_id=?", array($del['geometry_id']));
			}

			$this->query("TRUNCATE plan_delete");
			$this->query("TRUNCATE plan_message");
			$this->query("TRUNCATE plan_layer");
			$this->query("DELETE FROM layer WHERE layer_original_id IS NOT NULL");
			$this->query("TRUNCATE plan");

			$this->query("SET FOREIGN_KEY_CHECKS=1");

			Update::printLog("ClearPlans -> All plans have been deleted.");
		}

		public function ImportScenario()
		{
			Update::printLog("ImportScenario -> Starting Import Scenario ...");
			$plan = new Plan();
			$plan->Import();

			$objective = new Objective();
			$objective->Import();

			Update::printLog("ImportScenario -> Imported Scenario.");
		}

		public function SetupSecurityTokens()
		{
			Update::printLog("SetupSecurityToken -> Generating new access tokens");
			$security = new Security();
			$security->GenerateToken(Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN, Security::TOKEN_LIFETIME_INFINITE);
			$security->GenerateToken(Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER, Security::TOKEN_LIFETIME_INFINITE);
			Update::printLog("SetupSecurityToken -> Done");
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

		private static function printLog(string $message, bool $isError = false) 
		{
			$dateNow = '[' . date("Y-m-d H:i:s") . ']';
			$logLevel = $isError ? ' [ERROR] ' : ' [INFO]' ;

			print($dateNow . $logLevel . ' - ' . $message);
		}

		private static function RecreateLoggingHandler(string $message, int $phase)
		{
			file_put_contents(Update::getRecreateLogPath(), $message . PHP_EOL, FILE_APPEND);
			return ""; //Swallow all logging after this has been written to the log file.
		}

	}


?>
