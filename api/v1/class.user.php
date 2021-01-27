<?php
	class User extends Base {

		protected $allowed = array(
			["RequestSession", Security::ACCESS_LEVEL_FLAG_NONE]
		); 

		/**
		 * @apiGroup User
		 * @apiDescription Creates a new session for the desired country id.
		 * @api {POST} /user/RequestSession Set State
		 * @apiSuccess {json} Returns a json object describing the 'success' state, the 'session_id' generated for the user. And in case of a failure a 'message' that describes what went wrong.  
		 */
		public function RequestSession(int $build_version = 0, int $country_id = 0, string $country_password = "")
		{
			$response = array();

			$game = new Game();
			$config = $game->GetGameConfigValues();

			if (array_key_exists("application_versions", $config))
			{
				$versionConfig = $config["application_versions"];
				$minVersion = (array_key_exists("client_version_min", $versionConfig))? $versionConfig["client_version_min"] : 0;
				$maxVersion = (array_key_exists("client_version_max", $versionConfig))? $versionConfig["client_version_max"] : -1;

				if ($minVersion > $build_version || ($maxVersion > 0 && $maxVersion < $build_version))
				{
					if ($maxVersion > 0)
					{
						$clientVersionsMessage = "Accepted client versions are between ".$minVersion." and ".$maxVersion.".";
					}
					else 
					{
						$clientVersionsMessage = "Accepted client versions are from ".$minVersion." onwards.";
					}

					throw new Exception("Incompatible client version.\n".$clientVersionsMessage."\nYour client version is ".$build_version.".");
				}
			}

			$passwords = $this->query("SELECT game_session_password_admin, game_session_password_player FROM game_session");
			$hasCorrectPassword = true;
			if (count($passwords) > 0)
			{
				$password =  ($country_id < 3)? $passwords[0]["game_session_password_admin"] : $passwords[0]["game_session_password_player"];
				$hasCorrectPassword = $password == $country_password;
			}

			if ($hasCorrectPassword)
			{
				$response["session_id"] = $this->query("INSERT INTO user(user_lastupdate, user_country_id) VALUES (0, ?)", array($country_id), true);
				$security = new Security();
				$response["api_access_token"] = $security->GenerateToken()["token"];
				$response["api_access_recovery_token"] = $security->GetRecoveryToken()["token"];
			}
			else 
			{
				throw new Exception("Incorrect password.");
			}

			return $response;
		}
	}
?>
