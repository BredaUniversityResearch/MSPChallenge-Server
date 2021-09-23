<?php

class UnitTestSupport extends Base 
{
	public function __construct($method = "")
	{
		parent::__construct($method);

		$config = Config::GetInstance()->GetUnitTestLoggerConfig();
		if (!self::ShouldLogApiCalls())
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

	public static function ShouldLogApiCalls()
	{
		$config = Config::GetInstance()->GetUnitTestLoggerConfig();
		return isset($config["enabled"]) && $config["enabled"] === true;
	}

	public function RecordApiCall(string $class, string $method, array $data, array $result)
	{
		$requestHeaders = apache_request_headers();
		$requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
		
		if (isset($requestHeaders["msp_force_no_call_log"]))
		{
			return;
		}

		$requestKey = strtolower($class)."::".strtolower($method);
		if ($this->IsCallIdentifierOnIgnoreList($requestKey))
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

			$statFilePath = $outputFolder."summary.json";
			$data = array();
			
			$statFile = fopen($statFilePath, "c+");
			while(!flock($statFile, LOCK_EX))
				continue;
			fseek($statFile, 0, SEEK_END);
			$statFileSize = ftell($statFile);
			if ($statFileSize > 0)
			{
				fseek($statFile, 0, SEEK_SET);
				$statData = fread($statFile, filesize($statFilePath));
				$data = json_decode($statData, true);
			}
			
			if (isset($data[$requestKey]))
			{
				$data[$requestKey] = $data[$requestKey] + 1;
			}
			else
			{
				$data[$requestKey] = 1;
			}

			ftruncate($statFile, 0);
			fseek($statFile, 0);
			fwrite($statFile, json_encode($data, JSON_PRETTY_PRINT));
			fflush($statFile);
			flock($statFile, LOCK_UN);
			fclose($statFile);
		}
	} 

	private function IsCallIdentifierOnIgnoreList(string $callIdentifier)
	{
		$config = Config::GetInstance()->GetUnitTestLoggerConfig();
		return (isset($config["request_filter"]["ignore"]) && in_array($callIdentifier, $config["request_filter"]["ignore"]));
	}
};

?>