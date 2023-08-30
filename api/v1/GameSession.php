<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Domain\WsServer\ClientHeaderKeys;
use App\Entity\ServerManager\GameList;
use Drift\DBAL\Result;
use Exception;
use FilesystemIterator;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;
use ZipArchive;
use function App\resolveOnFutureTick;
use function App\await;

class GameSession extends Base
{
    private const ALLOWED = array(
        ["CreateGameSession", Security::ACCESS_LEVEL_FLAG_NONE],
        ["CreateGameSessionAndSignal", Security::ACCESS_LEVEL_FLAG_NONE],
        ["ArchiveGameSession", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["ArchiveGameSessionInternal", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["SaveSession", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["LoadGameSave", Security::ACCESS_LEVEL_FLAG_NONE],
        ["LoadGameSaveAndSignal", Security::ACCESS_LEVEL_FLAG_NONE],
        ["CreateGameSessionZip", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["CreateGameSessionLayersZip", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["ResetWatchdogAddress", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        ["SetUserAccess", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER]
    );

    const INVALID_SESSION_ID = -1;
    const ARCHIVE_DIRECTORY = "session_archive/";
    private const CONFIG_DIRECTORY = "running_session_config/";
    const EXPORT_DIRECTORY = "export/";
    const SECONDS_PER_MINUTE = 60;
    const SECONDS_PER_HOUR = self::SECONDS_PER_MINUTE * 60;
    
    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    public static function getConfigDirectory(): string
    {
        return SymfonyToLegacyHelper::getInstance()->getProjectDir() . '/' . self::CONFIG_DIRECTORY;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetGameSessionIdForCurrentRequest(): int
    {
        $sessionId = $_POST['game_id'] ?? $_GET['session'] ?? self::INVALID_SESSION_ID;
        return intval($sessionId) < 1 ? self::INVALID_SESSION_ID : (int)$sessionId;
    }

    /**
     * used to communicate "game_session_api" URL to the watchdog
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public static function getRequestApiRootAsync(bool $forDocker = false): PromiseInterface
    {
        if (isset($GLOBALS['RequestApiRoot'][$forDocker ? 1 : 0])) {
            $deferred = new Deferred();
            return resolveOnFutureTick($deferred, $GLOBALS['RequestApiRoot'][$forDocker ? 1 : 0])->promise();
        }
        $apiRoot = preg_replace('/(.*)\/(api|_profiler)\/(.*)/', '$1/', $_SERVER["REQUEST_URI"]);
        $apiRoot = str_replace("//", "/", $apiRoot);

        // this is always called from inside the docker environment,so just use http://caddy:80/...
        if ($forDocker) {
            $deferred = new Deferred();
            $GLOBALS['RequestApiRoot'][1] = 'http://caddy:80'.$apiRoot;
            return resolveOnFutureTick($deferred, $GLOBALS['RequestApiRoot'][1])->promise();
        }

        $_SERVER['HTTPS'] ??= 'off';
        /** @noinspection HttpUrlsUsage */
        $protocol = ($_SERVER['HTTPS'] == 'on') ? "https://" : ($_ENV['URL_WEB_SERVER_SCHEME'] ?? "http://");

        $connection = ConnectionManager::getInstance()->getCachedAsyncServerManagerDbConnection(Loop::get());
        return $connection->query(
            $connection->createQueryBuilder()
                ->select('address')
                ->from('game_servers')
                ->setMaxResults(1)
        )
        ->then(
            function (Result $result) use ($protocol, $apiRoot) {
                $row = $result->fetchFirstRow() ?? [];
                $serverName = $_ENV['URL_WEB_SERVER_HOST'] ?? $row['address'] ?? $_SERVER["SERVER_NAME"] ??
                    gethostname();
                $port = ':' . ($_ENV['URL_WEB_SERVER_PORT'] ?? 80);
                $apiRoot = $protocol.$serverName.$port.$apiRoot;
                $GLOBALS['RequestApiRoot'][0] = $apiRoot;
                return $apiRoot;
            }
        );
    }

    /**
     * returns the base API endpoint. e.g. http://localhost/1/
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRequestApiRoot(): string
    {
        return await(self::getRequestApiRootAsync());
    }

    /**
     * Used by GameTick to generate a server manager URL towards editGameSession.php
     *
     * @return string
     */
    public static function getServerManagerApiRoot(bool $forDocker = false): string
    {
        if (isset($GLOBALS['ServerManagerApiRoot'][$forDocker ? 1 : 0])) {
            return $GLOBALS['ServerManagerApiRoot'][$forDocker ? 1 : 0];
        }

        $serverName = $_SERVER["SERVER_NAME"] ?? gethostname();

        /** @noinspection HttpUrlsUsage */
        $protocol = isset($_SERVER['HTTPS'])? "https://" : ($_ENV['URL_WEB_SERVER_SCHEME'] ?? "http://");
        $apiFolder = "/ServerManager/api/";

        // this is always called from inside the docker environment,so just use http://caddy:80/...
        if ($forDocker) {
            $GLOBALS['ServerManagerApiRoot'][1] = 'http://caddy:80'.$apiFolder;
            return $GLOBALS['ServerManagerApiRoot'][1];
        }

        $dbConfig = Config::GetInstance()->DatabaseConfig();
        $temporaryConnection = Database::CreateTemporaryDBConnection(
            $dbConfig["host"],
            $dbConfig["user"],
            $dbConfig["password"],
            $dbConfig["database"]
        );
        $port = ':' . ($_ENV['URL_WEB_SERVER_PORT'] ?? 80);
        $apiRoot = $protocol.$serverName.$port.$apiFolder;
        foreach ($temporaryConnection->query("SELECT address FROM game_servers LIMIT 1") as $row) {
            $serverName = $_ENV['URL_WEB_SERVER_HOST'] ?? $row["address"];
            $apiRoot = $protocol.$serverName.$port.$apiFolder;
        }
        $GLOBALS['ServerManagerApiRoot'][0] = $apiRoot;
        return $apiRoot;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function GetConfigFilePathForSession(int $sessionId): string
    {
        return 'session_config_'.$sessionId.'.json';
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetHostedSessionIds(): array
    {
        $dbConfig = Config::GetInstance()->DatabaseConfig();
        //Escape so we don't match random characters but just the _
        $escapedPrefix = str_replace("_", "\_", $dbConfig["multisession_database_prefix"]);
        $sessionDatabasePattern = $escapedPrefix."%";

        $result = [];

        $databaseList = $this->getDatabase()->query("SHOW DATABASES LIKE '".$sessionDatabasePattern."'");
        foreach ($databaseList as $r) {
            $databaseName = reset($r); //Get the first entry from the array.
            $result[] = intval(substr($databaseName, strlen($dbConfig["multisession_database_prefix"])));
        }
        return $result;
    }

    /**
     * @apiGroup           GameSession
     * @apiDescription     Sets up a new game session with the supplied information.
     * @throws             Exception
     * @api                {POST} /GameSession/CreateGameSession Creates new game session
     * @apiParam           {int} game_id Session identifier for this game.
     * @apiParam           {string} config_file_content JSON Object of the config file.
     * @apiParam           {string} password_admin Plain-text admin password.
     * @apiParam           {string} password_player Plain-text player password.
     * @apiParam           {string} watchdog_address URL at which the watchdog resides for this session.
     * @apiParam           {string} response_address URL which we call when the setup is done.
     * @apiParam           {int} allow_recreate (0|1) Allow overwriting of an existing session?
     * @ForceNoTransaction
     * @noinspection       PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateGameSession(
        int $game_id,
        string $config_file,
        string $geoserver_url,
        string $geoserver_username,
        string $geoserver_password,
        string $password_admin,
        string $password_player,
        string $watchdog_address,
        string $response_address,
        bool $allow_recreate = false
    ): void {
        $sessionId = $game_id;
        
        if ($this->DoesSessionExist($sessionId)) {
            if (empty($allow_recreate)) {
                throw new Exception("Session already exists.");
            } else {
                $this->getDatabase()->SwitchToSessionDatabase($sessionId);
                $this->getDatabase()->DropSessionDatabase($this->getDatabase()->GetDatabaseName());
            }
        }

        $configFilePath = self::GetConfigFilePathForSession($game_id);

        $configDir = self::getConfigDirectory();
        if (!is_dir($configDir)) {
            mkdir($configDir);
        }
        if (!file_exists($config_file)) {
            throw new Exception("Could not read config file");
        }
        $config_file_content = file_get_contents($config_file);
        file_put_contents($configDir.$configFilePath, $config_file_content);

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

        // don't wait or feed back the return of the following new request - if things went well so far, then we can
        //   just feed back success
        // this is because any failures of the following new request are stored in the session log
        $this->LocalApiRequest("GameSession/CreateGameSessionAndSignal", $game_id, $postValues, true);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function SetGameSessionVersionInfo(array $decodedJsonConfig, array &$targetRequestValues): void
    {
        if (empty($decodedJsonConfig["metadata"])) {
            return;
        }
        $metaData = $decodedJsonConfig["metadata"];
        if (!empty($metaData["use_server_api_version"])) {
            $targetRequestValues["use_server_api_version"] = $metaData["use_server_api_version"];
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function DoesSessionExist(int $gameSessionId): bool
    {
        $hostedIds = $this->GetHostedSessionIds();
        return in_array($gameSessionId, $hostedIds);
    }

    /**
     * @apiGroup           GameSession
     * @apiDescription     For internal use: creates a new game session with the given config file path.
     * @throws             Exception
     * @api                {POST} /GameSession/CreateGameSession Creates new game session
     * @apiParam           {string} config_file_path Local path to the config file.
     * @apiParam           {string} password_admin Admin password for this session
     * @apiParam           {string} password_player Player password for this session
     * @apiParam           {string} watchdog_address API Address to direct all Watchdog calls to.
     * @apiParam           {string} response_address URL which we call when the setup is done.
     * @ForceNoTransaction
     * @noinspection       PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateGameSessionAndSignal(
        string $config_file_path,
        string $geoserver_url,
        string $geoserver_username,
        string $geoserver_password,
        string $password_admin,
        string $password_player,
        string $watchdog_address,
        string $response_address
    ): void {
        // get the entire session database in order - bare minimum the database is created and config file is put on
        //  its designated spot
        $update = new Update();
        $e = null;
        try {
            $update->ReimportAdvanced(
                $config_file_path,
                $geoserver_url,
                $geoserver_username,
                $geoserver_password
            );
        } catch (Throwable $e) {
            $e = new Exception('Recreate failed', 0, $e);
        }

        // get ready for an optional callback
        $postValues = (new Game())->GetGameDetails();
        $postValues["session_id"] = self::GetGameSessionIdForCurrentRequest();
        $token = (new Security())->getServerManagerToken();
        $postValues["token"] = $token; // to pass ServerManager security
        $postValues["api_access_token"] = $token; // to store in ServerManager

        if (null !== $e) {
            if (!empty($response_address)) {
                $postValues["session_state"] = new GameSessionStateValue('failed');
                $this->updateServerManagerGameList($postValues);
            }
            throw $e;
        }

        // get the watchdog and end-user log-on in order
        $this->getDatabase()->query(
            "
            INSERT INTO game_session (
                game_session_watchdog_address, game_session_watchdog_token, game_session_password_admin,
                game_session_password_player
            ) VALUES (?, UUID_SHORT(), ?, ?)
            ",
            array($watchdog_address, $password_admin, $password_player)
        );

        //Notify the simulation that the game has been setup so we start the simulations.
        //This is needed because MEL needs to be run before the game to setup the initial fishing values.
        $game = new Game();
        $this->asyncDataTransferTo($game);
        $watchdogSuccess = false;
        if (null !== $promise = $game->changeWatchdogState("SETUP")) {
            await($promise);
            $watchdogSuccess = true;
        };

        if (!empty($response_address)) {
            $postValues["session_state"] = $watchdogSuccess ?
                new GameSessionStateValue('healthy') : new GameSessionStateValue('failed');
            $this->updateServerManagerGameList($postValues);
        }
    }

    private function updateServerManagerGameList($postValues): void
    {
        $manager = SymfonyToLegacyHelper::getInstance()->getEntityManager();
        $gameSession = $manager->getRepository(GameList::class)->findOneBy(['id' => $postValues['session_id']]);
        if (isset($postValues['game_start_year'])) {
            $gameSession->setGameStartYear((int) $postValues['game_start_year']);
        }
        if (isset($postValues['game_end_month'])) {
            $gameSession->setGameEndMonth((int) $postValues['game_end_month']);
        }
        if (isset($postValues['game_current_month'])) {
            $gameSession->setGameCurrentMonth((int) $postValues['game_current_month']);
        }
        if (isset($postValues['game_state'])) {
            if (!$postValues['game_state'] instanceof GameStateValue) {
                $postValues['game_state'] = new GameStateValue($postValues['game_state']);
            }
            $gameSession->setGameState($postValues['game_state']);
        }
        if (isset($postValues['players_past_hour'])) {
            $gameSession->setPlayersPastHour((int) $postValues['players_past_hour']);
        }
        if (isset($postValues['players_active'])) {
            $gameSession->setPlayersActive((int) $postValues['players_active']);
        }
        if (isset($postValues['game_running_til_time'])) {
            $gameSession->setGameRunningTilTime((string) $postValues['game_running_til_time']);
        }
        if (isset($postValues['session_state'])) {
            if (!$postValues['session_state'] instanceof GameSessionStateValue) {
                $postValues['session_state'] = new GameSessionStateValue($postValues['session_state']);
            }
            $gameSession->setSessionState($postValues['session_state']);
        }
        if (isset($postValues['token'])) {
            $gameSession->setApiAccessToken((string) $postValues['token']);
        }
        $manager->persist($gameSession);
        $manager->flush();
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ResetWatchdogAddress(string $watchdog_address): void
    {
        if (empty($watchdog_address)) {
            throw new Exception("Watchdog address cannot be empty.");
        }
        /** @noinspection SqlWithoutWhere */
        $this->getDatabase()->query(
            "UPDATE game_session SET game_session_watchdog_address = ?, game_session_watchdog_token = UUID_SHORT();",
            array($watchdog_address)
        );
    }

    /**
     * @apiGroup           GameSession
     * @apiDescription     Archives a game session with a specified ID.
     * @throws             Exception
     * @api                {POST} /GameSession/ArchiveGameSession Archives game session
     * @apiParam           {string} response_url API call that we make with the zip encoded in the body upon completion.
     * @ForceNoTransaction
     * @noinspection       PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ArchiveGameSession(string $response_url): void
    {
        $sessionId = self::GetGameSessionIdForCurrentRequest();

        if (!$this->DoesSessionExist($sessionId) || $sessionId == self::INVALID_SESSION_ID) {
            throw new Exception("Session ".$sessionId." does not exist.");
        }
    
        $this->LocalApiRequest(
            "GameSession/ArchiveGameSessionInternal",
            $sessionId,
            array("response_url" => $response_url),
            true
        );
    }

    /**
     * @apiGroup           GameSession
     * @apiDescription     Archives a game session with a specified ID.
     * @throws             Exception
     * @api                {POST} /GameSession/ArchiveGameSessionInternal Archives game session, internal method
     * @apiParam           {string} response_url API call that we make with the zip path upon completion.
     * @ForceNoTransaction
     * @noinspection       PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ArchiveGameSessionInternal(string $response_url): void
    {
        $game = new Game();
        $this->asyncDataTransferTo($game);
        await($game->changeWatchdogState('end'));
        
        $zippath = $this->CreateGameSessionZip($response_url);
        
        if (!empty($zippath)) {
            // ok, delete everything!
            $db = $this->getDatabase();
            $gameData = $db->query("SELECT game_configfile FROM game");
            if (count($gameData) > 0) {
                $configFilePath = self::getConfigDirectory().$gameData[0]['game_configfile'];
                unlink($configFilePath);
            }

            $db->DropSessionDatabase($db->GetDatabaseName());
            
            self::RemoveDirectory(Store::GetRasterStoreFolder($this->getGameSessionId()));
        }
    }

    /**
     *
     *
     * @noinspection PhpUnused
     * @noinspection SpellCheckingInspection
     * @throws       Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SaveSession(
        int $save_id,
        string $type = "full",
        string $response_url = '',
        bool $nooverwrite = false,
        string $preferredfolder = self::ARCHIVE_DIRECTORY,
        string $preferredname = "session_archive_"
    ): void {
        $sessionId = self::GetGameSessionIdForCurrentRequest();
        if ($preferredfolder == self::ARCHIVE_DIRECTORY) {
            $preferredfolder = self::Dir().$preferredfolder;
        }
        $zippath = $preferredfolder.$preferredname.$save_id.".zip";
        
        if ($nooverwrite) {
            if (file_exists($zippath)) {
                throw new Exception("File ".$zippath." already exists, so not continuing.");
            }
        }

        if ($type == "full") {
            $this->LocalApiRequest(
                "GameSession/CreateGameSessionZip",
                $sessionId,
                array(
                    "save_id" => $save_id, "response_url" => $response_url, "preferredfolder" => $preferredfolder,
                    "preferredname" => $preferredname
                ),
                true
            );
        } elseif ($type == "layers") {
            $post = array(
                "save_id" => $save_id, "response_url" => $response_url, "preferredfolder" => $preferredfolder,
                "preferredname" => $preferredname
            );
            $this->LocalApiRequest(
                "GameSession/CreateGameSessionLayersZip",
                $sessionId,
                $post,
                true
            );
        } else {
            throw new Exception("Type ".$type." is not recognised.");
        }
    }

    /**
     * @throws       Exception
     * @noinspection SpellCheckingInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateGameSessionZip(
        string $response_url = "",
        int $save_id = 0,
        string $preferredfolder = self::ARCHIVE_DIRECTORY,
        string $preferredname = "session_archive_"
    ): string {
        $sessionId = self::GetGameSessionIdForCurrentRequest();
        if (empty($save_id)) {
            $preferredid = $sessionId;
        } else {
            $preferredid = $save_id;
        }
        if ($preferredfolder == self::ARCHIVE_DIRECTORY) {
            $preferredfolder = self::Dir().$preferredfolder;
        }
        $zippath = $preferredfolder.$preferredname.$preferredid.".zip";
        $sqlDumpPath = self::Dir().self::EXPORT_DIRECTORY."db_export_".$sessionId.".sql";

        Store::EnsureFolderExists($preferredfolder);
        
        $this->getDatabase()->createMspDatabaseDump($sqlDumpPath, true);
    
        $configFilePath = null;
        $gameData = $this->getDatabase()->query("SELECT game_configfile FROM game");
        if (count($gameData) > 0) {
            $configFilePath = self::getConfigDirectory().$gameData[0]['game_configfile'];
        }

        $sessionFiles = array($sqlDumpPath, $configFilePath);

        $zip = new ZipArchive();
        if ($zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Wasn't able to create the ZIP file: ".$zippath);
        }
        foreach ($sessionFiles as $file) {
            if (is_readable($file)) {
                $zip->addFile($file, pathinfo($file, PATHINFO_BASENAME));
            }
        }
        foreach (Store::GetRasterStoreFolderContents($this->getGameSessionId()) as $rasterfile) {
            if (is_readable($rasterfile)) {
                $pathName = pathinfo($rasterfile, PATHINFO_DIRNAME);
                if (stripos($pathName, "archive") !== false) {
                    $zipFolder = "raster/archive/";
                } else {
                    $zipFolder = "raster/";
                }
                $zip->addFile($rasterfile, $zipFolder.pathinfo($rasterfile, PATHINFO_BASENAME));
            }
        }
        $zip->close();
        unlink($sqlDumpPath);
        // callback if requested
        if (!empty($response_url)) {
            $token = (new Security())->getServerManagerToken();
            $postValues = array(
                "token" => $token,
                "session_id" => $sessionId,
                "zippath" => $zippath,
                "action" => "processZip"
            );
            if (!empty($save_id)) {
                $postValues["save_id"] = $save_id;
            }
            $this->CallBack($response_url, $postValues);
        }
        
        return $zippath;
    }

    /**
     * @throws       Exception
     * @noinspection PhpUnused
     * @noinspection SpellCheckingInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateGameSessionLayersZip(
        int $save_id,
        string $response_url,
        string $preferredfolder = self::ARCHIVE_DIRECTORY,
        string $preferredname = "temp_layers_"
    ): string {
        $sessionId = self::GetGameSessionIdForCurrentRequest();
        if ($preferredfolder == self::ARCHIVE_DIRECTORY) {
            $preferredfolder = self::Dir().$preferredfolder;
        }
        $zippath = $preferredfolder.$preferredname.$save_id.".zip";

        $layer = new Layer();
        $alllayers = $layer->List();
        if (empty($alllayers)) {
            throw new Exception("No layers, so cannot continue.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Could not create the ZIP at ".$zippath);
        }
    
        // for each layer in the session, get the .json file with all its currently active geometry
        // and store it in the appropriate place
        foreach ($alllayers as $thislayer) {
            if ($thislayer["layer_geotype"] != "raster") {
                $layer_json = json_encode($layer->Export($thislayer["layer_id"]));
                $layer_filename = $thislayer["layer_name"].'.json';
                $zip->addFromString($layer_filename, $layer_json);
            } else {
                $layer_binary = $layer->ReturnRasterById($thislayer["layer_id"]);
                $layer_filename = $thislayer["layer_name"].'.tiff';
                $zip->addFromString($layer_filename, $layer_binary); // addFromString is binary-safe
            }
        }
        $zip->close();
        // callback if requested
        if (!empty($response_url)) {
            $token = (new Security())->getServerManagerToken();
            $postValues = array(
                "token" => $token, "save_id" => $save_id, "session_id" => $sessionId,
                "zippath" => $zippath, "action" => "processZip"
            );
            $this->CallBack($response_url, $postValues);
        }
        
        return $zippath;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetUserAccess(string $password_admin, string $password_player): void
    {
        // @noinspection SqlWithoutWhere
        $this->getDatabase()->query(
            "UPDATE game_session SET game_session_password_admin = ?, game_session_password_player = ?;",
            array($password_admin, $password_player)
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CheckGameSessionPasswords(): array
    {
        $adminHasPassword = true;
        $playerHasPassword = true;
        $passwordData = $this->getDatabase()->query(
            "SELECT game_session_password_admin, game_session_password_player FROM game_session"
        );
        if (count($passwordData) > 0) {
            if (!parent::isNewPasswordFormat($passwordData[0]["game_session_password_admin"])
                || !parent::isNewPasswordFormat($passwordData[0]["game_session_password_player"])
            ) {
                $adminHasPassword = !empty($passwordData[0]["game_session_password_admin"]);
                $playerHasPassword = !empty($passwordData[0]["game_session_password_player"]);
            } else {
                $password_admin = json_decode(base64_decode($passwordData[0]["game_session_password_admin"]), true);
                $password_player = json_decode(base64_decode($passwordData[0]["game_session_password_player"]), true);
                if ($password_admin["admin"]["provider"] == "local") {
                    $adminHasPassword = !empty($password_admin["admin"]["value"]);
                }
                if ($password_player["provider"] == "local") {
                    foreach ($password_player["value"] as $password) {
                        if (!empty($password)) {
                            $playerHasPassword = true;
                            break;
                        } else {
                            $playerHasPassword = false;
                        }
                    }
                }
            }
        }
        return array("adminhaspassword" => $adminHasPassword, "playerhaspassword" => $playerHasPassword);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function RemoveDirectory(string $dir): void
    {
        try {
            $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator(
                $it,
                RecursiveIteratorIterator::CHILD_FIRST
            );
        } catch (Exception $e) {
            $files = array();
        }
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function LocalApiRequest(string $apiUrl, int $sessionId, array $postValues, bool $async = false): void
    {
        $baseUrl = SymfonyToLegacyHelper::getInstance()->getUrlGenerator()->generate(
            'legacy_api_session',
            [
            'session' => $sessionId,
            'query' => ''
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $requestHeader = apache_request_headers();
        $headers = array();
        if (isset($requestHeader[ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN])) {
            $headers[] = ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN.': '.
                $requestHeader[ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN];
        }
        
        $this->CallBack($baseUrl.$apiUrl, $postValues, $headers, $async);
    }

    /**
     * @throws       Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function LoadGameSave(
        string $save_path,
        string $watchdog_address,
        string $response_address,
        int $game_id,
        bool $allow_recreate = false
    ): void {
        $sessionId = $game_id;
        if ($this->DoesSessionExist($sessionId)) {
            if (empty($allow_recreate)) {
                throw new Exception("Session already exists.");
            } else {
                $db = $this->getDatabase();
                $db->SwitchToSessionDatabase($sessionId);
                $db->DropSessionDatabase($db->GetDatabaseName());
            }
        }
        if (!file_exists($save_path)) {
            throw new Exception("Path to the save file (save_path) seems to be incorrect.");
        }
        $zip = new ZipArchive;
        if ($zip->open($save_path) !== true) {
            throw new Exception("Could not read the save file (save_path).");
        }
        if ($zip->locateName('game_list.json') === false) {
            throw new Exception("Missing game_list.json file within the save file (save_path).");
        }
        $originalSessionId = self::GetOriginalGameSessionIdFromSave($save_path);
        if ($originalSessionId == 0) {
            throw new Exception("Could not properly read game_list.json file within the save file (save_path).");
        }
        $dbase_in_zip = "db_export_".$originalSessionId.".sql";
        $config_in_zip = self::GetConfigFilePathForSession($originalSessionId);
        if ($zip->locateName($dbase_in_zip) === false) {
            throw new Exception("Missing database dump file within the save file (save_path).");
        }
        if ($zip->locateName($config_in_zip) === false) {
            throw new Exception("Missing configuration file within the save file (save_path).");
        }
        $zip->close();

        $postValues = array(
            "config_file_path" => "zip://".$save_path."#".$config_in_zip,
            "dbase_file_path" => "zip://".$save_path."#".$dbase_in_zip,
            "raster_files_path" => $save_path,
            "watchdog_address" => $watchdog_address,
            "response_address" => $response_address
        );
        $this->LocalApiRequest("GameSession/LoadGameSaveAndSignal", $sessionId, $postValues, true);
    }

    /**
     * @throws       Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function LoadGameSaveAndSignal(
        string $config_file_path,
        string $dbase_file_path,
        string $raster_files_path,
        string $watchdog_address,
        string $response_address
    ): void {
        $configDir = self::getConfigDirectory();
        if (!is_dir($configDir)) {
            mkdir($configDir);
        }
        $zipped_config_file_content = file_get_contents($config_file_path);
        $new_config_file_name = self::GetConfigFilePathForSession(self::GetGameSessionIdForCurrentRequest());
        file_put_contents($configDir.$new_config_file_name, $zipped_config_file_content);

        $update = new Update();
        $result = $update->ReloadAdvanced($new_config_file_name, $dbase_file_path, $raster_files_path);

        $postValues["session_id"] = self::GetGameSessionIdForCurrentRequest();
        $postValues["token"] = (new Security())->getServerManagerToken(); // to pass ServerManager security
            
        if (!$result) {
            if (!empty($response_address)) {
                $postValues["session_state"] = "failed";
                $this->updateServerManagerGameList($postValues);
            }
            throw new Exception("Reload of save failed");
        }

        $this->ResetWatchdogAddress($watchdog_address);

        $game = new Game();
        $this->asyncDataTransferTo($game);
        await($game->changeWatchdogState("PAUSE")); // reloaded saves always start paused
        
        if (!empty($response_address)) {
            $postValues["session_state"] = "healthy";
            $this->updateServerManagerGameList($postValues);
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetOriginalGameSessionIdFromSave(string $save_path): int
    {
        // use the passed-on zip to get the original session_id
        $source = "zip://".$save_path."#game_list.json";
        $content = json_decode(file_get_contents($source), true);
        if (!is_null($content) && $content !== false) {
            return $content["id"];
        }
        return 0;
    }
}
