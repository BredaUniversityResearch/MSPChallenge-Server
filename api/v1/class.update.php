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

		public function __construct($str="")
		{
			parent::__construct($str);
		}

		public function ImportLayerMeta(string $configFilename, string $geoserver_url, string $geoserver_username, string $geoserver_password)
		{
			Log::LogInfo("ImportLayerMeta -> Starting import meta for all layers...");
			$layer = new Layer("");
			$layer->ImportMeta($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
			Log::LogInfo("ImportLayerMeta -> Done.");
		}

		public function SetupSimulations(string $configFileName)
		{
			Log::LogInfo("SetupSimulations -> Starting Setup Simulations ...");

			$game = new Game();
			$data = $game->GetGameConfigValues($configFileName);

			$game->SetupGametime($data);
			$sims = new Simulations();
			$configuredSimulations = $sims->GetConfiguredSimulationTypes();
			if(array_key_exists("MEL", $configuredSimulations)){
				Log::LogInfo("SetupSimulations -> Setting up MEL tables...");
				$mel = new MEL();
				$mel->OnReimport($data['MEL']);
				Log::LogInfo("SetupSimulations -> Done setting up MEL...");
			}

			if(array_key_exists("CEL", $configuredSimulations)){
			}

			if(array_key_exists("SEL", $configuredSimulations)){
				Log::LogInfo("SetupSimulations -> Setting up SEL tables...");
				$sel = new SEL();
				$sel->ReimportShippingLayers();
			}
		
			if(array_key_exists("REL", $configuredSimulations)){
				Log::LogInfo("SetupSimulations -> Setting up REL tables...");
				$rel = new REL();
				$rel->OnReimport();
			}
			Log::LogInfo("SetupSimulations -> Simulation(s) ".implode(" ", array_keys($configuredSimulations))." & test data is set up");
		}

		/**
		 * @apiGroup Update
		 * @api {GET} /update/Reimport Reimport
		 * @apiDescription Performs a full reimport of the database with the set filename in $configFilename.
		 */
		public function Reimport(string $configFilename, string $geoserver_url="", string $geoserver_username="", string $geoserver_password)
		{
			Log::SetupFileLogger(Log::GetRecreateLogPath());
			Log::LogInfo("Reimport -> Starting game session creation process...");

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

				Log::LogInfo("Reimport -> Created session.");
			} catch (Throwable $e) {
				Log::LogError("Reimport -> Something went wrong.");
				Log::LogError($e->getMessage()." on line ".$e->getLine()." of file ".$e->getFile());
				throw $e;
			}
			finally
			{
				$phpOutput = ob_get_flush();
				if (!empty($phpOutput)) {
					Log::LogInfo("Additionally the page generated the following output: ".$phpOutput);
					return false;
				}
				Log::ClearFileLogger();
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
			Log::LogInfo("ImportLayerGeometry -> Starting Import Layer Meta...");

			$store = new Store();
			$store->geoserver->baseurl = $geoserver_url;
			$store->geoserver->username = $geoserver_username;
			$store->geoserver->password = $geoserver_password;
			$game = new Game();

			$config = $game->GetGameConfigValues($configFilename);
			$game->SetupCountries($config);

			foreach($config['meta'] as $layerMeta)
			{
				Log::LogDebug("Starting import for layer ".$layerMeta["layer_name"]."...");
				$startTime = microtime(true);
				$store->CreateLayer($layerMeta, $config['region']);
				Log::LogDebug("Imported layer geometry for ".$layerMeta["layer_name"]." in ".(microtime(true) - $startTime)." seconds");
			}

			Log::LogInfo("ImportLayerGeometry -> Imported geometry.");
		}

		public function ClearEnergy()
		{
			Log::LogInfo("ClearEnergy -> Starting Clear Energy ...");

			$energy = new Energy("");
			$energy->Clear();

			Log::LogInfo("ClearEnergy -> Energy data cleared.");
		}

		public function ImportRestrictions() 
		{
			Log::LogInfo("ImportRestrictions -> Starting Import Restrictions ...");

			$plan = new Plan("");

			$plan->ImportRestrictions();

			Log::LogInfo("ImportRestrictions -> Restrictions imported.");
		}

		public function Clear()
		{
			$this->EmptyDatabase();
		}

		public function EmptyDatabase()
		{
			Log::LogInfo("EmptyDatabase -> Starting empty database...");
			Database::GetInstance()->DropSessionDatabase(Database::GetInstance()->GetDatabaseName());
			
			Log::LogInfo("EmptyDatabase -> Deleted database.");
		}

		public function ClearRasterStorage() 
		{
			Log::LogInfo("ClearRasterStorage -> Starting clear raster storage...");

			Store::ClearRasterStoreFolder();

			Log::LogInfo("ClearRasterStorage -> Cleared raster storage.");
		}

		public function RebuildDatabase(string $filename, bool $silent = false)
		{
			Log::LogInfo("RebuildDatabase -> Starting Rebuild Database ...");

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

			Log::LogInfo("RebuildDatabase -> Database rebuilt.");
		}

		private function ApplyGameConfig()
		{
			Database::GetInstance()->query("UPDATE game SET game_autosave_month_interval = ?", array(Config::GetInstance()->GetGameAutosaveInterval()));
		}

		public function ClearPlans()
		{
			Log::LogInfo("ClearPlans -> Cleaning plans ...");

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

			Log::LogInfo("ClearPlans -> All plans have been deleted.");
		}

		public function ImportScenario()
		{
			Log::LogInfo("ImportScenario -> Starting Import Scenario ...");
			$plan = new Plan();
			$plan->Import();

			$objective = new Objective();
			$objective->Import();

			Log::LogInfo("ImportScenario -> Imported Scenario.");
		}

		public function SetupSecurityTokens()
		{
			Log::LogInfo("SetupSecurityToken -> Generating new access tokens");
			$security = new Security();
			$security->GenerateToken(Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN, Security::TOKEN_LIFETIME_INFINITE);
			$security->GenerateToken(Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER, Security::TOKEN_LIFETIME_INFINITE);
			Log::LogInfo("SetupSecurityToken -> Done");
		}
	}

?>
