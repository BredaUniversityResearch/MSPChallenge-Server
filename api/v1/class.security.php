<?php

class Security extends Base
{
	const ACCESS_LEVEL_FLAG_FULL = 0x7FFFFFFF;
	const ACCESS_LEVEL_FLAG_NONE = 0;
	const ACCESS_LEVEL_FLAG_REQUEST_TOKEN = (1 << 0);
	const ACCESS_LEVEL_FLAG_SERVER_MANAGER = (1 << 1);
	
	const DEFAULT_TOKEN_LIFETIME_SECONDS = 5 * 60;
	const DEFAULT_TOKEN_RENEWAL_TIME = 1 * 60;
	const TOKEN_LIFETIME_INFINITE = -1;
	const TOKEN_DELETE_AFTER_TIME = self::DEFAULT_TOKEN_LIFETIME_SECONDS + 30 * 60;

	private const DISABLE_SECURITY_CHECK = true;
	
	protected $allowed = array(
		["RequestToken", Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN], 
		["CheckAccess", Security::ACCESS_LEVEL_FLAG_NONE]
	);
	
	public function __construct($method = "")
	{
		parent::__construct($method);
	}

	/**
	 * @apiGroup Security
	 * @api {GET} /security/CheckAccess CheckAccess
	 * @apiDescription Checks if the the current access token is valid to access a certain level. Currently only checks for full access tokens.
	 * @apiResult Returns json object indicating status of the current token. { "status": ["Valid"|"UpForRenewal"|"Expired"] }
	 */
	public function CheckAccess()
	{
		$accessTimeRemaining = 0;
		$hasAccess = $this->ValidateAccess(self::ACCESS_LEVEL_FLAG_FULL, $accessTimeRemaining);

		$result = "Expired";
		if ($hasAccess)
		{
			$result = ($accessTimeRemaining <= self::DEFAULT_TOKEN_RENEWAL_TIME)? "UpForRenewal" : "Valid";
		}

		return array("status" => $result, "time_remaining" => $accessTimeRemaining);
	}

	/**
	* @apiGroup Security
	* @api {POST} /security/RequestToken RequestToken
	* @apiDescription Requests a new access token for the API.
	* @apiParam expired_token OPTIONAL A previously used access token that is now expired. Needs a valid REQUEST_ACCESS token to be sent with the request before it generates a new token with the same access as the expired token.
	* @apiResult Returns json object indicating success and the token containing token identifier and unix timestap for until when it's valid. { "success": [0|1], "token": { "token": [identifier], "valid_until": [timestamp]" }
	*/
	public function RequestToken(string $expired_token = "")
	{
		$token = null;
	
		$requestToken = $this->GetCurrentRequestTokenDetails();
		if ($requestToken != null)
		{
			if ($this->TokenHasAccess($requestToken, self::ACCESS_LEVEL_FLAG_FULL))
			{
				$token = $this->GenerateToken();
			}
			else
			{
				if (!empty($expired_token))
				{
					$expiredTokenDetails = $this->GetTokenDetails($expired_token);
					if ($expiredTokenDetails != null)
					{
						$token = $this->GenerateToken($expiredTokenDetails['api_token_scope']);
					}
				}
			}
		}
		if ($token == null)
		{
			throw new Exception("Request token failed");
		}

		return $token;
	}

	//Returns array of [token => TokenValue, valid_until => UnixTimestamp]
	public function GenerateToken($accessLevel = self::ACCESS_LEVEL_FLAG_FULL, $lifetimeSeconds = self::DEFAULT_TOKEN_LIFETIME_SECONDS)
	{
		Database::GetInstance()->query("DELETE FROM api_token WHERE api_token_valid_until != 0 AND api_token_valid_until < DATE_ADD(NOW(), INTERVAL -? SECOND)", array(self::TOKEN_DELETE_AFTER_TIME));
		
		$token = random_int(0, PHP_INT_MAX);
		if ($lifetimeSeconds == self::TOKEN_LIFETIME_INFINITE)
		{
			$id = Database::GetInstance()->query("INSERT INTO api_token (api_token_token, api_token_scope, api_token_valid_until) VALUES(?, ?, 0)", array($token, $accessLevel), true); 
		}
		else
		{
			$id = Database::GetInstance()->query("INSERT INTO api_token (api_token_token, api_token_scope, api_token_valid_until) VALUES(?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))", array($token, $accessLevel, $lifetimeSeconds), true); 
		}
		$result = Database::GetInstance()->query("SELECT api_token_token, api_token_valid_until FROM api_token WHERE api_token_id = ?", array($id));
		return array("token" => $result[0]["api_token_token"], "valid_until" => $result[0]["api_token_valid_until"]);
	}

	//Returns array of [token => TokenValue]
	public function GetRecoveryToken()
	{
		return $this->GetSpecialToken(self::ACCESS_LEVEL_FLAG_REQUEST_TOKEN);
	}
	
	//Returns array of [token => TokenValue]
	public function GetServerManagerToken()
	{
		return $this->GetSpecialToken(self::ACCESS_LEVEL_FLAG_SERVER_MANAGER);
	}

	private function GetSpecialToken($accessLevel)
	{
		if ($accessLevel == self::ACCESS_LEVEL_FLAG_REQUEST_TOKEN || $accessLevel == self::ACCESS_LEVEL_FLAG_SERVER_MANAGER)
		{
			$tokenInfo = Database::GetInstance()->query("SELECT api_token_token FROM api_token WHERE api_token_valid_until = 0 AND api_token_scope = ?", array($accessLevel));
			$token = "";
			if (count($tokenInfo) > 0)
			{
				$token = $tokenInfo[0]["api_token_token"];
			}
			return array("token" => $token);
		}
		return array("token" => "");
	}

	private function GetCurrentRequestTokenDetails()
	{
		$token = $this->FindAuthenticationHeaderValue();
		if ($token == null)
		{
			return null;
		}
		return $this->GetTokenDetails($token);
	}

	private function GetTokenDetails($tokenValue)
	{
		$details = Database::GetInstance()->query("SELECT api_token_scope, UNIX_TIMESTAMP(api_token_valid_until) as expiry_time, UNIX_TIMESTAMP(api_token_valid_until) - UNIX_TIMESTAMP(NOW()) as valid_time_remaining FROM api_token WHERE api_token_token = ?", array($tokenValue));
		if (count($details) > 0)
		{
			return $details[0];
		}
		return null;
	}

	private function TokenHasAccess($tokenDetails, $accessLevel)
	{
		if (($tokenDetails["api_token_scope"] & $accessLevel) == $accessLevel)
		{
			return true;
		}
		return false;
	}

	public function ValidateAccess($requiredAccessLevelFlags, &$tokenValidTimeRemaining = null)
	{
		if (self::DISABLE_SECURITY_CHECK)
		{
			$tokenValidTimeRemaining = self::DEFAULT_TOKEN_LIFETIME_SECONDS;
			return true;
		}

		if ($requiredAccessLevelFlags == self::ACCESS_LEVEL_FLAG_NONE)
		{
			return true;
		}

		$token = $this->GetCurrentRequestTokenDetails();
		if ($token == null)
		{
			return false;
		}

		if (!$this->TokenHasAccess($token, $requiredAccessLevelFlags))
		{
			return false;
		}

		if ($token["valid_time_remaining"] < 0 && $token["expiry_time"] > 0)
		{
			return false;
		}

		if($tokenValidTimeRemaining !== null)
		{
			$tokenValidTimeRemaining = $token["valid_time_remaining"];
		}
		return true;
	}

	private function FindAuthenticationHeaderValue()
	{
		$requestHeaders = apache_request_headers();
		$requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
		
		if (isset($requestHeaders["mspapitoken"]))
		{
			return $requestHeaders["mspapitoken"];
		}
		return null;
	}
};

?>