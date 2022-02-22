<?php

namespace App\Domain\API\v1;

use Exception;
use stdClass;

/**
 * when you create your own authentication provider subclass, make sure you define these methods:
 * public function getName();
 * public function authenticate($username, $password);
 * just like in the default MSP Challenge authentication provider below.
 *
 * @noinspection PhpUnused
 */
// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps
class Auth_MSP extends Auths
{
    private string $name = 'MSP Challenge';

    public function getName(): string
    {
        return $this->name;
    }

    private function getJsonWebTokenObject(): object
    {
        // get a temp JWT from the Authoriser for further communication
        try {
            // for this we first need this MSP Challenge's server_id from the ServerManager
            $serverManagerReturn = json_decode(
                $this->CallBack(
                    GameSession::GetServerManagerApiRoot()."readServerManager.php",
                    array(
                        "token" => (new Security())->GetServerManagerToken()["token"],
                        "session_id" => $this->getGameSessionId()
                    )
                )
            );
            if (!$serverManagerReturn->success) {
                throw new Exception();
            }
            // and we send the server_id through to the Authoriser to request a jwt (JSON web token)
            $jwtReturn = json_decode(
                $this->CallBack(
                    Config::getInstance()->GetAuthJWTRetrieval(),
                    array(
                        "audience" => GameSession::GetRequestApiRoot(),
                        "server_id" => $serverManagerReturn->servermanager->server_id
                    ),
                    array(), // no headers
                    false, // synchronous, so wait
                    true // post as json
                )
            );
        } catch (Exception $e) {
            $jwtReturn = new stdClass();
            $jwtReturn->success = false;
        }
        return $jwtReturn;
    }

    /**
     * @throws Exception
     */
    public function authenticate(string $username, string $password): string
    {
        $jwtReturn = $this->getJsonWebTokenObject();
        if (!$jwtReturn->success) {
            throw new Exception(
                "Could not authenticate through ".$this->getName().
                ". Try again later or get in touch with your facilitator."
            );
        }

        $jwt = $jwtReturn->jwt;
        // use the jwt to check the sent username and password at the Authoriser
        $userCheckReturn = json_decode($this->CallBack(
            Config::getInstance()->GetAuthJWTUserCheck(),
            array(
                "jwt" => $jwt,
                "audience" => GameSession::GetRequestApiRoot(),
                "username" => $username,
                "password" => $password
            ),
            array(), // no headers
            false,  // synchronous, so wait
            true
        )); // post as json
        if (!$userCheckReturn->success) {
            throw new Exception("Username and/or password incorrect.");
        }
        return $userCheckReturn->username; //$userCheckReturn->email;
    }

    /**
     * @throws Exception
     * @noinspection SpellCheckingInspection
     */
    public function checkuser(string $username): array
    {
        $jwtReturn = $this->getJsonWebTokenObject();
        if (!$jwtReturn->success) {
            throw new Exception(
                "Could not authenticate through ".$this->getName().
                ". Try again later or get in touch with your facilitator."
            );
        }
        $jwt = $jwtReturn->jwt;
        // use the jwt to check the sent username and password at the Authoriser
        $usercheck_return = json_decode($this->CallBack(
            Config::getInstance()->GetAuthJWTUserCheck(),
            array(
                "jwt" => $jwt,
                "audience" => GameSession::GetRequestApiRoot(),
                "username" => $username
            ),
            array(), // no headers
            false,  // synchronous, so wait
            true
        )); // post as json
        if (!is_object($usercheck_return) || !property_exists($usercheck_return, 'success') ||
            !$usercheck_return->success) {
            throw new Exception("Users not found.");
        }
        return array("found" => $usercheck_return->found, "notfound" => $usercheck_return->notfound);
    }
}
