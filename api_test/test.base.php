<?php

class TestBase
{
	const TARGET_SERVER_BASE_URL = "http://localhost/dev/1/";
	private string $m_securityToken;

	public function __construct($securityToken)
	{
		$this->m_securityToken = $securityToken;
	}

	public function RunAll()
	{
		$type = new ReflectionClass($this);
		$methods = $type->getMethods(ReflectionMethod::IS_PUBLIC);

		foreach($methods as $method)
		{
			$comment = $method->getDocComment();
			if ($comment === false)
			{
				continue;
			}

			if (strstr($comment, "@TestMethod"))
			{
				try
				{
					$method->invoke($this);
					print("✅ ".$type->getName()."::".$method->getName()."".PHP_EOL);
				}
				catch (\Throwable $e)
				{
					print("❌ ". $type->getName()."::".$method->getName()." Failed. Exception thrown: ".$e->getMessage(). " in ".$e->getFile().":".$e->getLine().PHP_EOL);
				}
			}
		}
	}

	protected function DoRequest(string $endpoint, array $postData)
	{
		$ch = curl_init(self::TARGET_SERVER_BASE_URL.$endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("mspapitoken: ".$this->m_securityToken));
		$response = curl_exec($ch);
		if ($response === false)
		{
			throw new Exception("DoRequest to ".$endpoint." (".var_export($postData, true).") failed with error: ".curl_error($ch));
		}
		curl_close($ch);
		return $response;
	}

	protected function DoApiRequest(string $endpoint, array $postData)
	{
		$response = $this->DoRequest($endpoint, $postData);
		$decodedJson = json_decode($response, true);
		if ($decodedJson === null)
		{
			throw new Exception("DoApiRequest ".$endpoint." (".var_export($postData, true).") failed to decode JSON: ".json_last_error_msg().PHP_EOL."Response: ".$response);
		}

		if ($decodedJson["success"] == 0)
		{
			throw new Exception("DoApiRequest ".$endpoint." (".var_export($postData, true).") Returned failure: ".$decodedJson["message"]);
		}

		return $decodedJson["payload"];
	}
};

?>