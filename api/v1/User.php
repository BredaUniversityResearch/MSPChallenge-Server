<?php

namespace App\Domain\API\v1;

use DateTime;
use DateTimeInterface;
use Exception;

class User extends Base
{
    private const ALLOWED = array(
        ["RequestSession", Security::ACCESS_LEVEL_FLAG_NONE],
        "CloseSession",
        ["getProviders", Security::ACCESS_LEVEL_FLAG_NONE],
        ["checkExists", Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER]
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup User
     * @apiDescription Creates a new session for the desired country id.
     * @throws Exception
     * @api {POST} /user/RequestSession Set State
     * @apiSuccess {json} Returns a json object describing the 'success' state, the 'session_id' generated for the user.
     *   And in case of a failure a 'message' that describes what went wrong.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function RequestSession(
        string $build_timestamp,
        int $country_id,
        string $country_password = "",
        string $user_name = ""
    ): array {
        $response = array();
        $this->CheckVersion($build_timestamp);
            
        $passwords = Database::GetInstance()->query(
            "SELECT game_session_password_admin, game_session_password_player FROM game_session"
        );
        $password_admin = $passwords[0]["game_session_password_admin"];
        $password_player = $passwords[0]["game_session_password_player"];
        if (!parent::isNewPasswordFormat($password_admin) || !parent::isNewPasswordFormat($password_player)) {
            return $this->RequestSessionLegacy($country_id, $country_password, $user_name);
        } else {
            $password_admin = json_decode(base64_decode($password_admin), true);
            $password_player = json_decode(base64_decode($password_player), true);
                
            // check whether this is an admin, region manager, or player requesting entrance and get the authentication
            //   provider accordingly
            if ($country_id == 1) {
                $provider = $password_admin["admin"]["provider"];
            } elseif ($country_id == 2) {
                $provider = $password_admin["region"]["provider"];
            } else {
                $provider = $password_player["provider"];
            }
            // request authentication
            if ($provider == "local") {
                // simple locally stored password authentication
                if ($country_id == 1) {
                    $authenticated = $country_password == $password_admin["admin"]["value"];
                } elseif ($country_id == 2) {
                    $authenticated = $country_password == $password_admin["region"]["value"];
                } else {
                    $authenticated = $country_password == $password_player["value"][$country_id];
                }

                if (!$authenticated) {
                    throw new Exception("That password is incorrect.");
                }
            } else {
                // an external username/password authentication provider should be used
                $user_name = $this->callProvidersAuthentication($provider, $user_name, $country_password);
                // now do the authorization
                if (!empty($user_name)) {
                    if ($country_id == 1) {
                        $userlist = $password_admin["admin"]["value"];
                    } elseif ($country_id == 2) {
                        $userlist = $password_admin["region"]["value"];
                    } else {
                        $userlist = $password_player["value"][$country_id];
                    }
                    $userarray = explode(" ", $userlist);
                    if (!in_array($user_name, $userarray)) {
                        throw new Exception("You are not allowed to log on for that country.");
                    }
                } else {
                    throw new Exception(
                        "Could not authenticate you. Your username and/or password could be incorrect."
                    );
                }
            }
            // all is well!
            $response["session_id"] = Database::GetInstance()->query(
                "INSERT INTO user(user_name, user_lastupdate, user_country_id) VALUES (?, 0, ?)",
                array($user_name, $country_id),
                true
            );
            $security = new Security();
            $response["api_access_token"] = $security->generateToken()["token"];
            $response["api_access_recovery_token"] = $security->getRecoveryToken();
        }
        return $response;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function RequestSessionLegacy(
        int $countryId,
        string $countryPassword = "",
        string $userName = ""
    ): array {
        $response = array();
        $passwords = Database::GetInstance()->query(
            "SELECT game_session_password_admin, game_session_password_player FROM game_session"
        );
        $hasCorrectPassword = true;
        if (count($passwords) > 0) {
            $password =  ($countryId < 3) ?
                $passwords[0]["game_session_password_admin"] : $passwords[0]["game_session_password_player"];
            $hasCorrectPassword = $password == $countryPassword;
        }

        if ($hasCorrectPassword) {
            try {
                $response["session_id"] = Database::GetInstance()->query(
                    "INSERT INTO user(user_name, user_lastupdate, user_country_id) VALUES (?, 0, ?)",
                    array($userName, $countryId),
                    true
                );
                $security = new Security();
                $response["api_access_token"] = $security->generateToken()["token"];
                $response["api_access_recovery_token"] = $security->getRecoveryToken();
            } catch (Exception $e) {
                throw new Exception(
                    "Could not log you in. Please check with your session administrator." .
                    "This session might need upgrading."
                );
            }
        } else {
            throw new Exception("Incorrect password.");
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CheckVersion(string $build_timestamp): void
    {
        $game = new Game();
        $config = $game->GetGameConfigValues();

        if (array_key_exists("application_versions", $config)) {
            $clientBuildDate = DateTime::createFromFormat(DateTimeInterface::ATOM, $build_timestamp);
            $versionConfig = $config["application_versions"];
            $minDate = new DateTime("@0");
            $minBuildDate = $minDate;
            $maxBuildDate = new DateTime(); // now
            if (array_key_exists("client_build_date_min", $versionConfig)) {
                $minBuildDate = DateTime::createFromFormat(
                    DateTimeInterface::ATOM,
                    $versionConfig["client_build_date_min"]
                );
            }
            if (array_key_exists("client_build_date_max", $versionConfig)) {
                $maxBuildDate = DateTime::createFromFormat(
                    DateTimeInterface::ATOM,
                    $versionConfig["client_build_date_max"]
                );
            }
            if ($minBuildDate > $clientBuildDate || ($maxBuildDate > $minDate && $maxBuildDate < $clientBuildDate)) {
                if ($maxBuildDate > $minDate) {
                    $clientVersionsMessage = "Accepted client versions are between ".
                        $minBuildDate->format(DateTimeInterface::ATOM)." and ".
                        $maxBuildDate->format(DateTimeInterface::ATOM).".";
                } else {
                    $clientVersionsMessage = "Accepted client versions are from ".
                        $minBuildDate->format(DateTimeInterface::ATOM)." onwards.";
                }

                throw new Exception("Incompatible client version.\n".$clientVersionsMessage.
                    "\nYour client version is ".
                    $clientBuildDate->format(DateTimeInterface::ATOM).".");
            }
        }
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CloseSession(int $session_id): void
    {
        Database::GetInstance()->query("UPDATE user SET user_loggedoff = 1 WHERE user_id = ?", array($session_id));
    }

    private function checkProviderExists($provider): bool
    {
        return is_subclass_of($provider, Auths::class);
    }
    
    public function getProviders(): array
    {
        $return = array();
        self::AutoloadAllClasses();
        foreach (get_declared_classes() as $class) {
            if ($this->checkProviderExists($class)) {
                $return[] = [
                    "id" => $class,
                    "name" => (new $class)->getName()
                ];
            }
        }
        return $return;
    }

    /**
     * @throws Exception
     */
    public function checkExists(string $provider, string $users)
    {
        if ($this->checkProviderExists($provider)) {
            $call_provider = new $provider;
            return $call_provider->checkuser($users);
        }
        throw new Exception("Could not work with authentication provider '".$provider."'.");
    }

    /**
     * @throws Exception
     */
    private function callProvidersAuthentication(string $provider, string $username, string $password)
    {
        if ($this->checkProviderExists($provider)) {
            $call_provider = new $provider;
            return $call_provider->authenticate($username, $password);
        }
        throw new Exception("Could not work with authentication provider '".$provider."'.");
    }
}
