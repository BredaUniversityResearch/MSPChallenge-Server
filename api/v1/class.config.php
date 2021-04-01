<?php

class Config
{
	private $msp_auth = "https://auth.mspchallenge.info/usersc/plugins/apibuilder/authmsp/";
		
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

	public function GetCodeBranch()
	{
		return $this->configRoot["code_branch"];
	}

	public function GetAuth() 
	{
		return $this->msp_auth;
	}

	public function GetAuthJWTRetrieval() 
	{
		return $this->GetAuth()."getjwt.php";
	}

	public function GetAuthJWTUserCheck() 
	{
		return $this->GetAuth()."checkuserjwt.php";
	}

	public function GetGeoserverCredentialsEndpoint()
	{
		return $this->GetAuth()."geocredjwt.php";
	}
	
	public function GetAuthWithProxy()
	{
		if (!empty($this->configRoot["msp_auth_with_proxy"])) return $this->configRoot["msp_auth_with_proxy"];
		else return false;
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
