<?php

namespace App\Domain\API;

use Exception;
use PDO;
use PDOException;

class APIHelper
{
    public const API_LATEST_VERSION = "v1";
    private const INVALID_SESSION_ID = -1;
    private string $currentBaseFolder;
    private static ?APIHelper $instance = null;

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function __construct(string $projectDir)
    {
        $this->currentBaseFolder = $projectDir . '/';

        // constructor will be called by Symfony services mechanism.
        //   Register instance such that legacy code can use ::getInstance()
        self::$instance = $this;
    }

    /**
     * @throws Exception
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            throw new Exception(__CLASS__  . ' instance has not been initialised');
        }
        return self::$instance;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetGameSessionIdForCurrentRequest(): int
    {
        $sessionId = self::INVALID_SESSION_ID;
        if (isset($_GET['session'])) {
            $sessionId = intval($_GET['session']);
            if ($sessionId <= 0) {
                $sessionId = self::INVALID_SESSION_ID;
            }
        }
        return $sessionId;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetBaseFolder(): string
    {
        return $this->currentBaseFolder;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetCurrentSessionServerApiVersion(?string $baseFolder = null): string
    {
        if (null === $baseFolder) {
            $baseFolder = $this->currentBaseFolder;
        }
        require_once($baseFolder."api_config.php");

        if (!empty($_POST["use_server_api_version"])) {
            //print("Using server version ".$_POST["use_server_api_version"]);
            return $_POST["use_server_api_version"];
        }

        $dbConfig = $GLOBALS['api_config']['database'];
        $dbUser = $dbConfig['user'];
        $dbPass = $dbConfig['password'];
        $dbHost = $dbConfig['host'];

        $sessionId = $this->GetGameSessionIdForCurrentRequest();
        if ($sessionId != self::INVALID_SESSION_ID) {
            $dbName = $dbConfig["multisession_database_prefix"] . $sessionId;

            try {
                $db = new PDO("mysql:host=" . $dbHost . ";dbname=" . $dbName, $dbUser, $dbPass, array(
                    PDO::MYSQL_ATTR_LOCAL_INFILE => true
                ));

                $result = $db->query(
                    "SELECT game_session_api_version_server FROM game_session_api_version",
                    PDO::FETCH_ASSOC
                );
                if ($result !== false) {
                    $fetchedResult = $result->fetch(PDO::FETCH_ASSOC);
                    if ($fetchedResult !== false) {
                        //print("Using server version ".$fetchedResult["game_session_api_version_server"]);
                        return $fetchedResult["game_session_api_version_server"];
                    }
                }
            } catch (PDOException $ex) {
                return self::API_LATEST_VERSION;
            }
        }

        //print("Using server version ".self::ApiLatestVersion);
        return self::API_LATEST_VERSION;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetCurrentSessionServerApiFolder(?string $baseFolder = null): string
    {
        if (null === $baseFolder) {
            $baseFolder = $this->currentBaseFolder;
        }
        $apiVersion = $this->GetCurrentSessionServerApiVersion();
        return $this->GetApiFolder($baseFolder, $apiVersion);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetApiFolder(string $baseFolder, string $apiVersion): string
    {
        $targetFolder = $baseFolder. "api/" . $apiVersion . "/";
        if (!is_dir($targetFolder)) {
            die("Failed to load API at location \"" . $targetFolder . "\" for API version \"" . $apiVersion . "\"");
        }
        return $targetFolder;
    }
}
