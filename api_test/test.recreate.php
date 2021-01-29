<?php

class TestRecreate extends TestBase 
{
	public function __construct(string $token)
	{
		parent::__construct($token);
	}

	/**
	 * @TestMethod
	 */
	public function RecreateSession()
	{
		$postData = array ("config_file_path" => "session_config_1.json",
			"geoserver_url" => $GLOBALS['api_config']["geoserver_url"], 
			"geoserver_username" => "admin", 
			"geoserver_password" => "geoserver", 
			"password_admin" => "a", 
			"password_player" => "",
			"watchdog_address" => "localhost",
			"response_address" => null,
			"jwt" => null
		);
		$response = $this->DoApiRequest("/api/GameSession/CreateGameSessionAndSignal", $postData);
	}
};