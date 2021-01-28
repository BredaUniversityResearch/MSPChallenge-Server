<?php

class APIHelper
{
	public const ApiLatestVersion = "v1";
	private const InvalidSessionId = -1;

	public static function SetupApiLoader()
	{
		$apiVersion = self::GetCurrentSessionServerApiVersion();

		spl_autoload_register(self::ApiLoaderFunctionWrapper($apiVersion));
	}

	private static function GetGameSessionIdForCurrentRequest()
	{
		$sessionId = self::InvalidSessionId;
		if (isset($_GET['session'])) 
		{
			$sessionId = intval($_GET['session']);
			if ($sessionId <= 0)
			{
				$sessionId = self::InvalidSessionId;
			}
		}
		return $sessionId;
	}

	public static function GetCurrentSessionServerApiVersion()
	{
		require_once("api_config.php");

		if (!empty($_POST["use_server_api_version"]))
		{
			//print("Using server version ".$_POST["use_server_api_version"]);
			return $_POST["use_server_api_version"];
		}

		$dbConfig = $GLOBALS['api_config']['database'];
		$dbUser = $dbConfig['user'];
		$dbPass = $dbConfig['password'];
		$dbHost = $dbConfig['host'];

		$sessionId = self::GetGameSessionIdForCurrentRequest();
		if ($sessionId != self::InvalidSessionId) 
		{
			$dbName = $dbConfig["multisession_database_prefix"].$sessionId;

			try 
			{
				$db = new PDO("mysql:host=".$dbHost.";dbname=".$dbName, $dbUser, $dbPass, array(
						PDO::MYSQL_ATTR_LOCAL_INFILE => true
					));

				$result = $db->query("SELECT game_session_api_version_server FROM game_session_api_version", PDO::FETCH_ASSOC);
				if ($result !== false)
				{
					$fetchedResult = $result->fetch(PDO::FETCH_ASSOC);
					if ($fetchedResult !== false)
					{
						//print("Using server version ".$fetchedResult["game_session_api_version_server"]);
						return $fetchedResult["game_session_api_version_server"];
					}
				}
			}
			catch( PDOException $ex)
			{
				return self::ApiLatestVersion;
			}
		}

		//print("Using server version ".self::ApiLatestVersion);
		return self::ApiLatestVersion;
	}

	public static function GetCurrentSessionServerApiFolder()
	{
		$apiVersion = self::GetCurrentSessionServerApiVersion();
		return self::GetApiFolder($apiVersion);
	}

	private static function GetApiFolder($apiVersion)
	{
		$targetFolder = "api/".$apiVersion."/";
		if (!is_dir("api/".$apiVersion."/"))
		{
			die("Failed to load API at location \"".$targetFolder."\" for API version \"".$apiVersion."\"");
		}
		return $targetFolder;
	}

	public static function ApiLoaderFunctionWrapper($apiVersion)
	{
		return function($className) use ($apiVersion) {
			$includeFileName = self::GetApiFolder($apiVersion)."class." . strtolower($className) . ".php";
			include($includeFileName);
		};
	}
};