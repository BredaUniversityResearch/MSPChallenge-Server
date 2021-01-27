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

		private static $silentReimportMessages = false;

		public function __construct($str="")
		{
			parent::__construct($str);
		}

		public function ImportMeta(bool $full=false, string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			//imports export/meta_export.csv and overrides the current 'layer' table with its contents
			Update::printLog("ImportMeta -> Starting import Meta ...");
			$layer = new Layer("");
			$layer->ImportMeta($geoserver_url, $geoserver_username, $geoserver_password);
			Update::printLog("ImportMeta -> Imported Meta.");

			if($full)
				Update::PrintReimportMessage("<br/>Imported meta<br/><div class='line'></div><br/>Starting Import Restrictions ...");
			else
				Update::PrintReimportMessage("<br/>Imported meta");
		}

		public function SetupSimulations()
		{
			Update::printLog("SetupSimulations -> Starting Setup Simulations ...");

			$update = new Update();
			$game = new Game();
			$data = $game->GetGameConfigValues(self::$configFilename);

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
			Update::PrintReimportMessage("<br/>Done setting up ".implode(" ", array_keys($configuredSimulations))." simulation(s) & test data is set up<br/><div class='line'></div><br/>Starting Import Scenario ...");
			Update::printLog("SetupSimulations -> Simulation(s) ".implode(" ", array_keys($configuredSimulations))." & test data is set up");
		}

		public function Meta(string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			//shorthand to make sure ImportMeta doesn't stop working
			$this->ImportMeta(true, $geoserver_url, $geoserver_username, $geoserver_password);
		}

		/**
		 * @apiGroup Update
		 * @api {GET} /update/Reimport Reimport
		 * @apiDescription Performs a full reimport of the database with the set filename in $_COOKIE['filename'].
		 */
		public function Reimport(string $filename = null, string $geoserver_url="", string $geoserver_username="", string$geoserver_password)
		{
			/*if ($filename == null) {
				$filename = $_COOKIE['filename'];
			}*/ // remnants from api/visual/start?

			Update::flushLog();
			
			ob_start("Update::RecreateLoggingHandler", 16);
			ob_implicit_flush(1);
			Update::printLog("Reimport -> Starting game session creation process...");

			try {
				$this->EmptyDatabase();
				$this->ClearRasterStorage();
				$this->RebuildDatabase($filename);
				$this->ImportGeometry($geoserver_url, $geoserver_username, $geoserver_password);
				$this->ClearEnergy();
				$this->Meta($geoserver_url, $geoserver_username, $geoserver_password);
				$this->ImportRestrictions();
				$this->SetupSimulations();
				$this->ImportScenario();
				$this->SetupSecurityTokens();

				Update::PrintReimportMessage("REIMPORT DONE");

				Update::printLog("Reimport -> Created session.");
			} catch (Exception $e) {
				Update::printLog("Reimport -> Something went wrong.", true);
				Update::printLog($e->getMessage()." on line ".$e->getLine()." of file ".$e->getFile(), true);
			}
			finally
			{
				$phpOutput = ob_get_flush();
				if (!empty($phpOutput)) {
					Update::printLog("Additionally the page generated the following output: ".$phpOutput);
				}
			}
		}


		public function ReimportAdvanced(string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password) 
		{
			set_time_limit(300); //5 minute time-out for rebuilding.

			Update::$silentReimportMessages = true;
			ob_flush(); //Clear the output buffers.
			$this->Reimport($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
			$errorMessages = ob_get_contents();
			Update::$silentReimportMessages = false;
			ob_end_clean();

			return $errorMessages;
		}

		public function ImportGeometry(string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			Update::printLog("ImportGeometry -> Starting Import Geometry (this will take a few minutes) ...");

			$store = new Store();
			$store->geoserver->baseurl = $geoserver_url;
			$store->geoserver->username = $geoserver_username;
			$store->geoserver->password = $geoserver_password;
			$game = new Game();

			$config = $game->GetGameConfigValues(self::$configFilename);
			$game->SetupCountries($config);

			foreach($config['meta'] as $layerMeta)
			{
				$store->CreateLayer($layerMeta, $config['region']);
			}

			Update::PrintReimportMessage("<br/>Imported geometry<br/><div class='line'></div><br/>Starting Clear Energy ...");
			Update::printLog("ImportGeometry -> Imported geometry.");
		}

		public function ClearEnergy()
		{
			Update::printLog("ClearEnergy -> Starting Clear Energy ...");

			$energy = new Energy("");
			$energy->Clear();

			Update::PrintReimportMessage("<br/>Energy data cleared<br/><div class='line'></div><br/>Starting Import Meta ...");

			Update::printLog("ClearEnergy -> Energy data cleared.");
		}

		public function ImportRestrictions() 
		{
			Update::printLog("ImportRestrictions -> Starting Import Restrictions ...");

			$plan = new Plan("");

			$plan->ImportRestrictions();

			Update::PrintReimportMessage("<br/>Restrictions imported<br/><div class='line'></div><br/>Starting Setup Simulations ...");
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
			
			Update::PrintReimportMessage("<br/>Deleted database<br/><div class='line'></div><br/>Clearing raster storage...");
			Update::printLog("EmptyDatabase -> Deleted database.");
		}

		public function ClearRasterStorage() 
		{
			Update::printLog("ClearRasterStorage -> Starting clear raster storage...");

			Store::ClearRasterStoreFolder();
			Update::PrintReimportMessage("[EmptyDatabase]Cleared raster storage<br/><div class='line'></div><br/>Starting Rebuild Database ...");

			Update::printLog("ClearRasterStorage -> Cleared raster storage.");
		}

		public function RebuildDatabase(string $filename, bool $silent = false)
		{
			Update::printLog("RebuildDatabase -> Starting Rebuild Database ...");

			if ($filename == null) {
				$filename = Base::$configFilename;
			}
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

			Update::PrintReimportMessage("<br/>Database rebuilt<br/><div class='line'></div><br/>Starting Import Geometry (this will take a few minutes) ...");

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

			Update::PrintReimportMessage("All plans have been deleted.");

			Update::printLog("ClearPlans -> All plans have been deleted.");
		}

		public function ImportScenario()
		{
			Update::printLog("ImportScenario -> Starting Import Scenario ...");
			$plan = new Plan();
			$plan->Import();

			$objective = new Objective();
			$objective->Import();

			Update::PrintReimportMessage("<br/>RECREATE DONE!");
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

		public static function PrintReimportMessage(string $message) 
		{
			if (!Update::$silentReimportMessages) {
				print($message);
			}
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
			return $message;
		}

	}


?>
