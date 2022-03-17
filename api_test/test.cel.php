<?php 

class TestCEL extends TestBase
{
	public function __construct(string $token)
	{
		parent::__construct($token);
	}

	/**
	 * @TestMethod
	 */
	public function GetConnections()
	{
		$response = $this->DoApiRequest("/api/cel/GetConnections", array());
		Assert::ExpectArray($response);
	}
}
?>