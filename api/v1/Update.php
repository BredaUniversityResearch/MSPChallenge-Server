<?php

namespace App\Domain\API\v1;

use App\Domain\API\APIHelper;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class Update extends Base
{
    private const ALLOWED = array(
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
        "ManualExportDatabase",
        ["From40beta7To40beta8", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["From40beta7To40beta9", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["From40beta7To40beta10", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["From40beta8To40beta10", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["From40beta9To40beta10", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER]
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportLayerMeta(
        string $configFilename,
        string $geoserver_url,
        string $geoserver_username,
        string $geoserver_password
    ): void {
        Log::LogInfo("ImportLayerMeta -> Starting import meta for all layers...");
        $layer = new Layer("");
        $layer->ImportMeta($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
        Log::LogInfo("ImportLayerMeta -> Done.");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetupSimulations(string $configFileName): void
    {
        Log::LogInfo("SetupSimulations -> Starting Setup Simulations ...");

        $game = new Game();
        $data = $game->GetGameConfigValues($configFileName);

        $game->SetupGameTime($data);
        $sims = new Simulations();
        $configuredSimulations = $sims->GetConfiguredSimulationTypes();
        if (array_key_exists("MEL", $configuredSimulations)) {
            Log::LogInfo("SetupSimulations -> Setting up MEL tables...");
            $mel = new MEL();
            $mel->OnReimport($data['MEL']);
            Log::LogInfo("SetupSimulations -> Done setting up MEL...");
        }

        if (array_key_exists("SEL", $configuredSimulations)) {
            Log::LogInfo("SetupSimulations -> Setting up SEL tables...");
            $sel = new SEL();
            $sel->ReimportShippingLayers();
        }
        
        if (array_key_exists("REL", $configuredSimulations)) {
            Log::LogInfo("SetupSimulations -> Setting up REL tables...");
            $rel = new REL();
            $rel->OnReimport();
        }
        Log::LogInfo(
            "SetupSimulations -> Simulation(s) ".implode(" ", array_keys($configuredSimulations)).
            " & test data is set up"
        );
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ReloadAdvanced($new_config_file_name, $dbase_file_path, $raster_files_path): bool
    {
        set_time_limit(Config::GetInstance()->GetLongRequestTimeout());

        return $this->Reload($new_config_file_name, $dbase_file_path, $raster_files_path);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Reload(string $new_config_file_name, string $dbase_file_path, string $raster_files_path): bool
    {
        Log::SetupFileLogger(Log::getRecreateLogPath());
        Log::LogInfo("Reload -> Starting game session reload process...");
        try {
            $this->EmptyDatabase();
            $this->RebuildDatabaseByDumpImport($dbase_file_path, $new_config_file_name);
            $this->ClearRasterStorage();
            $this->ExtractRasterFiles($raster_files_path);

            Log::LogInfo("Reload -> Save reloaded.");
            return true;
        } catch (Throwable $e) {
            Log::LogError("Reload -> Something went wrong.");
            Log::LogError($e->getMessage()." on line ".$e->getLine()." of file ".$e->getFile());

            $phpOutput = ob_get_flush();
            if (!empty($phpOutput)) {
                Log::LogInfo("Additionally the page generated the following output: ".$phpOutput);
            }
            Log::ClearFileLogger();

            return false;
        }
    }

    /**
     * @apiGroup Update
     * @throws Exception
     * @throws Throwable
     * @api {POST} /update/Reimport Reimport
     * @apiDescription Performs a full reimport of the database with the set filename in $configFilename.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Reimport(
        string $configFilename,
        string $geoserver_url = '',
        string $geoserver_username = '',
        string $geoserver_password = ''
    ): void {
        Log::SetupFileLogger(Log::getRecreateLogPath());
        Log::LogInfo("Reimport -> Starting game session creation process...");
        try {
            $this->EmptyDatabase();
            $this->ClearRasterStorage();
            $this->RebuildDatabase($configFilename);

            // this needs to happen as early as possible to be able to return a failed state to the ServerManager in
            //   case something goes wrong
            $this->SetupSecurityTokens();
            $this->ImportLayerGeometry($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
            $this->ClearEnergy();
            $this->ImportLayerMeta($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
            $this->ImportRestrictions();
            $this->SetupSimulations($configFilename);
            $this->ImportScenario();

            Log::LogInfo("Reimport -> Created session.");
        } catch (Throwable $e) {
            Log::LogError("Reimport -> Something went wrong.");
            Log::LogError($e->getMessage()." on line ".$e->getLine()." of file ".$e->getFile());

            $phpOutput = ob_get_flush();
            if (!empty($phpOutput)) {
                Log::LogInfo("Additionally the page generated the following output: ".$phpOutput);
            }
            Log::ClearFileLogger();

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ReimportAdvanced(
        string $configFilename,
        string $geoserver_url,
        string $geoserver_username,
        string $geoserver_password
    ): void {
        set_time_limit(Config::GetInstance()->GetLongRequestTimeout());

        $this->Reimport($configFilename, $geoserver_url, $geoserver_username, $geoserver_password);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportLayerGeometry(
        string $configFilename,
        string $geoserver_url,
        string $geoserver_username,
        string $geoserver_password
    ): void {
        Log::LogInfo("ImportLayerGeometry -> Starting Import Layer Meta...");

        $store = new Store();
        $store->geoserver->baseurl = $geoserver_url;
        $store->geoserver->username = $geoserver_username;
        $store->geoserver->password = $geoserver_password;
        $game = new Game();

        $config = $game->GetGameConfigValues($configFilename);
        $game->SetupCountries($config);

        foreach ($config['meta'] as $layerMeta) {
            Log::LogDebug("Starting import for layer ".$layerMeta["layer_name"]."...");
            $startTime = microtime(true);
            $store->CreateLayer($layerMeta, $config['region']);
            Log::LogDebug(
                "Imported layer geometry for ".$layerMeta["layer_name"]." in ".(microtime(true) - $startTime).
                " seconds"
            );
        }

        Log::LogInfo("ImportLayerGeometry -> Imported geometry.");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ClearEnergy(): void
    {
        Log::LogInfo("ClearEnergy -> Starting Clear Energy ...");

        $energy = new Energy("");
        $energy->Clear();

        Log::LogInfo("ClearEnergy -> Energy data cleared.");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportRestrictions(): void
    {
        Log::LogInfo("ImportRestrictions -> Starting Import Restrictions ...");

        $plan = new Plan("");

        $plan->ImportRestrictions();

        Log::LogInfo("ImportRestrictions -> Restrictions imported.");
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Clear(): void
    {
        $this->EmptyDatabase();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function EmptyDatabase(): void
    {
        Log::LogInfo("EmptyDatabase -> Starting empty database...");
        Database::GetInstance()->DropSessionDatabase(Database::GetInstance()->GetDatabaseName());
            
        Log::LogInfo("EmptyDatabase -> Deleted database.");
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ClearRasterStorage(): void
    {
        Log::LogInfo("ClearRasterStorage -> Starting clear raster storage...");

        Store::ClearRasterStoreFolder($this->getGameSessionId());

        Log::LogInfo("ClearRasterStorage -> Cleared raster storage.");
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ExtractRasterFiles(string $raster_zip): void
    {
        Log::LogInfo("ExtractRasterFiles -> Starting reload of raster files...");

        Store::ExtractRasterFilesFromZIP($raster_zip, $this->getGameSessionId());

        Log::LogInfo("ExtractRasterFiles -> Raster files reloaded.");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function RebuildDatabaseByDumpImport(string $dbase_file_path, string $new_config_file_name): void
    {
        Log::LogInfo("RebuildDatabaseByDumpImport -> Recreating database from save's dump file ...");
        $outputDirectory = SymfonyToLegacyHelper::getInstance()->getProjectDir() . '/export/DatabaseDumps/';
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory);
        }
        $templocation = $outputDirectory."temp".rand(1, 100).".sql";
        if (copy($dbase_file_path, $templocation)) {
            $db = Database::GetInstance();
            $db->CreateDatabaseAndSelect();
            $db->ImportMspDatabaseDump($templocation, true);
            unlink($templocation);

            $game = new Game();
            $game->Setupfilename($new_config_file_name);
            $this->ApplyGameConfig();
        } else {
            Log::LogError("Could not extract database dump from the save ZIP file.");
        }
        Log::LogInfo("RebuildDatabaseByDumpImport -> Database recreated.");
    }

    /**
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function RebuildDatabase(string $filename, bool $silent = false): void
    {
        Log::LogInfo("RebuildDatabase -> Starting Rebuild Database ...");

        $defaultDatabaseName = "msp";

        //Ensure database exists
        $db = Database::GetInstance();
        $db->CreateDatabaseAndSelect();
        //import the db structure
        $query = file_get_contents(APIHelper::getInstance()->GetCurrentSessionServerApiFolder()."mysql_structure.sql");
        //Replace the default database name with the desired one required for the session.
        $query = str_replace("`".$defaultDatabaseName."`", "`".$db->GetDatabaseName()."`", $query);
        $db->query($query);

        //run the inserts
        $query = file_get_contents(APIHelper::getInstance()->GetCurrentSessionServerApiFolder()."mysql_inserts.sql");
        $query = str_replace("`".$defaultDatabaseName."`", "`".$db->GetDatabaseName()."`", $query);
        $db->query($query);
        $db->query(
            "INSERT INTO game_session_api_version (game_session_api_version_server) VALUES (?)",
            array(APIHelper::getInstance()->GetCurrentSessionServerApiVersion())
        );

        $game = new Game();
        $game->Setupfilename($filename);
        $this->ApplyGameConfig();

        Log::LogInfo("RebuildDatabase -> Database rebuilt.");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function ApplyGameConfig(): void
    {
        /** @noinspection SqlWithoutWhere */
        Database::GetInstance()->query(
            "UPDATE game SET game_autosave_month_interval = ?",
            array(Config::GetInstance()->GetGameAutosaveInterval())
        );
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ClearPlans(): void
    {
        Log::LogInfo("ClearPlans -> Cleaning plans ...");

        Database::GetInstance()->query("SET FOREIGN_KEY_CHECKS=0");
        $todelete = Database::GetInstance()->query("SELECT geometry_id FROM plan_layer
				LEFT JOIN geometry ON geometry.geometry_layer_id=plan_layer.plan_layer_layer_id");

        foreach ($todelete as $del) {
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

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportScenario(): void
    {
        Log::LogInfo("ImportScenario -> Starting Import Scenario ...");
        $plan = new Plan();
        $plan->Import();

        $objective = new Objective();
        $objective->Import();

        Log::LogInfo("ImportScenario -> Imported Scenario.");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetupSecurityTokens(): void
    {
        Log::LogInfo("SetupSecurityToken -> Generating new access tokens");
        $security = new Security();
        $security->generateToken(Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN, Security::TOKEN_LIFETIME_INFINITE);
        $security->generateToken(Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER, Security::TOKEN_LIFETIME_INFINITE);
        Log::LogInfo("SetupSecurityToken -> Done");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function From40beta7To40beta8(): void
    {
        Database::GetInstance()->query("ALTER TABLE `user`
				ADD `user_name` VARCHAR(45) NULL AFTER `user_id`,
  				ADD `user_loggedoff` TINYINT NOT NULL DEFAULT 0 AFTER `user_country_id`;");

        Database::GetInstance()->query("ALTER TABLE `game_session` 
				CHANGE `game_session_password_admin` `game_session_password_admin` TEXT NOT NULL,
				CHANGE `game_session_password_player` `game_session_password_player` TEXT NOT NULL;");

        Database::GetInstance()->query("ALTER TABLE `country` 
				ADD `country_name` VARCHAR(45) NULL AFTER `country_id`;");

        $configData = (new Game)->GetGameConfigValues();
        foreach ($configData['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $configData['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    Database::GetInstance()->query(
                        "UPDATE country SET country_name = ? WHERE country_id = ?",
                        array($country['displayName'], $country['value'])
                    );
                }
            }
        }
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function From40beta7To40beta9(): void
    {
        //there was no difference in db structure between beta8 and beta9
        $this->From40beta7To40beta8();
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function From40beta7To40beta10(): void
    {
        try {
            $this->From40beta7To40beta9();
        } catch (Exception $e) {
            $original = $e;
            while (null !== $e->getPrevious()) {
                $e = $e->getPrevious();
            }
            switch ($e->getCode()) {
                case '42S21': // SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name
                    // meaning this database is probably already a beta9 database. So just continue on...
                    break;
                default:
                    throw $original; // re-throw error, to fail this migration.
            }
        }

        $this->From40beta9To40beta10();
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function From40beta8To40beta10(): void
    {
        $this->From40beta9To40beta10();
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function From40beta9To40beta10(): string
    {
        // Run doctrine migrations.
        $application = new Application(SymfonyToLegacyHelper::getInstance()->getKernel());
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $application->run(
            new StringInput('doctrine:migrations:migrate -vvv -n --em=' . Database::GetInstance()->GetDatabaseName()),
            $output
        );
        return $output->fetch();
    }
}
