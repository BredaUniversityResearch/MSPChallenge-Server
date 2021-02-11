<?php

class UnitTestSupport extends Base 
{
	public function __construct($method = "")
	{
		parent::__construct($method);

		$config = Config::GetInstance()->GetUnitTestLoggerConfig();
		if (!isset($config["enabled"]) || $config["enabled"] !== true)
		{
			throw new Exception("This should not be instantiated in non-development environments");
		}
	}

	public static function GetIntermediateFolder()
	{
		$dbName = Database::GetInstance()->GetDatabaseName();
		if (empty($dbName))
		{
			return null;
		}

		$config = Config::GetInstance()->GetUnitTestLoggerConfig();
		return $config["intermediate_folder"].$dbName."/";
	}

	public function RecordApiCall(string $class, string $method, array $data, array $result)
	{
		$requestHeaders = apache_request_headers();
		$requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
		
		if (isset($requestHeaders["msp_force_no_call_log"]))
		{
			return;
		}

		$outputData = array ("call_class" => $class, 
			"call_method" => $method,
			"call_data" => $data,
			"result" => $result
		);

		$outputFolder = self::GetIntermediateFolder();
		if ($outputFolder != null)
		{
			if (!is_dir($outputFolder))
			{
				mkdir($outputFolder, 0666, true);
			}
		
			$filename = ((string)microtime(true)).".json";
			file_put_contents($outputFolder.$filename, json_encode($outputData));
		}
	} 
};

?>