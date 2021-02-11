<?php

class Config
{
	private static $instance;
	public static function GetInstance()
	{
		if (Config::$instance == null)
		{
			Config::$instance = new Config();
		}
		return Config::$instance;
	}

	private $configRoot = null;

	private function __construct()
	{
		$this->LoadConfigFile();
	}

	private function LoadConfigFile()
	{
		
		require_once(APIHelper::GetBaseFolder()."api_config.php");
		$this->configRoot = $GLOBALS['api_config'];
	}

	public function GetAuth() 
	{
		global $auth_url;
		if (!empty($this->configRoot["msp_auth"])) return $this->configRoot["msp_auth"];
		else return $auth_url;
	}

	public function GetAuthWithProxy()
	{
		if (!empty($this->configRoot["msp_auth_with_proxy"])) return $this->configRoot["msp_auth_with_proxy"];
		else return false;
	}
	
	public function GetCodeBranch()
	{
		return $this->configRoot["code_branch"];
	}

	public function GetGeoserverUrl()
	{
		return $this->configRoot["geoserver_url"];
	}

	public function GetGeoserverCredentialsEndpoint()
	{
		return $this->configRoot["geoserver_credentials_endpoint"];
	}

	public function GetAuthApiKey()
	{
		return $this->configRoot["api_key"];
	}

	public function GetAuthServerSessionLogEndpoint()
	{
		return $this->configRoot["authserver_log_session_info_endpoint"];
	}

	public function GetGameAutosaveInterval()
	{
		return $this->configRoot["game_autosave_interval"];
	}

	public function GetLongRequestTimeout()
	{
		return $this->configRoot["long_request_timeout"];
	}

	public function ShouldWaitForSimulationsInDev()
	{
		return $this->configRoot["wait_for_simulations_in_dev"];
	}

	public function RemoteApiUrl()
	{
		return $this->configRoot["remote_api_url"];
	}

	public function WikiConfig()
	{
		return $this->configRoot["wiki"];
	}

	public function DevConfig()
	{
		return isset($this->configRoot["dev_config"])? $this->configRoot["dev_config"] : array();
	}

	public function DatabaseConfig()
	{
		return isset($this->configRoot["database"])? $this->configRoot["database"] : array();
	}

	public function GetUnitTestLoggerConfig()
	{
		return isset($this->configRoot["unit_test_logger"])? $this->configRoot["unit_test_logger"] : array();
	}
}

?>
